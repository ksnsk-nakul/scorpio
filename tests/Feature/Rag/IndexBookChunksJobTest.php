<?php

use App\Jobs\IndexBookChunksJob;
use App\Models\Book;
use App\Models\BookChunk;
use App\Models\Chapter;
use App\Support\RagConnectionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (! RagConnectionGuard::available()) {
        $this->markTestSkipped('The `rag` Postgres connection is not configured/reachable.');
    }
});

// RefreshDatabase only resets the app's default (sqlite) connection — it does not
// touch the 'rag' Postgres connection, which is a real, persistent, shared schema
// (rag_test) used across every test file. Book::factory() reuses small auto-increment
// ids across test runs, so without explicit cleanup here, book_chunks rows leak
// between test files and can pollute other tests' unfiltered similarity queries
// (discovered while building RetrievalServiceTest, which searches book_chunks
// globally with no WHERE book_id clause).

it('embeds and stores chunks for every chapter of a book', function () {
    $book = Book::factory()->create(['status' => 'ready']);

    try {
        Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 0, 'content' => '<p>Chapter one content here.</p>']);
        Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 1, 'content' => '<p>Chapter two content here.</p>']);

        Http::fake([
            'generativelanguage.googleapis.com/*embedContent*' => Http::response([
                'embedding' => ['values' => array_fill(0, 768, 0.05)],
            ], 200),
        ]);

        (new IndexBookChunksJob($book))->handle(app(\App\Services\GeminiClient::class), app(\App\Services\ChapterChunker::class));

        $chunks = BookChunk::where('book_id', $book->id)->get();
        expect($chunks)->toHaveCount(2);
        expect($chunks->pluck('chapter_id')->unique())->toHaveCount(2);
    } finally {
        BookChunk::where('book_id', $book->id)->delete();
    }
});

it('replaces existing chunks on re-run rather than duplicating them', function () {
    $book = Book::factory()->create(['status' => 'ready']);

    try {
        $chapter = Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 0, 'content' => '<p>Original content.</p>']);

        Http::fake([
            'generativelanguage.googleapis.com/*embedContent*' => Http::response([
                'embedding' => ['values' => array_fill(0, 768, 0.05)],
            ], 200),
        ]);

        $job = new IndexBookChunksJob($book);
        $job->handle(app(\App\Services\GeminiClient::class), app(\App\Services\ChapterChunker::class));
        $job->handle(app(\App\Services\GeminiClient::class), app(\App\Services\ChapterChunker::class));

        expect(BookChunk::where('book_id', $book->id)->count())->toBe(1);
    } finally {
        BookChunk::where('book_id', $book->id)->delete();
    }
});

it('skips chapters with no extractable text', function () {
    $book = Book::factory()->create(['status' => 'ready']);

    try {
        Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 0, 'content' => '<img src="cover.jpg">']);

        Http::fake();

        (new IndexBookChunksJob($book))->handle(app(\App\Services\GeminiClient::class), app(\App\Services\ChapterChunker::class));

        expect(BookChunk::where('book_id', $book->id)->count())->toBe(0);
        Http::assertNothingSent();
    } finally {
        BookChunk::where('book_id', $book->id)->delete();
    }
});

it('logs and skips a chunk whose embedding call fails, without aborting the rest of the book', function () {
    $book = Book::factory()->create(['status' => 'ready']);

    try {
        Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 0, 'content' => '<p>First chapter, will fail.</p>']);
        Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 1, 'content' => '<p>Second chapter, will succeed.</p>']);

        // GeminiClient retries up to 3 total attempts on a 429 before giving up, so the
        // first chapter's chunk must fail on all 3 of its attempts (calls 1-3) to actually
        // exhaust retries and surface as a failure; the second chapter's chunk (call 4)
        // then succeeds on its first attempt.
        $callCount = 0;
        Http::fake(function () use (&$callCount) {
            $callCount++;
            if ($callCount <= 3) {
                return Http::response(['error' => ['message' => 'quota exceeded']], 429);
            }
            return Http::response(['embedding' => ['values' => array_fill(0, 768, 0.05)]], 200);
        });

        (new IndexBookChunksJob($book))->handle(app(\App\Services\GeminiClient::class), app(\App\Services\ChapterChunker::class));

        // the first chapter's chunk failed and was skipped; the second chapter's chunk still got indexed
        expect(BookChunk::where('book_id', $book->id)->count())->toBe(1);
    } finally {
        BookChunk::where('book_id', $book->id)->delete();
    }
});
