<?php

use App\Jobs\IndexBookChunksJob;
use App\Jobs\ParseEpubBookJob;
use App\Models\Book;
use App\Models\User;
use App\Services\EpubParsingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EpubFixtureBuilder;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
});

it('marks the book ready after successful parsing', function () {
    $fixture = EpubFixtureBuilder::build(
        bookTitle: 'A Fine Book',
        author: 'Jane Doe',
        chapters: [['title' => 'One', 'body' => '<p>Text.</p>']],
    );
    $relativePath = 'books/uploads/' . basename($fixture);
    Storage::disk('public')->put($relativePath, file_get_contents($fixture));
    unlink($fixture);

    $book = Book::create([
        'title' => 'placeholder',
        'source_epub_path' => $relativePath,
        'uploaded_by' => $this->user->id,
        'status' => 'pending',
    ]);

    (new ParseEpubBookJob($book))->handle(new EpubParsingService());

    expect($book->fresh()->status)->toBe('ready');
});

it('marks the book failed with a reason when parsing throws', function () {
    $book = Book::create([
        'title' => 'placeholder',
        'source_epub_path' => 'books/uploads/does-not-exist.epub',
        'uploaded_by' => $this->user->id,
        'status' => 'pending',
    ]);

    $job = new ParseEpubBookJob($book);

    try {
        $job->handle(new EpubParsingService());
    } catch (\Throwable $e) {
        $job->failed($e);
    }

    expect($book->fresh()->status)->toBe('failed')
        ->and($book->fresh()->status_reason)->not->toBeNull();
});

it('dispatches IndexBookChunksJob once the book is parsed and ready', function () {
    Queue::fake();

    $fixture = EpubFixtureBuilder::build(
        bookTitle: 'A Fine Book',
        author: 'Jane Doe',
        chapters: [['title' => 'One', 'body' => '<p>Text.</p>']],
    );
    $relativePath = 'books/uploads/' . basename($fixture);
    Storage::disk('public')->put($relativePath, file_get_contents($fixture));
    unlink($fixture);

    $book = Book::create([
        'title' => 'placeholder',
        'source_epub_path' => $relativePath,
        'uploaded_by' => $this->user->id,
        'status' => 'pending',
    ]);

    (new ParseEpubBookJob($book))->handle(new EpubParsingService());

    Queue::assertPushed(IndexBookChunksJob::class);
});
