<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    protected $fillable = ['user_id', 'name', 'slug', 'template', 'blocks', 'status', 'published_at', 'is_home'];

    protected $casts = ['blocks' => 'array', 'published_at' => 'datetime', 'is_home' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(fn ($p) => $p->slug ??= Str::slug($p->name));
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function serviceCards(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ServiceCard::class);
    }
}
