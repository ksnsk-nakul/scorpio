<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WalletTransaction extends Model
{
    protected $fillable = [
        'txn_ref', 'recipient_user_id',
        'payer_name', 'payer_email',
        'type', 'amount_paise', 'balance_after_paise',
        'category', 'note',
        'razorpay_payment_id', 'razorpay_order_id',
        'meta',
    ];

    protected $casts = [
        'payer_email' => 'encrypted',
        'meta'        => 'array',
    ];

    protected $hidden = ['payer_email'];

    public static function generateRef(): string
    {
        do {
            $ref = 'TXN-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (static::where('txn_ref', $ref)->exists());

        return $ref;
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
