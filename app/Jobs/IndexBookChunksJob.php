<?php

namespace App\Jobs;

use App\Models\Book;
use App\Models\BookChunk;
use App\Services\ChapterChunker;
use App\Services\GeminiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class IndexBookChunksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Defensive hard ceiling on top of ChapterChunker's ~3000 char soft cap: a
    // malformed chapter (no <p> boundaries) could yield one oversized chunk that
    // exceeds Gemini's embedding input limits. This does not change ChapterChunker's
    // own contract — it's a safety net applied only at the embed call site.
    private const MAX_EMBED_INPUT_CHARS = 8000;

    public function __construct(public Book $book) {}

    public function handle(GeminiClient $gemini, ChapterChunker $chunker): void
    {
        BookChunk::where('book_id', $this->book->id)->delete();

        $attempted = 0;
        $succeeded = 0;

        foreach ($this->book->chapters as $chapter) {
            $textChunks = $chunker->chunk($chapter->content ?? '');

            foreach ($textChunks as $index => $text) {
                $attempted++;

                try {
                    $embedInput = mb_substr($text, 0, self::MAX_EMBED_INPUT_CHARS);
                    $embedding = $gemini->embed($embedInput);
                    $vectorLiteral = '[' . implode(',', $embedding) . ']';

                    DB::connection('rag')->statement(
                        'insert into book_chunks (book_id, chapter_id, chunk_index, content, embedding) values (?, ?, ?, ?, ?::vector)',
                        [$this->book->id, $chapter->id, $index, $text, $vectorLiteral]
                    );

                    $succeeded++;
                } catch (Throwable $e) {
                    // Isolate per-chunk failures (rate limits, oversized input, transient
                    // network errors) so one bad chunk never aborts the rest of the book —
                    // this job runs against ~870 real books, and a single flaky embed call
                    // must not sink an entire book's indexing. Never log chunk text itself.
                    Log::warning('IndexBookChunksJob: failed to embed/store a chunk, skipping it', [
                        'book_id' => $this->book->id,
                        'chapter_id' => $chapter->id,
                        'chunk_index' => $index,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // A book with real content where every single chunk failed (credentials expired,
        // rag connection unreachable, systemic outage) must not look identical to a
        // successful run — throw so the queue records/retries a real failure instead of
        // silently leaving the book un-indexed with no signal anywhere.
        if ($attempted > 0 && $succeeded === 0) {
            throw new \RuntimeException("IndexBookChunksJob: all {$attempted} chunk(s) failed for book {$this->book->id}; nothing was indexed.");
        }
    }
}
