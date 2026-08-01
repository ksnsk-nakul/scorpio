<?php

use App\Models\Book;
use App\Models\User;
use App\Services\EpubParsingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EpubFixtureBuilder;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
});

function bookFromFixture(string $fixturePath, string $userId): Book
{
    $relativePath = 'books/uploads/' . basename($fixturePath);
    Storage::disk('public')->put($relativePath, file_get_contents($fixturePath));
    unlink($fixturePath);

    return Book::create([
        'title' => 'placeholder',
        'source_epub_path' => $relativePath,
        'uploaded_by' => $userId,
    ]);
}

it('extracts metadata and creates chapters in spine order', function () {
    $fixture = EpubFixtureBuilder::build(
        bookTitle: 'The Dispossessed',
        author: 'Ursula K. Le Guin',
        chapters: [
            ['title' => 'Chapter One', 'body' => '<p>It begins.</p>'],
            ['title' => 'Chapter Two', 'body' => '<p>It continues.</p>'],
        ],
        description: 'A novel of anarchist utopia.',
    );
    $book = bookFromFixture($fixture, $this->user->id);

    (new EpubParsingService())->parse($book);

    expect($book->title)->toBe('The Dispossessed')
        ->and($book->description)->toBe('A novel of anarchist utopia.')
        ->and($book->language)->toBe('en')
        ->and($book->author->name)->toBe('Ursula K. Le Guin');

    $chapters = $book->chapters()->get();
    expect($chapters)->toHaveCount(2);
    expect($chapters[0]->title)->toBe('Chapter One')
        ->and($chapters[0]->content)->toContain('It begins.')
        ->and($chapters[0]->sort_order)->toBe(0);
    expect($chapters[1]->title)->toBe('Chapter Two')
        ->and($chapters[1]->sort_order)->toBe(1);
});

it('falls back to Chapter N when every chapter title matches the book title', function () {
    $fixture = EpubFixtureBuilder::build(
        bookTitle: 'Sample Book',
        author: 'Jane Doe',
        chapters: [
            ['title' => 'Sample Book', 'body' => '<p>First.</p>'],
            ['title' => 'Sample Book', 'body' => '<p>Second.</p>'],
            ['title' => 'Sample Book', 'body' => '<p>Third.</p>'],
        ],
    );
    $book = bookFromFixture($fixture, $this->user->id);

    (new EpubParsingService())->parse($book);

    $chapters = $book->chapters()->get();
    expect($chapters)->toHaveCount(3);
    expect($chapters[0]->title)->toBe('Chapter 1');
    expect($chapters[1]->title)->toBe('Chapter 2');
    expect($chapters[2]->title)->toBe('Chapter 3');
});

it('falls back to Chapter N when a chapter title is a prefix of the book title (series name minus volume)', function () {
    $fixture = EpubFixtureBuilder::build(
        bookTitle: 'Some Series, Vol. 5',
        author: 'Jane Doe',
        chapters: [
            ['title' => 'Some Series', 'body' => '<p>First.</p>'],
            ['title' => 'Some Series', 'body' => '<p>Second.</p>'],
        ],
    );
    $book = bookFromFixture($fixture, $this->user->id);

    (new EpubParsingService())->parse($book);

    $chapters = $book->chapters()->get();
    expect($chapters)->toHaveCount(2);
    expect($chapters[0]->title)->toBe('Chapter 1');
    expect($chapters[1]->title)->toBe('Chapter 2');
});

it('falls back to Chapter N when a chapter title is suspiciously short', function () {
    $fixture = EpubFixtureBuilder::build(
        bookTitle: 'Sample Book',
        author: 'Jane Doe',
        chapters: [
            ['title' => 'a', 'body' => '<p>First.</p>'],
        ],
    );
    $book = bookFromFixture($fixture, $this->user->id);

    (new EpubParsingService())->parse($book);

    $chapters = $book->chapters()->get();
    expect($chapters[0]->title)->toBe('Chapter 1');
});

