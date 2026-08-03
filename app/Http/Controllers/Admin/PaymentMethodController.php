<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Razorpay\Api\Api;

class PaymentMethodController extends Controller
{
    // A card is tokenized via a ₹1 authorize-only (never captured) Razorpay order,
    // rather than collecting raw card fields ourselves — keeps card data inside
    // Razorpay's hosted Checkout iframe (PCI SAQ-A), matching WalletTopUpController's
    // existing pattern instead of inventing a second, riskier flow.
    private const CARD_VERIFY_PAISE = 100;

    private function razorpay(): Api
    {
        $key    = config('billing.razorpay.key_id');
        $secret = config('billing.razorpay.key_secret');
        abort_if(blank($key) || blank($secret), 503, 'Payment gateway not configured.');
        return new Api($key, $secret);
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Billing/PaymentMethods', [
            'methods'     => auth()->user()->paymentMethods()
                ->get(['id', 'type', 'label', 'is_default', 'created_at']),
            'razorpayKey' => config('billing.razorpay.key_id'),
        ]);
    }

    public function createCardOrder(Request $request): JsonResponse
    {
        $data = $request->validate(['label' => 'required|string|max:50']);

        $user = auth()->user();

        if ($user->paymentMethods()->count() >= 5) {
            return response()->json(['message' => 'You can save up to 5 payment methods.'], 422);
        }

        $customerId = $user->razorpay_customer_id;
        if (! $customerId) {
            $customer   = $this->razorpay()->customer->create([
                'name'          => $user->name,
                'email'         => $user->email,
                'fail_existing' => '0',
            ]);
            $customerId = $customer->id;
            $user->update(['razorpay_customer_id' => $customerId]);
        }

        $order = $this->razorpay()->order->create([
            'amount'          => self::CARD_VERIFY_PAISE,
            'currency'        => 'INR',
            'payment_capture' => 0, // authorize only — never captured/charged, void() below on success
            'notes'           => ['purpose' => 'save_card', 'user_id' => (string) $user->id],
        ]);

        session(['add_card' => [
            'order_id' => $order->id,
            'label'    => $data['label'],
        ]]);

        return response()->json([
            'order_id'    => $order->id,
            'amount'      => self::CARD_VERIFY_PAISE,
            'key'         => config('billing.razorpay.key_id'),
            'customer_id' => $customerId,
            'name'        => $user->name,
            'email'       => $user->email,
        ]);
    }

    public function verifyCard(Request $request): RedirectResponse
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

        $pending = session('add_card');
        abort_if(
            ! $pending || $pending['order_id'] !== $request->razorpay_order_id,
            422,
            'Session mismatch. Please try again.'
        );

        $user = auth()->user();

        try {
            $payment = $this->razorpay()->payment->fetch($request->razorpay_payment_id);
            $tokenId = $payment->token_id ?? null;

            // The order was created with payment_capture: 0 (authorize only) — the
            // Razorpay PHP SDK has no explicit void() call, so we simply never
            // capture it. Razorpay auto-releases an uncaptured authorization on its
            // own; no charge ever completes.
        } catch (\Throwable $e) {
            return back()->withErrors(['card' => 'Could not verify the card with Razorpay. Please try again.']);
        }

        if (! $tokenId) {
            return back()->withErrors(['card' => 'Razorpay did not return a reusable card token. Please try again.']);
        }

        if ($user->paymentMethods()->where('razorpay_token_id', $tokenId)->exists()) {
            session()->forget('add_card');
            return back()->with('success', 'Card already saved.');
        }

        DB::transaction(function () use ($user, $pending, $tokenId) {
            $isDefault = ! $user->paymentMethods()->where('is_default', true)->exists();

            PaymentMethod::create([
                'user_id'           => $user->id,
                'type'              => 'card',
                'label'             => $pending['label'],
                'razorpay_token_id' => $tokenId,
                'is_default'        => $isDefault,
            ]);
        });

        session()->forget('add_card');

        return back()->with('success', 'Card saved.');
    }

    public function storeUpi(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'upi_id' => ['required', 'string', 'max:100', 'regex:/^[\w.\-]+@[\w]+$/'],
            'label'  => ['nullable', 'string', 'max:50'],
        ]);

        $user = auth()->user();

        if ($user->paymentMethods()->count() >= 5) {
            return back()->withErrors(['upi_id' => 'You can save up to 5 payment methods.']);
        }

        DB::transaction(function () use ($user, $data) {
            $isDefault = ! $user->paymentMethods()->where('is_default', true)->exists();

            PaymentMethod::create([
                'user_id'    => $user->id,
                'type'       => 'upi',
                'label'      => $data['label'] ?? $data['upi_id'],
                'upi_id'     => $data['upi_id'],
                'is_default' => $isDefault,
            ]);
        });

        return back()->with('success', 'UPI ID saved.');
    }

    public function setDefault(Request $request, PaymentMethod $method): RedirectResponse
    {
        abort_if($method->user_id !== auth()->id(), 403);

        DB::transaction(function () use ($method) {
            auth()->user()->paymentMethods()->update(['is_default' => false]);
            $method->update(['is_default' => true]);
        });

        return back()->with('success', 'Default payment method updated.');
    }

    public function destroy(PaymentMethod $method): RedirectResponse
    {
        abort_if($method->user_id !== auth()->id(), 403);
        $method->delete();
        return back()->with('success', 'Payment method removed.');
    }
}
