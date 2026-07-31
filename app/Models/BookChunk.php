<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookChunk extends Model
{
    protected $connection = 'rag';

    public $timestamps = false;

    protected $fillable = ['book_id', 'chapter_id', 'chunk_index', 'content'];
}
