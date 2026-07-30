<?php

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
});

it('generates a unique slug from the title on creation', function () {
    $book = Book::create([
        'title' => 'The Left Hand of Darkness',
        'source_epub_path' => 'books/uploads/x.epub',
        'uploaded_by' => $this->user->id,
    ]);
    expect($book->slug)->toBe('the-left-hand-of-darkness');
});

it('appends a numeric suffix when the slug collides', function () {
    Book::create(['title' => 'Dune', 'source_epub_path' => 'a.epub', 'uploaded_by' => $this->user->id]);
    $second = Book::create(['title' => 'Dune', 'source_epub_path' => 'b.epub', 'uploaded_by' => $this->user->id]);
    expect($second->slug)->toBe('dune-2');
});

it('defaults status to pending', function () {
    $book = Book::create(['title' => 'Foundation', 'source_epub_path' => 'a.epub', 'uploaded_by' => $this->user->id]);
    expect($book->status)->toBe('pending')
        ->and($book->isProcessing())->toBeTrue()
        ->and($book->isReady())->toBeFalse()
        ->and($book->isFailed())->toBeFalse();
});

it('exposes a cover url only when cover_path is set', function () {
    $book = Book::factory()->create(['cover_path' => null]);
    expect($book->cover_url)->toBeNull();

    $book->update(['cover_path' => "books/{$book->id}/cover.jpg"]);
    expect($book->fresh()->cover_url)->toContain("books/{$book->id}/cover.jpg");
});
