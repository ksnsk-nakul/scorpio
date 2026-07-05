<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function index(Request $request): Response
    {
        $user  = auth()->user();
        $query = WalletTransaction::where('recipient_user_id', $user->id);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('txn_ref', 'like', "%{$search}%")
                  ->orWhere('payer_name', 'like', "%{$search}%")
                  ->orWhere('note', 'like', "%{$search}%");
            });
        }

        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($from = $request->get('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $transactions = $query->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($tx) => [
                'id'                  => $tx->id,
                'txn_ref'             => $tx->txn_ref,
                'type'                => $tx->type,
                'category'            => $tx->category,
                'amount_paise'        => $tx->amount_paise,
                'balance_after_paise' => $tx->balance_after_paise,
                'payer_name'          => $tx->payer_name,
                'note'                => $tx->note,
                'razorpay_payment_id' => $tx->razorpay_payment_id,
                'created_at'          => $tx->created_at,
            ]);

        return Inertia::render('Admin/Billing/Wallet', [
            'balance'      => $user->wallet_balance_paise,
            'transactions' => $transactions,
            'filters'      => $request->only(['search', 'category', 'type', 'from', 'to']),
            'payLink'      => route('wallet.pay.show', $user->username),
            'razorpayKey'  => config('billing.razorpay.key_id'),
        ]);
    }
}
