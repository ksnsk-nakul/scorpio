<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'user_id', 'plan', 'status',
        'razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature',
        'current_period_end',
    ];

    protected $casts = [
        'current_period_end' => 'datetime',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->current_period_end === null || $this->current_period_end->isFuture());
    }
}
