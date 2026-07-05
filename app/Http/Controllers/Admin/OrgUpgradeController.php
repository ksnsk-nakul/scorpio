<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Razorpay\Api\Api;

class OrgUpgradeController extends Controller
{
    public function createOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan'            => 'required|in:team,business,enterprise',
            'org_name'        => 'required|string|max:100',
            'org_slug'        => 'required|string|max:100|unique:organizations,slug|regex:/^[a-z0-9\-]+$/',
            'org_description' => 'nullable|string|max:500',
        ]);

        // Enterprise has no Razorpay price — caller shows "Contact us"
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

        // Stash org details in session — verified and applied atomically in verify()
        session([
            'org_upgrade' => [
                'plan'            => $data['plan'],
                'org_name'        => $data['org_name'],
                'org_slug'        => $data['org_slug'],
                'org_description' => $data['org_description'] ?? null,
            ],
        ]);

        // Create pending subscription without touching existing active ones
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

        // HMAC verification before any DB writes
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
            return redirect()->route('admin.billing.index')
                ->withErrors(['payment' => 'Session expired. Please try again.']);
        }

        $org = DB::transaction(function () use ($request, $upgrade) {
            $user = auth()->user();

            // Activate the pending subscription
            $subscription = Subscription::where('razorpay_order_id', $request->razorpay_order_id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $subscription->update([
                'status'              => 'active',
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature'  => $request->razorpay_signature,
                'current_period_end'  => now()->addMonth(),
            ]);

            // Cancel all other active subscriptions inside the transaction
            Subscription::where('user_id', $user->id)
                ->where('id', '!=', $subscription->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);

            // Update user plan
            $user->update(['plan' => $upgrade['plan']]);

            // Re-validate slug uniqueness at write time (race condition guard)
            if (Organization::where('slug', $upgrade['org_slug'])->exists()) {
                throw ValidationException::withMessages([
                    'org_slug' => 'This slug was just taken. Please choose another.',
                ]);
            }

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
