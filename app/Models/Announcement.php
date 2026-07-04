<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'title', 'body', 'type', 'display',
        'cta_label', 'cta_url', 'active', 'starts_at', 'ends_at',
    ];

    protected $casts = [
        'active'    => 'boolean',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