it('keeps a genuinely distinct chapter title even when other chapters repeat the book title', function () {
    $fixture = EpubFixtureBuilder::build(
        bookTitle: 'Sample Book',
        author: 'Jane Doe',
        chapters: [
            ['title' => 'Sample Book', 'body' => '<p>First.</p>'],
            ['title' => 'Orbit Newsletter Signup', 'body' => '<p>Sign up here.</p>'],
        ],
    );
    $book = bookFromFixture($fixture, $this->user->id);

    (new EpubParsingService())->parse($book);

    $chapters = $book->chapters()->get();
    expect($chapters[0]->title)->toBe('Chapter 1');
    expect($chapters[1]->title)->toBe('Orbit Newsletter Signup');
});

it('reuses an existing author by case-insensitive name match', function () {
    \App\Models\Author::create(['name' => 'Ted Chiang']);

    $fixture = EpubFixtureBuilder::build(
        bookTitle: 'Exhalation',
        author: 'ted chiang',
        chapters: [['title' => 'Exhalation', 'body' => '<p>Text.</p>']],
    );
    $book = bookFromFixture($fixture, $this->user->id);

    (new EpubParsingService())->parse($book);

    expect(\App\Models\Author::count())->toBe(1);
});

it('throws when the spine has no readable chapters', function () {
    $fixture = EpubFixtureBuilder::build(
        bookTitle: 'Empty Book',
        author: 'Nobody',
        chapters: [],
    );
    $book = bookFromFixture($fixture, $this->user->id);

    (new EpubParsingService())->parse($book);
})->throws(RuntimeException::class);

it('throws when the archive is not a valid zip', function () {
    $book = Book::create([
        'title' => 'placeholder',
        'source_epub_path' => 'books/uploads/corrupt.epub',
        'uploaded_by' => $this->user->id,
    ]);
    Storage::disk('public')->put('books/uploads/corrupt.epub', 'not a zip file');

    (new EpubParsingService())->parse($book);
})->throws(RuntimeException::class);

it('extracts chapter images and rewrites their src to stored urls', function () {
    $fixture = EpubFixtureBuilder::build(
        bookTitle: 'Illustrated Tales',
        author: 'Jane Doe',
        chapters: [[
            'title' => 'Chapter One',
            'body' => '<p>Look:</p><img src="images/fig1.jpg" alt="Figure 1"/>',
            'images' => ['images/fig1.jpg' => 'fake jpg bytes'],
        ]],
    );
    $book = bookFromFixture($fixture, $this->user->id);

    (new EpubParsingService())->parse($book);

    $chapter = $book->chapters()->first();
    expect($chapter->content)->not->toContain('src="images/fig1.jpg"');
    expect($chapter->content)->toMatch('/src="[^"]*books\/' . $book->id . '\/images\/[^"]+"/');

    preg_match('/src="([^"]+)"/', $chapter->content, $m);
    $url = $m[1];
    $storedPath = parse_url($url, PHP_URL_PATH);
    $relativePath = preg_replace('#^.*?(books/)#', '$1', $storedPath);
    Storage::disk('public')->assertExists($relativePath);
    expect(Storage::disk('public')->get($relativePath))->toBe('fake jpg bytes');
});

it('resolves ../ image paths from a chapter nested in a subdirectory', function () {
    $fixture = EpubFixtureBuilder::build(
        bookTitle: 'Nested Structure',
        author: 'Jane Doe',
        chapters: [[
            'title' => 'Chapter One',
            'body' => '<p>Look:</p><img src="../images/fig1.jpg" alt="Figure 1"/>',
            'images' => ['../images/fig1.jpg' => 'nested fig bytes'],
            'path' => 'text/chapter1.xhtml',
        ]],
    );
    $book = bookFromFixture($fixture, $this->user->id);

    (new EpubParsingService())->parse($book);

    $chapter = $book->chapters()->first();
    expect($chapter->content)->not->toContain('src="../images/fig1.jpg"');
    expect($chapter->content)->toMatch('/src="[^"]*books\/' . $book->id . '\/images\/[^"]+"/');

    preg_match('/src="([^"]+)"/', $chapter->content, $m);
    $url = $m[1];
    $storedPath = parse_url($url, PHP_URL_PATH);
    $relativePath = preg_replace('#^.*?(books/)#', '$1', $storedPath);
    Storage::disk('public')->assertExists($relativePath);
    expect(Storage::disk('public')->get($relativePath))->toBe('nested fig bytes');
});

