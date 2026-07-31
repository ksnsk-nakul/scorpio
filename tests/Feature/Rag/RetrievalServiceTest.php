<?php

use App\Services\RetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// RetrievalService looks up Book/Chapter on the default (sqlite) connection to
// attach titles to each result. Without a migrated schema there, Book::find()
// throws "no such table: books" — same reason IndexBookChunksJobTest needs this.
uses(RefreshDatabase::class);

function insertTestChunk(int $bookId, int $chapterId, int $index, string $content, array $embedding): void
{
    $vector = '['.implode(',', $embedding).']';
    DB::connection('rag')->statement(
        'insert into book_chunks (book_id, chapter_id, chunk_index, content, embedding) values (?, ?, ?, ?, ?::vector)',
        [$bookId, $chapterId, $index, $content, $vector]
    );
}

it('returns the closest chunks by cosine distance', function () {
    $close = array_fill(0, 768, 0.1);
    $far = array_fill(0, 768, -0.9);

    try {
        insertTestChunk(1, 1, 0, 'closely related content', $close);
        insertTestChunk(1, 2, 0, 'unrelated content', $far);

        Http::fake([
            'generativelanguage.googleapis.com/*embedContent*' => Http::response([
                'embedding' => ['values' => array_fill(0, 768, 0.1)],
            ], 200),
        ]);

        $results = (new RetrievalService())->search('a question', limit: 2);

        expect($results)->toHaveCount(2);
        expect($results[0]['content'])->toBe('closely related content');
    } finally {
        DB::connection('rag')->table('book_chunks')->where('book_id', 1)->delete();
    }
});

it('respects the limit parameter', function () {
    try {
        for ($i = 0; $i < 10; $i++) {
            insertTestChunk(2, $i, 0, "chunk $i", array_fill(0, 768, $i * 0.01));
        }

        Http::fake([
            'generativelanguage.googleapis.com/*embedContent*' => Http::response([
                'embedding' => ['values' => array_fill(0, 768, 0.05)],
            ], 200),
        ]);

        $results = (new RetrievalService())->search('a question', limit: 3);

        expect($results)->toHaveCount(3);
    } finally {
        DB::connection('rag')->table('book_chunks')->where('book_id', 2)->delete();
    }
});
