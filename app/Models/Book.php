<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'author_id', 'title', 'slug', 'description', 'cover_path',
        'language', 'publisher', 'published_date', 'subject',
        'source_epub_path', 'status', 'status_reason', 'uploaded_by',
    ];

    protected $casts = [
        'published_date' => 'date',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::creating(function (Book $book) {
            if (! $book->slug) {
                $book->slug = static::uniqueSlug($book->title);
            }
        });
    }

    public static function uniqueSlug(string $title, ?int $excludeId = null): string
    {
        $base = Str::slug($title) ?: 'book';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function author(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function chapters(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('sort_order');
    }

    public function uploader(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function libraryEntries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LibraryEntry::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    // A book stuck at pending/processing for a while almost always means the
    // job that would move it forward was lost (e.g. no queue worker was running
    // when it was dispatched) rather than genuinely still in flight — a fresh
    // upload legitimately sits at "pending" for a few seconds/minutes, so this
    // only trips once that window has clearly passed.
    public function isStuck(): bool
    {
        return $this->isProcessing() && $this->updated_at->lt(now()->subMinutes(10));
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }
}
