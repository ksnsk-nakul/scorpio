<?php

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function ageBook(Book $book, $when): void
{
    $book->timestamps = false;
    $book->updated_at = $when;
    $book->save();
}

beforeEach(function () {
    Storage::fake('public');

    $this->staleePending = Book::factory()->create(['title' => 'Stuck Pending', 'status' => 'pending']);
    ageBook($this->staleePending, now()->subHours(2));
    Storage::disk('public')->put($this->staleePending->source_epub_path, 'fake epub bytes');

    $this->staleProcessing = Book::factory()->create(['title' => 'Stuck Processing', 'status' => 'processing']);
    ageBook($this->staleProcessing, now()->subHours(3));

    $this->freshPending = Book::factory()->create(['title' => 'Fresh Pending', 'status' => 'pending']);
    ageBook($this->freshPending, now()->subMinutes(5));

    $this->readyBook = Book::factory()->create(['title' => 'Ready Book', 'status' => 'ready']);
    ageBook($this->readyBook, now()->subHours(5));
});

it('does not delete anything on --dry-run', function () {
    $countBefore = Book::count();

    $this->artisan('library:purge-stale-uploads', ['--dry-run' => true])
        ->assertSuccessful();

    expect(Book::count())->toBe($countBefore);
    expect(Storage::disk('public')->exists($this->staleePending->source_epub_path))->toBeTrue();
});

it('deletes uploads stuck in pending/processing past the window and their files', function () {
    $this->artisan('library:purge-stale-uploads')
        ->assertSuccessful();

    expect(Book::find($this->staleePending->id))->toBeNull();
    expect(Book::find($this->staleProcessing->id))->toBeNull();
    expect(Storage::disk('public')->exists($this->staleePending->source_epub_path))->toBeFalse();
});

it('leaves fresh pending uploads and ready books untouched', function () {
    $this->artisan('library:purge-stale-uploads')
        ->assertSuccessful();

    expect(Book::find($this->freshPending->id))->not->toBeNull();
    expect(Book::find($this->readyBook->id))->not->toBeNull();
});

it('respects a custom --hours window', function () {
    $this->artisan('library:purge-stale-uploads', ['--hours' => 4])
        ->assertSuccessful();

    // staleProcessing is only 3h old, under the 4h window given here.
    expect(Book::find($this->staleProcessing->id))->not->toBeNull();
    // staleePending is 2h old, also under 4h.
    expect(Book::find($this->staleePending->id))->not->toBeNull();
});
