<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryEntry extends Model
{
    protected $fillable = [
        'user_id', 'book_id', 'status', 'last_chapter_id', 'last_read_at',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'reading',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function lastChapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'last_chapter_id');
    }
}
