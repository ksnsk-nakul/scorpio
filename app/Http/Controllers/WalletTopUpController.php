<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Razorpay\Api\Api;

class WalletTopUpController extends Controller
{
    private const MIN_PAISE = 10000;   // ₹100
    private const MAX_PAISE = 1000000; // ₹10,000

    private function razorpay(): Api
    {
        $key    = config('billing.razorpay.key_id');
        $secret = config('billing.razorpay.key_secret');
        abort_if(blank($key) || blank($secret), 503, 'Payment gateway not configured.');
        return new Api($key, $secret);
    }

    public function show(string $username): Response
    {
        $recipient = User::where('username', $username)
            ->select(['id', 'name', 'username', 'site_name'])
            ->firstOrFail();

        $payer = auth()->user();

        return Inertia::render('WalletTopUp', [
            'recipient'      => $recipient->only('name', 'username', 'site_name'),
            'razorpayKey'    => config('billing.razorpay.key_id'),
            'payerName'      => $payer?->name,
            'payerEmail'     => $payer?->email,
            'paymentMethods' => $payer
                ? $payer->paymentMethods()->get(['id', 'type', 'label', 'is_default'])
                : [],
        ]);
    }

    public function createOrder(string $username, Request $request): JsonResponse
    {
        $user = User::where('username', $username)->select(['id', 'name', 'email'])->firstOrFail();

        $payer = auth()->user();

        // Authenticated users: name/email come from their account, not the form
        if ($payer) {
            $data = $request->validate([
                'amount_paise' => ['required', 'integer', 'min:' . self::MIN_PAISE, 'max:' . self::MAX_PAISE],
                'note'         => ['nullable', 'string', 'max:255'],
            ]);
            $data['payer_name']  = $payer->name;
            $data['payer_email'] = $payer->email;
        } else {
            $data = $request->validate([
                'amount_paise' => ['required', 'integer', 'min:' . self::MIN_PAISE, 'max:' . self::MAX_PAISE],
                'payer_name'   => ['required', 'string', 'max:100'],
                'payer_email'  => ['required', 'email', 'max:255'],
                'note'         => ['nullable', 'string', 'max:255'],
            ]);
        }

        // Resolve saved payment method if payer is authenticated and selected one
        $savedMethod     = null;
        $razorpayTokenId = null;
        $upiVpa          = null;

        if ($payer && $request->filled('payment_method_id')) {
            $savedMethod = $payer->paymentMethods()
                ->find((int) $request->payment_method_id);

            if ($savedMethod) {
                if ($savedMethod->type === 'card') {
                    $razorpayTokenId = $savedMethod->razorpay_token_id;
                } elseif ($savedMethod->type === 'upi') {
                    $upiVpa = $savedMethod->makeVisible('upi_id')->upi_id;
                }
            }
        }

        $order = $this->razorpay()->order->create([
            'amount'   => $data['amount_paise'],
            'currency' => 'INR',
            'notes'    => [
                'purpose'           => 'wallet_topup',
                'recipient_user_id' => (string) $user->id,
                'recipient_name'    => $user->name,
                'payer_name'        => $data['payer_name'],
            ],
        ]);

        session(['wallet_topup' => [
            'order_id'          => $order->id,
            'recipient_user_id' => $user->id,
            'amount_paise'      => $data['amount_paise'],
            'payer_name'        => $data['payer_name'],
            'payer_email'       => $data['payer_email'],
            'note'              => $data['note'] ?? null,
        ]]);

        $customerId = null;
        if ($payer && $razorpayTokenId) {
            if ($payer->razorpay_customer_id) {
                $customerId = $payer->razorpay_customer_id;
            } else {
                try {
                    $customer = $this->razorpay()->customer->create([
                        'name'          => $payer->name,
                        'email'         => $payer->email,
                        'fail_existing' => '0',
                    ]);
                    $payer->update(['razorpay_customer_id' => $customer->id]);
                    $customerId = $customer->id;
                } catch (\Throwable) {
                    // non-critical — proceed without customer_id
                }
            }
        }

        return response()->json([
            'order_id'           => $order->id,
            'amount'             => $data['amount_paise'],
            'key'                => config('billing.razorpay.key_id'),
            'payer_name'         => $data['payer_name'],
            'payer_email'        => $data['payer_email'],
            'razorpay_token_id'  => $razorpayTokenId,
            'customer_id'        => $customerId,
            'upi_vpa'            => $upiVpa,
        ]);
    }

    public function verify(string $username, Request $request): RedirectResponse
    {
        $request->validate([
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
        ]);

        $expected = hash_hmac(
            'sha256',
            $request->razorpay_order_id . '|' . $request->razorpay_payment_id,
            config('billing.razorpay.key_secret')
        );

        abort_unless(hash_equals($expected, $request->razorpay_signature), 422);

        $pending = session('wallet_topup');

        abort_if(
            ! $pending || $pending['order_id'] !== $request->razorpay_order_id,
            422,
            'Session mismatch. Please try again.'
        );

        $user = User::findOrFail($pending['recipient_user_id']);

        DB::transaction(function () use ($user, $pending, $request) {
            $user->creditWallet(
                (int) $pending['amount_paise'],
                'topup',
                $pending['note'] ?? 'Wallet top-up',
                $pending['payer_name'],
                $pending['payer_email'],
                $request->razorpay_payment_id,
                $request->razorpay_order_id,
                ['source' => 'public_topup_link']
            );
        });

        $txnRef = WalletTransaction::where('razorpay_payment_id', $request->razorpay_payment_id)
            ->value('txn_ref');

        session()->forget('wallet_topup');

        return redirect()->route('wallet.pay.done', ['username' => $username])
            ->with('topup_success', [
                'txn_ref'        => $txnRef,
                'amount_paise'   => $pending['amount_paise'],
                'recipient_name' => $user->name,
                'payer_name'     => $pending['payer_name'],
            ]);
    }

    public function done(string $username, Request $request): Response
    {
        $payload = session('topup_success');

        return Inertia::render('WalletTopUp', [
            'recipient'   => ['username' => $username],
            'razorpayKey' => config('billing.razorpay.key_id'),
            'done'        => $payload,
        ]);
    }
}
