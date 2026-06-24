<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    protected $fillable = ['name','slug','template','blocks','status','published_at'];

    protected $casts = ['blocks' => 'array', 'published_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn ($p) => $p->slug ??= Str::slug($p->name));
    }

    public function serviceCards(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ServiceCard::class);
    }
}
