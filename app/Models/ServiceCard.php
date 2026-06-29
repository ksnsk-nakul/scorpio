<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCard extends Model
{
    protected $fillable = [
        'user_id', 'title', 'description', 'icon', 'image', 'tags',
        'featured', 'sort_order', 'page_id', 'external_url',
    ];

    protected $casts = ['tags' => 'array', 'featured' => 'boolean'];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function page(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
