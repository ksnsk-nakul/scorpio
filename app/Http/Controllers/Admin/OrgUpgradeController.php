<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Razorpay\Api\Api;

class OrgUpgradeController extends Controller
{
    public function createOrder(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'plan'            => 'required|in:team,business,enterprise',
            'org_name'        => 'required|string|max:100',
            'org_slug'        => 'required|string|max:100|unique:organizations,slug|regex:/^[a-z0-9\-]+$/',
            'org_description' => 'nullable|string|max:500',
        ]);

        // Enterprise has no Razorpay price — redirect to contact
        $price = config("billing.plans.{$data['plan']}.price");
        if ($price === null) {
            return response()->json(['contact' => true]);
        }

        $api = new Api(
            config('billing.razorpay.key_id'),
            config('billing.razorpay.key_secret')
        );

        $order = $api->order->create([
            'amount'   => $price,
            'currency' => 'INR',
            'notes'    => ['plan' => $data['plan']],
        ]);

        // Store org details in session for use during verify
        session([
            'org_upgrade' => [
                'plan'            => $data['plan'],
                'org_name'        => $data['org_name'],
                'org_slug'        => $data['org_slug'],
                'org_description' => $data['org_description'] ?? null,
            ],
        ]);

        // Create pending subscription
        Subscription::where('user_id', auth()->id())
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        Subscription::create([
            'user_id'           => auth()->id(),
            'plan'              => $data['plan'],
            'status'            => 'pending',
            'razorpay_order_id' => $order->id,
        ]);

        return response()->json([
            'order_id' => $order->id,
            'amount'   => $price,
            'key'      => config('billing.razorpay.key_id'),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
        ]);

        // Verify HMAC signature
        $expectedSignature = hash_hmac(
            'sha256',
            $request->razorpay_order_id . '|' . $request->razorpay_payment_id,
            config('billing.razorpay.key_secret')
        );

        if (! hash_equals($expectedSignature, $request->razorpay_signature)) {
            return back()->withErrors(['payment' => 'Payment verification failed.']);
        }

        $upgrade = session('org_upgrade');
        if (! $upgrade) {
            return redirect()->route('admin.billing.index')->withErrors(['payment' => 'Session expired. Please try again.']);
        }

        $org = DB::transaction(function () use ($request, $upgrade) {
            $user = auth()->user();

            // Activate subscription
            $subscription = Subscription::where('razorpay_order_id', $request->razorpay_order_id)->firstOrFail();
            $subscription->update([
                'status'              => 'active',
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature'  => $request->razorpay_signature,
                'current_period_end'  => now()->addMonth(),
            ]);

            // Cancel other active subscriptions
            Subscription::where('user_id', $user->id)
                ->where('id', '!=', $subscription->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);

            // Update user plan
            $user->update(['plan' => $upgrade['plan']]);

            // Create organization
            return Organization::create([
                'owner_id'    => $user->id,
                'name'        => $upgrade['org_name'],
                'slug'        => $upgrade['org_slug'],
                'description' => $upgrade['org_description'],
                'plan'        => $upgrade['plan'],
            ]);
        });

        session()->forget('org_upgrade');

        return redirect()->route('admin.organizations.show', $org)
            ->with('success', 'Organization created and plan upgraded successfully!');
    }
}
