<?php

use App\Models\Book;
use App\Models\Series;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows a series page listing all its ready volumes in order', function () {
    $series = Series::create(['name' => 'Overlord Series']);
    $volume2 = Book::factory()->create(['series_id' => $series->id, 'volume_number' => 2, 'status' => 'ready', 'title' => 'Overlord - Volume 02']);
    $volume1 = Book::factory()->create(['series_id' => $series->id, 'volume_number' => 1, 'status' => 'ready', 'title' => 'Overlord - Volume 01']);
    $volume3 = Book::factory()->create(['series_id' => $series->id, 'volume_number' => 3, 'status' => 'ready', 'title' => 'Overlord - Volume 03']);

    $response = $this->get("/library/series/{$series->slug}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Library/SeriesShow')
        ->has('books.data', 3)
        ->where('books.data.0.volume_number', 1)
        ->where('books.data.1.volume_number', 2)
        ->where('books.data.2.volume_number', 3));
});

it('excludes non-ready books from a series page', function () {
    $series = Series::create(['name' => 'Overlord Series']);
    Book::factory()->create(['series_id' => $series->id, 'volume_number' => 1, 'status' => 'ready']);
    Book::factory()->create(['series_id' => $series->id, 'volume_number' => 2, 'status' => 'ready']);
    Book::factory()->create(['series_id' => $series->id, 'volume_number' => 3, 'status' => 'ready']);
    $pending = Book::factory()->create(['series_id' => $series->id, 'volume_number' => 4, 'status' => 'pending', 'title' => 'Overlord - Volume 04']);

    $response = $this->get("/library/series/{$series->slug}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Library/SeriesShow')
        ->has('books.data', 3)
        ->where('books.data.0.volume_number', 1)
        ->where('books.data.1.volume_number', 2)
        ->where('books.data.2.volume_number', 3));
});

it('404s for an unknown series slug', function () {
    $this->get('/library/series/does-not-exist')->assertNotFound();
});

it('includes series info and other volumes on a book detail page', function () {
    $series = Series::create(['name' => 'Re:Zero Series']);
    $volume1 = Book::factory()->create(['series_id' => $series->id, 'volume_number' => 1, 'status' => 'ready', 'title' => 'Re:Zero - Volume 01']);
    $volume2 = Book::factory()->create(['series_id' => $series->id, 'volume_number' => 2, 'status' => 'ready', 'title' => 'Re:Zero - Volume 02']);

    $response = $this->get("/library/books/{$volume1->slug}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Library/BookDetail')
        ->where('book.series.name', 'Re:Zero Series')
        ->where('book.series.volume_number', 1)
        ->has('book.series.other_volumes', 1)
        ->where('book.series.other_volumes.0.slug', $volume2->slug));
});

it('omits series data entirely for a standalone book with no series', function () {
    $book = Book::factory()->create(['series_id' => null, 'status' => 'ready', 'title' => 'The Lonely Standalone Novel']);

    $response = $this->get("/library/books/{$book->slug}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Library/BookDetail')
        ->where('book.series', null));
});

it('matches series name in the public library search', function () {
    $series = Series::create(['name' => 'Guild Receptionist Series']);
    $book = Book::factory()->create(['series_id' => $series->id, 'volume_number' => 1, 'status' => 'ready', 'title' => 'Volume 01']);

    $response = $this->get('/library?search=Guild+Receptionist');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Library/Index')
        ->has('books.data', 1)
        ->where('books.data.0.slug', $book->slug));
});
