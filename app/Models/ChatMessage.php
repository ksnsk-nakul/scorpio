<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $connection = 'rag';

    public $timestamps = false;

    protected $fillable = ['thread_id', 'role', 'content', 'citations'];

    protected $casts = [
        'citations' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ChatMessage $message) {
            $message->created_at ??= now();
        });
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }
}
