<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    protected $fillable = [
        'user_id', 'type', 'label', 'upi_id', 'razorpay_token_id', 'is_default',
    ];

    protected $casts = [
        'upi_id'     => 'encrypted',
        'is_default' => 'boolean',
    ];

    protected $hidden = ['upi_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
