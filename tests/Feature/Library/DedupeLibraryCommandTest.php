<?php

use App\Models\Book;
use App\Models\BookAlternateTitle;
use App\Models\Chapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    // Three distinct volumes of the same series.
    $this->volume1 = Book::factory()->create(['title' => 'Overlord - Volume 01', 'status' => 'ready']);
    $this->volume2 = Book::factory()->create(['title' => 'Overlord - Volume 02', 'status' => 'ready']);
    $this->volume3 = Book::factory()->create(['title' => 'Overlord - Volume 03', 'status' => 'ready']);

    // A genuine duplicate: same series + volume, one ready with chapters, one pending with none.
    $this->readyDuplicate = Book::factory()->create(['title' => 'Re:Zero - Volume 05', 'status' => 'ready']);
    Chapter::factory()->count(3)->create(['book_id' => $this->readyDuplicate->id]);
    $this->pendingDuplicate = Book::factory()->create(['title' => 'Re:Zero - Volume 05 [Yen Press]', 'status' => 'pending']);

    // A standalone book with no series marker.
    $this->standalone = Book::factory()->create(['title' => 'The Lonely Standalone Novel', 'status' => 'ready']);
});

it('does not delete or persist anything on --dry-run', function () {
    $countBefore = Book::count();

    $this->artisan('library:dedupe', ['--dry-run' => true])
        ->assertSuccessful();

    expect(Book::count())->toBe($countBefore);
    expect(BookAlternateTitle::count())->toBe(0);

    // Backfill fields should not have been persisted either.
    expect($this->volume1->fresh()->series_id)->toBeNull();
    expect($this->standalone->fresh()->series_id)->toBeNull();
});

it('merges the true duplicate and leaves everything else untouched', function () {
    $this->artisan('library:dedupe')
        ->assertSuccessful();

    expect(Book::find($this->pendingDuplicate->id))->toBeNull();
    expect(Book::find($this->readyDuplicate->id))->not->toBeNull();

    $alternate = BookAlternateTitle::where('book_id', $this->readyDuplicate->id)->first();
    expect($alternate)->not->toBeNull();
    expect($alternate->source)->toBe('cleanup');

    expect(Book::find($this->standalone->id))->not->toBeNull();
    expect(Book::find($this->volume1->id))->not->toBeNull();
    expect(Book::find($this->volume2->id))->not->toBeNull();
    expect(Book::find($this->volume3->id))->not->toBeNull();
});
