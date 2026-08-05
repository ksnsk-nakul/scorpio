<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookAlternateTitle extends Model
{
    public $timestamps = false;

    protected $fillable = ['book_id', 'title', 'source', 'created_at'];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
