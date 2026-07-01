<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Razorpay\Api\Api;

class DonationController extends Controller
{
    private function razorpay(): Api
    {
        return new Api(
            config('billing.razorpay.key_id'),
            config('billing.razorpay.key_secret')
        );
    }

    public function show()
    {
        return Inertia::render('Donate', [
            'razorpayKey' => config('billing.razorpay.key_id'),
            'meUrl'       => config('billing.razorpay.me_url'),
        ]);
    }

    public function createOrder(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer|min:100|max:500000', // paise: ₹1 – ₹5000
            'name'   => 'nullable|string|max:100',
            'note'   => 'nullable|string|max:255',
        ]);

        $order = $this->razorpay()->order->create([
            'amount'          => $request->amount,
            'currency'        => 'INR',
            'receipt'         => 'donation_' . time(),
            'payment_capture' => 1,
            'notes'           => [
                'donor_name' => $request->name ?? 'Anonymous',
                'note'       => $request->note ?? '',
                'type'       => 'donation',
            ],
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

        $expectedSignature = hash_hmac(
            'sha256',
            $request->razorpay_order_id . '|' . $request->razorpay_payment_id,
            config('billing.razorpay.key_secret')
        );

        if (! hash_equals($expectedSignature, $request->razorpay_signature)) {
            return response()->json(['success' => false, 'message' => 'Payment verification failed.'], 422);
        }

        return response()->json(['success' => true]);
    }
}
