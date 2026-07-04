<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'owner_id', 'name', 'slug', 'description', 'logo',
        'plan', 'white_label', 'custom_brand_name',
    ];

    protected $casts = ['white_label' => 'boolean'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(OrganizationAchievement::class)->orderBy('sort_order');
    }

    public function memberLimit(): ?int
    {
        return config("billing.plans.{$this->plan}.member_limit");
    }

    public function withinMemberLimit(): bool
    {
        $limit = $this->memberLimit();
        return $limit === null || $this->members()->count() <= $limit;
    }
}
