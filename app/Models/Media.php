<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    public const OFFICE_MIMES = [
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.oasis.opendocument.text',
    ];

    public const COMIC_EXTENSIONS = ['cbz', 'cbr'];

    protected $fillable = ['user_id','disk','path','filename','mime_type','size','alt_text','status','status_reason','page_manifest'];

    protected $casts = [
        'page_manifest' => 'array',
    ];

    protected $attributes = [
        'status' => 'ready',
    ];

    public function mediable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        if ($this->disk === 'public') {
            return asset('storage/' . $this->path);
        }
        if ($this->disk === 'static') {
            return asset($this->path);
        }
        return Storage::disk($this->disk)->url($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->filename, PATHINFO_EXTENSION));
    }

    public function needsOfficeConversion(): bool
    {
        return in_array($this->mime_type, self::OFFICE_MIMES, true);
    }

    public function needsComicExtraction(): bool
    {
        return in_array($this->extension(), self::COMIC_EXTENSIONS, true);
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

    public function getConvertedPdfUrlAttribute(): ?string
    {
        if (! $this->needsOfficeConversion() || ! $this->isReady()) {
            return null;
        }

        return Storage::disk('public')->url("conversions/{$this->id}.pdf");
    }

    public function getComicPageUrlsAttribute(): array
    {
        if (! $this->needsComicExtraction() || empty($this->page_manifest)) {
            return [];
        }

        return collect($this->page_manifest)
            ->map(fn (string $filename) => Storage::disk('public')->url("comics/{$this->id}/{$filename}"))
            ->all();
    }
}
