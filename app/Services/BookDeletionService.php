<?php
namespace App\Services;

use App\Models\Book;
use App\Support\RagConnectionGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BookDeletionService
{
    public function delete(Book $book): void
    {
        Storage::disk('public')->deleteDirectory("books/{$book->id}");
        Storage::disk('public')->delete($book->source_epub_path);

        // book_chunks lives on the separate `rag` Postgres connection with no FK to
        // sqlite's books table (cross-database, can't cascade) — clean it up explicitly
        // so a delete doesn't leave orphaned embeddings behind. Skip cleanly if the rag
        // connection isn't configured/reachable here, same as the migrations do.
        if (RagConnectionGuard::available()) {
            DB::connection('rag')->table('book_chunks')->where('book_id', $book->id)->delete();
        }

        $book->delete();
    }
}
