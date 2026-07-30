<?php

use App\Models\Author;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('only lists ready books on the public index, paginated 15 per page', function () {
    Book::factory()->count(3)->create(['status' => 'ready']);
    Book::factory()->count(2)->create(['status' => 'pending']);
    Book::factory()->count(1)->create(['status' => 'failed']);

    $response = $this->get('/library');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Library/Index')
        ->has('books.data', 3));
});

it('requires no authentication to view the public index', function () {
    $this->get('/library')->assertOk();
});

it('shows a ready book with its chapters in order', function () {
    $book = Book::factory()->create(['status' => 'ready', 'title' => 'A Public Book']);
    \App\Models\Chapter::factory()->create(['book_id' => $book->id, 'title' => 'Two', 'sort_order' => 1]);
    \App\Models\Chapter::factory()->create(['book_id' => $book->id, 'title' => 'One', 'sort_order' => 0]);

    $response = $this->get("/library/books/{$book->slug}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Library/BookDetail')
        ->where('book.title', 'A Public Book')
        ->where('book.chapters.0.title', 'One')
        ->where('book.chapters.1.title', 'Two'));
});

it('404s for a book that is not ready', function () {
    $book = Book::factory()->create(['status' => 'pending']);

    $this->get("/library/books/{$book->slug}")->assertNotFound();
});

it('404s for a nonexistent book slug', function () {
    $this->get('/library/books/does-not-exist')->assertNotFound();
});

it('shows a chapter with correct prev/next flags', function () {
    $book = Book::factory()->create(['status' => 'ready']);
    \App\Models\Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 0, 'title' => 'First']);
    \App\Models\Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 1, 'title' => 'Second']);
    \App\Models\Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 2, 'title' => 'Third']);

    $response = $this->get("/library/books/{$book->slug}/chapters/1");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Library/ChapterReader')
        ->where('chapter.title', 'Second')
        ->where('hasPrev', true)
        ->where('hasNext', true));
});

it('reports no prev on the first chapter and no next on the last', function () {
    $book = Book::factory()->create(['status' => 'ready']);
    \App\Models\Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 0]);
    \App\Models\Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 1]);

    $this->get("/library/books/{$book->slug}/chapters/0")
        ->assertInertia(fn ($page) => $page->where('hasPrev', false)->where('hasNext', true));

    $this->get("/library/books/{$book->slug}/chapters/1")
        ->assertInertia(fn ($page) => $page->where('hasPrev', true)->where('hasNext', false));
});

it('404s for a nonexistent chapter sort_order', function () {
    $book = Book::factory()->create(['status' => 'ready']);
    \App\Models\Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 0]);

    $this->get("/library/books/{$book->slug}/chapters/99")->assertNotFound();
});

it('shows an author with only their ready books', function () {
    $author = Author::factory()->create(['name' => 'Jane Doe']);
    Book::factory()->create(['author_id' => $author->id, 'status' => 'ready', 'title' => 'Ready Book']);
    Book::factory()->create(['author_id' => $author->id, 'status' => 'pending', 'title' => 'Pending Book']);

    $response = $this->get("/library/authors/{$author->slug}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Library/AuthorShow')
        ->where('author.name', 'Jane Doe')
        ->has('books.data', 1)
        ->where('books.data.0.title', 'Ready Book'));
});

it('404s for a nonexistent author slug', function () {
    $this->get('/library/authors/does-not-exist')->assertNotFound();
});
