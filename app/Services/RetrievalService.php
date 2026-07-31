<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Support\Facades\DB;

class RetrievalService
{
    // Resolved via app() rather than constructor injection so callers (and tests) can
    // keep using `new RetrievalService()` directly without wiring a container binding.
    /** @return array<int, array{book_id:int, chapter_id:int, content:string, book_title:string, chapter_title:?string}> */
    public function search(string $question, int $limit = 6): array
    {
        $gemini = app(GeminiClient::class);
        $embedding = $gemini->embed($question);
        $vectorLiteral = '['.implode(',', $embedding).']';

        // $limit binds fine as a native PDO::PARAM_INT (Laravel's Connection::bindValues
        // does this automatically for PHP int values) even with Laravel's default
        // non-emulated prepares against Postgres — verified against the real Supabase
        // connection. The explicit (int) cast is just defense-in-depth against a caller
        // bypassing the type hint (e.g. a loose dynamic call).
        $rows = DB::connection('rag')->select(
            'select book_id, chapter_id, content from book_chunks order by embedding <=> ?::vector limit ?',
            [$vectorLiteral, (int) $limit]
        );

        if (empty($rows)) {
            return [];
        }

        // Batch-load books/chapters instead of one find() per row: this is called on
        // every chat message (Task 6), and top-k chunks frequently share a book/chapter.
        $bookIds = collect($rows)->pluck('book_id')->unique()->all();
        $chapterIds = collect($rows)->pluck('chapter_id')->unique()->all();
        $books = Book::whereIn('id', $bookIds)->get()->keyBy('id');
        $chapters = Chapter::whereIn('id', $chapterIds)->get()->keyBy('id');

        $results = [];
        foreach ($rows as $row) {
            $book = $books->get($row->book_id);
            $chapter = $chapters->get($row->chapter_id);

            $results[] = [
                'book_id' => (int) $row->book_id,
                'chapter_id' => (int) $row->chapter_id,
                'content' => $row->content,
                'book_title' => $book?->title ?? 'Unknown book',
                'chapter_title' => $chapter?->title,
            ];
        }

        return $results;
    }
}
