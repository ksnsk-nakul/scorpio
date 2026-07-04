<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationAchievement extends Model
{
    protected $fillable = [
        'organization_id', 'user_id', 'title',
        'description', 'icon', 'achieved_at', 'sort_order',
    ];

    protected $casts = ['achieved_at' => 'date'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
