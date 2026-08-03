<?php

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('creates a pending book and dispatches a parse job on upload', function () {
    $file = UploadedFile::fake()->create('my-book.epub', 500, 'application/epub+zip');

    $response = $this->actingAs($this->admin)->postJson('/admin/library/books', ['file' => $file]);

    $response->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('title', 'my-book');
    // Scoped to this test's uploader, not a raw count, since $this->seed() also
    // creates LibrarySeeder's sample book (attributed to the seeded admin user).
    expect(Book::where('uploaded_by', $this->admin->id)->count())->toBe(1);
    Queue::assertPushed(\App\Jobs\ParseEpubBookJob::class);
});

it('accepts an epub uploaded with a generic zip mime type', function () {
    $file = UploadedFile::fake()->create('real-world.epub', 500, 'application/zip');

    $this->actingAs($this->admin)
        ->postJson('/admin/library/books', ['file' => $file])
        ->assertOk();
});

it('rejects a non-epub file', function () {
    $file = UploadedFile::fake()->create('not-a-book.txt', 10, 'text/plain');

    $this->actingAs($this->admin)
        ->postJson('/admin/library/books', ['file' => $file])
        ->assertStatus(422);
});

it('returns the current status for a book', function () {
    $book = Book::factory()->create(['status' => 'processing', 'uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->getJson("/admin/library/books/{$book->id}/status")
        ->assertOk()
        ->assertJsonPath('status', 'processing');
});

it('re-dispatches parsing for a failed book on retry', function () {
    $book = Book::factory()->create(['status' => 'failed', 'status_reason' => 'boom', 'uploaded_by' => $this->admin->id]);
    Storage::disk('public')->put($book->source_epub_path, 'fake epub bytes');

    $this->actingAs($this->admin)
        ->postJson("/admin/library/books/{$book->id}/retry")
        ->assertOk()
        ->assertJsonPath('status', 'pending');

    Queue::assertPushed(\App\Jobs\ParseEpubBookJob::class);
    expect($book->fresh()->status_reason)->toBeNull();
});

it('rejects retrying a book that is not failed or stuck', function () {
    $book = Book::factory()->create(['status' => 'ready', 'uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->postJson("/admin/library/books/{$book->id}/retry")
        ->assertStatus(422);
});

it('retries a stuck (long-pending) book', function () {
    $book = Book::factory()->create(['status' => 'pending', 'uploaded_by' => $this->admin->id]);
    $book->forceFill(['updated_at' => now()->subMinutes(15)])->save();
    Storage::disk('public')->put($book->source_epub_path, 'fake epub bytes');

    $this->actingAs($this->admin)
        ->postJson("/admin/library/books/{$book->id}/retry")
        ->assertOk()
        ->assertJsonPath('status', 'pending');

    Queue::assertPushed(\App\Jobs\ParseEpubBookJob::class);
});

it('rejects retrying a recently-created pending book (not actually stuck yet)', function () {
    $book = Book::factory()->create(['status' => 'pending', 'uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->postJson("/admin/library/books/{$book->id}/retry")
        ->assertStatus(422);
});

it('marks a stuck book as failed instead of retrying when its source file is missing', function () {
    $book = Book::factory()->create(['status' => 'processing', 'uploaded_by' => $this->admin->id]);
    $book->forceFill(['updated_at' => now()->subMinutes(15)])->save();
    // Deliberately no Storage::put — the source file doesn't exist.

    $response = $this->actingAs($this->admin)
        ->postJson("/admin/library/books/{$book->id}/retry")
        ->assertOk()
        ->assertJsonPath('status', 'failed');

    expect($response->json('status_reason'))->toContain('missing');
    expect($book->fresh()->status)->toBe('failed');
    Queue::assertNotPushed(\App\Jobs\ParseEpubBookJob::class);
});

it('marks a stuck book as failed via the explicit action', function () {
    $book = Book::factory()->create(['status' => 'processing', 'uploaded_by' => $this->admin->id]);
    $book->forceFill(['updated_at' => now()->subMinutes(15)])->save();

    $this->actingAs($this->admin)
        ->postJson("/admin/library/books/{$book->id}/mark-failed")
        ->assertOk()
        ->assertJsonPath('status', 'failed');

    expect($book->fresh()->status_reason)->not->toBeNull();
});

it('rejects marking a non-stuck book as failed', function () {
    $book = Book::factory()->create(['status' => 'pending', 'uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->postJson("/admin/library/books/{$book->id}/mark-failed")
        ->assertStatus(422);
});

it('lists all books with author names for the admin index page', function () {
    $author = \App\Models\Author::factory()->create(['name' => 'Jane Doe']);
    Book::factory()->create(['title' => 'A Book', 'author_id' => $author->id, 'status' => 'ready', 'uploaded_by' => $this->admin->id]);
    Book::factory()->create(['title' => 'Pending Book', 'author_id' => null, 'status' => 'pending', 'uploaded_by' => $this->admin->id]);

    $response = $this->actingAs($this->admin)->get('/admin/library');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Library/Index')
        // 3, not 2: the admin index lists every book regardless of uploader, and
        // $this->seed() also creates LibrarySeeder's sample book (oldest, so it
        // sorts last under latest()).
        ->has('books.data', 3)
        ->where('books.data.0.title', 'Pending Book') // latest() first
        ->where('books.data.1.author', 'Jane Doe'));
});

it('updates title, author, and description', function () {
    $book = Book::factory()->create(['title' => 'Old Title', 'uploaded_by' => $this->admin->id]);

    $response = $this->actingAs($this->admin)->patchJson("/admin/library/books/{$book->id}", [
        'title' => 'New Title',
        'author_name' => 'New Author',
        'description' => 'Updated description.',
    ]);

    $response->assertOk();
    $book->refresh();
    expect($book->title)->toBe('New Title')
        ->and($book->slug)->toBe('new-title')
        ->and($book->author->name)->toBe('New Author')
        ->and($book->description)->toBe('Updated description.');
});

it('reuses an existing author on update instead of creating a duplicate', function () {
    \App\Models\Author::factory()->create(['name' => 'Existing Author']);
    $book = Book::factory()->create(['uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)->patchJson("/admin/library/books/{$book->id}", [
        'author_name' => 'existing author',
    ]);

    // 3, not 2: the book's original factory author + this one + LibrarySeeder's
    // "Scorpio" author created by the outer $this->seed() call.
    expect(\App\Models\Author::count())->toBe(3);
});

it('deletes a book, its chapters, and its stored files', function () {
    $book = Book::factory()->create(['uploaded_by' => $this->admin->id]);
    \App\Models\Chapter::factory()->create(['book_id' => $book->id]);
    Storage::disk('public')->put("books/{$book->id}/cover.jpg", 'fake cover');
    Storage::disk('public')->put($book->source_epub_path, 'fake epub');

    $this->actingAs($this->admin)
        ->deleteJson("/admin/library/books/{$book->id}")
        ->assertOk();

    expect(Book::find($book->id))->toBeNull()
        ->and(\App\Models\Chapter::where('book_id', $book->id)->count())->toBe(0);
    Storage::disk('public')->assertMissing("books/{$book->id}/cover.jpg");
    Storage::disk('public')->assertMissing($book->source_epub_path);
});
