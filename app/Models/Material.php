<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Material extends Model
{
    protected $fillable = ['topic_id', 'type', 'content', 'download_policy', 'status'];

    protected $attributes = ['download_policy' => 'view_only', 'status' => 'ready'];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function isDownloadable(): bool
    {
        return $this->download_policy === 'downloadable';
    }
}
