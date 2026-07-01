<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\BadRequestError;

class BillingController extends Controller
{
    private function razorpay(): Api
    {
        $key    = config('billing.razorpay.key_id');
        $secret = config('billing.razorpay.key_secret');

        abort_if(blank($key) || blank($secret), 503, 'Razorpay is not configured.');

        return new Api($key, $secret);
    }

    public function index()
    {
        $user = auth()->user()->load('activeSubscription');

        return Inertia::render('Admin/Billing/Index', [
            'plans'        => config('billing.plans'),
            'currentPlan'  => $user->currentPlan(),
            'subscription' => $user->activeSubscription,
            'razorpayKey'  => config('billing.razorpay.key_id'),
        ]);
    }

    public function createOrder(Request $request)
    {
        $request->validate(['plan' => 'required|in:pro,business']);

        $plan = config('billing.plans.' . $request->plan);

        try {
            $order = $this->razorpay()->order->create([
                'amount'          => $plan['price'],
                'currency'        => $plan['currency'],
                'receipt'         => 'order_' . auth()->id() . '_' . time(),
                'payment_capture' => 1,
                'notes'           => [
                    'user_id' => (string) auth()->id(),
                    'plan'    => $request->plan,
                ],
            ]);
        } catch (BadRequestError $e) {
            return response()->json(['message' => 'Razorpay error: ' . $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Could not create payment order. Please try again.'], 500);
        }

        Subscription::create([
            'user_id'           => auth()->id(),
            'plan'              => $request->plan,
            'status'            => 'pending',
            'razorpay_order_id' => $order->id,
        ]);

        return response()->json([
            'order_id' => $order->id,
            'amount'   => $order->amount,
            'currency' => $order->currency,
        ]);
    }

    public function verify(Request $request)
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

        if (! hash_equals($expected, $request->razorpay_signature)) {
            return redirect()->route('admin.billing.index')
                ->with('error', 'Payment verification failed. Please contact support.');
        }

        $subscription = Subscription::where('razorpay_order_id', $request->razorpay_order_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $subscription->update([
            'status'              => 'active',
            'razorpay_payment_id' => $request->razorpay_payment_id,
            'razorpay_signature'  => $request->razorpay_signature,
            'current_period_end'  => now()->addMonth(),
        ]);

        // Deactivate any other active subscriptions for this user
        Subscription::where('user_id', auth()->id())
            ->where('id', '!=', $subscription->id)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        auth()->user()->update(['plan' => $subscription->plan]);

        return redirect()->route('admin.billing.index')
            ->with('success', 'Subscription activated! Welcome to ' . ucfirst($subscription->plan) . '.');
    }

    public function cancel()
    {
        $subscription = auth()->user()->activeSubscription;

        if ($subscription) {
            $subscription->update(['status' => 'cancelled']);
            auth()->user()->update(['plan' => 'free']);
        }

        return redirect()->route('admin.billing.index')
            ->with('success', 'Subscription cancelled. Your plan reverts to Free.');
    }
}
