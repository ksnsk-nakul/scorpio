<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Billing/PaymentMethods', [
            'methods' => auth()->user()->paymentMethods()
                ->get(['id', 'type', 'label', 'is_default', 'created_at']),
        ]);
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

    public function storeCard(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'razorpay_token_id' => 'required|string|max:100',
            'label'             => 'required|string|max:50',
        ]);

        $user = auth()->user();

        if ($user->paymentMethods()->count() >= 5) {
            return back()->withErrors(['token' => 'You can save up to 5 payment methods.']);
        }

        if ($user->paymentMethods()->where('razorpay_token_id', $data['razorpay_token_id'])->exists()) {
            return back()->with('success', 'Card already saved.');
        }

        DB::transaction(function () use ($user, $data) {
            $isDefault = ! $user->paymentMethods()->where('is_default', true)->exists();

            PaymentMethod::create([
                'user_id'           => $user->id,
                'type'              => 'card',
                'label'             => $data['label'],
                'razorpay_token_id' => $data['razorpay_token_id'],
                'is_default'        => $isDefault,
            ]);
        });

        return back()->with('success', 'Card saved.');
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