it('extracts the cover image declared via meta name=cover', function () {
    $fixture = EpubFixtureBuilder::build(
        bookTitle: 'Covered Book',
        author: 'Jane Doe',
        chapters: [['title' => 'One', 'body' => '<p>Text.</p>']],
        cover: ['ext' => 'jpg', 'bytes' => 'fake cover bytes'],
    );
    $book = bookFromFixture($fixture, $this->user->id);

    (new EpubParsingService())->parse($book);

    expect($book->cover_path)->toBe("books/{$book->id}/cover.jpg");
    Storage::disk('public')->assertExists("books/{$book->id}/cover.jpg");
    expect(Storage::disk('public')->get("books/{$book->id}/cover.jpg"))->toBe('fake cover bytes');
});

it('removes previously extracted images and cover before re-parsing (retry)', function () {
    $fixture = EpubFixtureBuilder::build(
        bookTitle: 'Illustrated Tales',
        author: 'Jane Doe',
        chapters: [[
            'title' => 'Chapter One',
            'body' => '<p>Look:</p><img src="images/fig1.jpg" alt="Figure 1"/>',
            'images' => ['images/fig1.jpg' => 'fake jpg bytes'],
        ]],
        cover: ['ext' => 'jpg', 'bytes' => 'fake cover bytes'],
    );
    $book = bookFromFixture($fixture, $this->user->id);

    (new EpubParsingService())->parse($book);
    $book->refresh();

    $imagesBefore = Storage::disk('public')->files("books/{$book->id}/images");
    $coverBefore = $book->cover_path;
    expect($imagesBefore)->toHaveCount(1)
        ->and($coverBefore)->toBe("books/{$book->id}/cover.jpg");

    // Simulate a retry: re-parse the same book against the same source file.
    (new EpubParsingService())->parse($book);
    $book->refresh();

    $imagesAfter = Storage::disk('public')->files("books/{$book->id}/images");
    expect($imagesAfter)->toHaveCount(1)
        ->and($imagesAfter)->not->toEqual($imagesBefore)
        ->and($book->cover_path)->toBe("books/{$book->id}/cover.jpg");

    Storage::disk('public')->assertMissing($imagesBefore[0]);
    Storage::disk('public')->assertExists($imagesAfter[0]);
    Storage::disk('public')->assertExists("books/{$book->id}/cover.jpg");
});

it('downgrades internal epub cross-reference links to plain text', function () {
    $fixture = EpubFixtureBuilder::build(
        bookTitle: 'Linked Tales',
        author: 'Jane Doe',
        chapters: [[
            'title' => 'Chapter One',
            'body' => '<p>See <a href="../Text/toc.xhtml#nav">the contents</a> and '
                . '<a href="chapter002.xhtml">chapter two</a>.</p>',
        ]],
    );
    $book = bookFromFixture($fixture, $this->user->id);

    (new EpubParsingService())->parse($book);

    $chapter = $book->chapters()->first();
    expect($chapter->content)->not->toContain('<a ')
        ->and($chapter->content)->toContain('<span>the contents</span>')
        ->and($chapter->content)->toContain('<span>chapter two</span>');
});

it('keeps external links and same-page fragment links intact', function () {
    $fixture = EpubFixtureBuilder::build(
        bookTitle: 'Linked Tales',
        author: 'Jane Doe',
        chapters: [[
            'title' => 'Chapter One',
            'body' => '<p>See <a href="https://example.com">this site</a>, '
                . 'email <a href="mailto:a@b.com">the author</a>, or jump to '
                . '<a href="#footnote1">the footnote</a>.</p><p id="footnote1">Here.</p>',
        ]],
    );
    $book = bookFromFixture($fixture, $this->user->id);

    (new EpubParsingService())->parse($book);

    $chapter = $book->chapters()->first();
    expect($chapter->content)->toContain('href="https://example.com"')
        ->and($chapter->content)->toContain('href="mailto:a@b.com"')
        ->and($chapter->content)->toContain('href="#footnote1"');
});

it('leaves cover_path null when no cover image exists in the manifest', function () {
    $fixture = EpubFixtureBuilder::build(
        bookTitle: 'No Cover Book',
        author: 'Jane Doe',
        chapters: [['title' => 'One', 'body' => '<p>Text.</p>']],
    );
    $book = bookFromFixture($fixture, $this->user->id);

    (new EpubParsingService())->parse($book);

    expect($book->cover_path)->toBeNull();
});
