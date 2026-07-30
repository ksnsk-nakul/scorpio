<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Author extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'bio'];

    protected static function booted(): void
    {
        static::creating(function (Author $author) {
            if (! $author->slug) {
                $author->slug = static::uniqueSlug($author->name);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($name) ?: 'author';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public static function findOrCreateByName(string $name): self
    {
        $name = trim($name);
        $existing = static::whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();

        return $existing ?: static::create(['name' => $name]);
    }

    public function books(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Book::class);
    }
}
