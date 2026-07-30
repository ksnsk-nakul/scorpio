# E-Library Backend Ingestion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the backend ingestion pipeline for the e-library — `Author`/`Book`/`Chapter` models, a hand-rolled `EpubParsingService` (ZipArchive + SimpleXML/DOMDocument, no third-party EPUB package), a queued `ParseEpubBookJob`, and the upload/status/retry endpoints — fully testable via Pest with no UI required.

**Architecture:** One EPUB file per upload request creates a `Book` row (`status: pending`) immediately, using the filename as a placeholder title so a slug exists right away, then dispatches `ParseEpubBookJob`. The job runs `EpubParsingService`, which extracts Dublin Core metadata, walks the OPF manifest/spine to pull chapters in reading order, extracts and rewrites embedded images, and extracts a cover — all stored directly to the `public` disk under `books/{book_id}/...` (no `Media` model involvement, matching the spec's rationale). On success the book's `status` becomes `ready`; on any exception, `failed` with a reason, retryable from the stored original file.

**Tech Stack:** Laravel 13 (ZipArchive, SimpleXMLElement, DOMDocument, queued jobs, Pest), PHP built-ins only for EPUB parsing — no new Composer dependency.

**Reference spec:** `docs/superpowers/specs/2026-07-30-e-library-design.md`

---

## File Map

**New**
- `database/migrations/2026_07_30_100001_create_authors_table.php`
- `database/migrations/2026_07_30_100002_create_books_table.php`
- `database/migrations/2026_07_30_100003_create_chapters_table.php`
- `app/Models/Author.php`
- `app/Models/Book.php`
- `app/Models/Chapter.php`
- `database/factories/AuthorFactory.php`
- `database/factories/BookFactory.php`
- `database/factories/ChapterFactory.php`
- `app/Services/EpubParsingService.php`
- `app/Jobs/ParseEpubBookJob.php`
- `app/Http/Controllers/Admin/BookController.php`
- `tests/Unit/AuthorTest.php`
- `tests/Unit/BookTest.php`
- `tests/Feature/EpubParsingServiceTest.php`
- `tests/Feature/ParseEpubBookJobTest.php`
- `tests/Feature/BookUploadTest.php`
- `tests/support/EpubFixtureBuilder.php`

**Modified**
- `routes/web.php` — book upload/status/retry routes

---

## Task 1: Migrations — `authors`, `books`, `chapters`

**Files:**
- Create: `database/migrations/2026_07_30_100001_create_authors_table.php`
- Create: `database/migrations/2026_07_30_100002_create_books_table.php`
- Create: `database/migrations/2026_07_30_100003_create_chapters_table.php`

- [ ] **Step 1: Write the authors migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('bio')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
};
```

- [ ] **Step 2: Write the books migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('language')->nullable();
            $table->string('publisher')->nullable();
            $table->date('published_date')->nullable();
            $table->string('subject')->nullable();
            $table->string('source_epub_path');
            $table->string('status')->default('pending');
            $table->string('status_reason')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
```

- [ ] **Step 3: Write the chapters migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->longText('content');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['book_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapters');
    }
};
```

- [ ] **Step 4: Run the migrations**

Run: `php artisan migrate`
Expected: all three `... DONE`

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_07_30_100001_create_authors_table.php \
        database/migrations/2026_07_30_100002_create_books_table.php \
        database/migrations/2026_07_30_100003_create_chapters_table.php
git commit -m "feat: add authors/books/chapters migrations for e-library"
```

---

## Task 2: `Author` model + factory

**Files:**
- Create: `app/Models/Author.php`
- Create: `database/factories/AuthorFactory.php`
- Test: `tests/Unit/AuthorTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Author;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a unique slug from the name on creation', function () {
    $a = Author::create(['name' => 'Ursula K. Le Guin']);
    expect($a->slug)->toBe('ursula-k-le-guin');
});

it('appends a numeric suffix when the slug collides', function () {
    Author::create(['name' => 'Frank Herbert']);
    $second = Author::create(['name' => 'Frank Herbert']);
    expect($second->slug)->toBe('frank-herbert-2');
});

it('finds an existing author by case-insensitive exact name match', function () {
    $original = Author::create(['name' => 'Isaac Asimov']);
    $found = Author::findOrCreateByName('isaac asimov');
    expect($found->id)->toBe($original->id);
    expect(Author::count())->toBe(1);
});

it('creates a new author when no case-insensitive match exists', function () {
    Author::findOrCreateByName('Octavia E. Butler');
    expect(Author::count())->toBe(1);
    expect(Author::first()->name)->toBe('Octavia E. Butler');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/AuthorTest.php`
Expected: FAIL — class `App\Models\Author` not found

- [ ] **Step 3: Implement the `Author` model**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Author extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'bio'];

    protected static function booted(): void
    {
        static::creating(function (Author $author) {
            if (! $author->slug) {
                $author->slug = static::uniqueSlug($author->name);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($name) ?: 'author';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public static function findOrCreateByName(string $name): self
    {
        $name = trim($name);
        $existing = static::whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();

        return $existing ?: static::create(['name' => $name]);
    }

    public function books(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Book::class);
    }
}
```

- [ ] **Step 4: Create the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Author;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuthorFactory extends Factory
{
    protected $model = Author::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'bio' => null,
        ];
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Unit/AuthorTest.php`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Models/Author.php database/factories/AuthorFactory.php tests/Unit/AuthorTest.php
git commit -m "feat: add Author model with slug generation and name matching"
```

---

## Task 3: `Book` model + factory

**Files:**
- Create: `app/Models/Book.php`
- Create: `database/factories/BookFactory.php`
- Test: `tests/Unit/BookTest.php`

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/BookTest.php`
Expected: FAIL — class `App\Models\Book` not found

- [ ] **Step 3: Implement the `Book` model**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'author_id', 'title', 'slug', 'description', 'cover_path',
        'language', 'publisher', 'published_date', 'subject',
        'source_epub_path', 'status', 'status_reason', 'uploaded_by',
    ];

    protected $casts = [
        'published_date' => 'date',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::creating(function (Book $book) {
            if (! $book->slug) {
                $book->slug = static::uniqueSlug($book->title);
            }
        });
    }

    public static function uniqueSlug(string $title, ?int $excludeId = null): string
    {
        $base = Str::slug($title) ?: 'book';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function author(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function chapters(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('sort_order');
    }

    public function uploader(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }
}
```

- [ ] **Step 4: Create the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BookFactory extends Factory
{
    protected $model = Book::class;

    public function definition(): array
    {
        return [
            'author_id' => Author::factory(),
            'title' => $this->faker->sentence(3),
            'source_epub_path' => 'books/uploads/' . Str::uuid() . '.epub',
            'status' => 'ready',
            'uploaded_by' => User::factory(),
        ];
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Unit/BookTest.php`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Models/Book.php database/factories/BookFactory.php tests/Unit/BookTest.php
git commit -m "feat: add Book model with status lifecycle and slug generation"
```

---

## Task 4: `Chapter` model + factory

**Files:**
- Create: `app/Models/Chapter.php`
- Create: `database/factories/ChapterFactory.php`

No dedicated unit test — `Chapter` is a plain data record with no branching logic beyond the relationship, which is exercised by `Book::chapters()` and the parsing-service tests in later tasks.

- [ ] **Step 1: Implement the `Chapter` model**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chapter extends Model
{
    use HasFactory;

    protected $fillable = ['book_id', 'title', 'content', 'sort_order'];

    public function book(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
```

- [ ] **Step 2: Create the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChapterFactory extends Factory
{
    protected $model = Chapter::class;

    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'title' => $this->faker->sentence(2),
            'content' => '<p>' . $this->faker->paragraph() . '</p>',
            'sort_order' => 0,
        ];
    }
}
```

- [ ] **Step 3: Verify via tinker**

Run: `php artisan tinker --execute="echo App\Models\Chapter::factory()->make()->content;"`
Expected: prints a `<p>...</p>` string with no errors

- [ ] **Step 4: Commit**

```bash
git add app/Models/Chapter.php database/factories/ChapterFactory.php
git commit -m "feat: add Chapter model"
```

---

## Task 5: EPUB test fixture builder

**Files:**
- Create: `tests/support/EpubFixtureBuilder.php`

Every later parsing test needs real, valid EPUB zip files to parse — not mocks, since the whole point of `EpubParsingService` is real zip/XML handling. This helper builds one on demand.

- [ ] **Step 1: Write the fixture builder**

```php
<?php

namespace Tests\Support;

use ZipArchive;

class EpubFixtureBuilder
{
    /**
     * @param array<int, array{title: string, body: string, images?: array<string, string>}> $chapters
     *   Each chapter's 'images' maps a relative src (e.g. "images/fig1.jpg") to raw image bytes.
     * @param array{ext: string, bytes: string}|null $cover
     */
    public static function build(
        string $bookTitle,
        string $author,
        array $chapters,
        ?array $cover = null,
        ?string $description = null,
        string $language = 'en',
    ): string {
        $path = tempnam(sys_get_temp_dir(), 'epub_fixture_') . '.epub';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);

        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->addFromString('META-INF/container.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <rootfiles>
    <rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);

        $manifestItems = [];
        $spineItems = [];

        foreach ($chapters as $i => $chapter) {
            $n = $i + 1;
            $chapterHtml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n"
                . "<head><title>{$chapter['title']}</title></head>\n"
                . "<body>{$chapter['body']}</body>\n"
                . "</html>";
            $zip->addFromString("OEBPS/chapter{$n}.xhtml", $chapterHtml);
            $manifestItems[] = "<item id=\"chap{$n}\" href=\"chapter{$n}.xhtml\" media-type=\"application/xhtml+xml\"/>";
            $spineItems[] = "<itemref idref=\"chap{$n}\"/>";

            foreach ($chapter['images'] ?? [] as $relativeSrc => $bytes) {
                $zip->addFromString("OEBPS/{$relativeSrc}", $bytes);
                $imgId = 'img_' . preg_replace('/[^a-zA-Z0-9]/', '_', $relativeSrc);
                $ext = strtolower(pathinfo($relativeSrc, PATHINFO_EXTENSION));
                $manifestItems[] = "<item id=\"{$imgId}\" href=\"{$relativeSrc}\" media-type=\"image/{$ext}\"/>";
            }
        }

        $coverMeta = '';
        if ($cover) {
            $zip->addFromString("OEBPS/cover.{$cover['ext']}", $cover['bytes']);
            $manifestItems[] = "<item id=\"cover-image\" href=\"cover.{$cover['ext']}\" media-type=\"image/{$cover['ext']}\" properties=\"cover-image\"/>";
            $coverMeta = '<meta name="cover" content="cover-image"/>';
        }

        $manifestXml = implode("\n    ", $manifestItems);
        $spineXml = implode("\n    ", $spineItems);
        $descriptionTag = $description ? "<dc:description>{$description}</dc:description>" : '';

        $opf = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf" version="2.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:title>{$bookTitle}</dc:title>
    <dc:creator>{$author}</dc:creator>
    <dc:language>{$language}</dc:language>
    {$descriptionTag}
    {$coverMeta}
  </metadata>
  <manifest>
    {$manifestXml}
  </manifest>
  <spine>
    {$spineXml}
  </spine>
</package>
XML;

        $zip->addFromString('OEBPS/content.opf', $opf);
        $zip->close();

        return $path;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add tests/support/EpubFixtureBuilder.php
git commit -m "test: add EPUB fixture builder for parsing tests"
```

---

## Task 6: `EpubParsingService` — metadata, manifest, spine, chapters

**Files:**
- Create: `app/Services/EpubParsingService.php`
- Test: `tests/Feature/EpubParsingServiceTest.php`

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/EpubParsingServiceTest.php`
Expected: FAIL — class `App\Services\EpubParsingService` not found

- [ ] **Step 3: Implement metadata/manifest/spine/chapter extraction**

```php
<?php
namespace App\Services;

use App\Models\Author;
use App\Models\Book;
use App\Models\Chapter;
use DOMDocument;
use DOMNode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class EpubParsingService
{
    private const OPF_NS = 'http://www.idpf.org/2007/opf';
    private const DC_NS = 'http://purl.org/dc/elements/1.1/';
    private const CONTAINER_NS = 'urn:oasis:names:tc:opendocument:xmlns:container';

    public function parse(Book $book): void
    {
        // Book always stores its source file on the public disk — unlike
        // Media, there's no per-record configurable disk.
        $sourcePath = Storage::disk('public')->path($book->source_epub_path);

        $zip = new ZipArchive();
        if ($zip->open($sourcePath) !== true) {
            throw new RuntimeException('Unable to open EPUB archive.');
        }

        $opfPath = $this->locateOpf($zip);
        $opfDir = dirname($opfPath);
        $opfDir = ($opfDir === '.' || $opfDir === '') ? '' : $opfDir . '/';

        $opf = $this->readXml($zip, $opfPath);

        $metadata = $this->extractMetadata($opf);
        $manifest = $this->extractManifest($opf);
        $spineIds = $this->extractSpine($opf);

        $book->chapters()->delete();

        if (! empty($metadata['title'])) {
            $book->title = $metadata['title'];
            $book->slug = Book::uniqueSlug($metadata['title'], $book->id);
        }
        $book->description = $metadata['description'] ?? null;
        $book->language = $metadata['language'] ?? null;
        $book->publisher = $metadata['publisher'] ?? null;
        $book->published_date = $this->parseDate($metadata['date'] ?? null);
        $book->subject = $metadata['subject'] ?? null;
        $book->author_id = Author::findOrCreateByName($metadata['creator'] ?? 'Unknown')->id;

        $sortOrder = 0;
        foreach ($spineIds as $idref) {
            if (! isset($manifest[$idref])) {
                continue;
            }
            $item = $manifest[$idref];
            if (! str_contains($item['media_type'], 'html')) {
                continue;
            }

            $chapterPath = $opfDir . $item['href'];
            $html = $zip->getFromName($chapterPath);
            if ($html === false) {
                continue;
            }

            [$content, $title] = $this->processChapterHtml($html, $zip, $chapterPath, $book, $sortOrder);

            Chapter::create([
                'book_id' => $book->id,
                'title' => $title,
                'content' => $content,
                'sort_order' => $sortOrder,
            ]);

            $sortOrder++;
        }

        if ($sortOrder === 0) {
            $zip->close();
            throw new RuntimeException('No readable chapters found in the EPUB spine.');
        }

        $this->extractCover($zip, $opf, $manifest, $opfDir, $book);

        $zip->close();
        $book->save();
    }

    private function locateOpf(ZipArchive $zip): string
    {
        $containerXml = $zip->getFromName('META-INF/container.xml');
        if ($containerXml === false) {
            throw new RuntimeException('Missing META-INF/container.xml.');
        }

        $xml = new SimpleXMLElement($containerXml);
        $xml->registerXPathNamespace('c', self::CONTAINER_NS);
        $rootfiles = $xml->xpath('//c:rootfile');

        if (empty($rootfiles)) {
            throw new RuntimeException('No rootfile declared in container.xml.');
        }

        return (string) $rootfiles[0]['full-path'];
    }

    private function readXml(ZipArchive $zip, string $path): SimpleXMLElement
    {
        $content = $zip->getFromName($path);
        if ($content === false) {
            throw new RuntimeException("Missing file in archive: {$path}");
        }

        return new SimpleXMLElement($content);
    }

    private function extractMetadata(SimpleXMLElement $opf): array
    {
        $opf->registerXPathNamespace('dc', self::DC_NS);

        $first = fn (string $tag) => (string) ($opf->xpath("//dc:{$tag}")[0] ?? '') ?: null;
        $subjects = array_map(fn ($n) => (string) $n, $opf->xpath('//dc:subject') ?: []);

        return [
            'title' => $first('title'),
            'creator' => $first('creator'),
            'description' => $first('description'),
            'language' => $first('language'),
            'publisher' => $first('publisher'),
            'date' => $first('date'),
            'subject' => $subjects ? implode(', ', $subjects) : null,
        ];
    }

    private function extractManifest(SimpleXMLElement $opf): array
    {
        $opf->registerXPathNamespace('opf', self::OPF_NS);
        $manifest = [];

        foreach ($opf->xpath('//opf:manifest/opf:item') as $item) {
            $manifest[(string) $item['id']] = [
                'href' => (string) $item['href'],
                'media_type' => (string) $item['media-type'],
                'properties' => (string) $item['properties'],
            ];
        }

        return $manifest;
    }

    private function extractSpine(SimpleXMLElement $opf): array
    {
        $opf->registerXPathNamespace('opf', self::OPF_NS);
        $ids = [];

        foreach ($opf->xpath('//opf:spine/opf:itemref') as $itemref) {
            $ids[] = (string) $itemref['idref'];
        }

        return $ids;
    }

    private function parseDate(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    // processChapterHtml() and extractCover() are added in Tasks 7 and 8.
    private function processChapterHtml(string $html, ZipArchive $zip, string $chapterPath, Book $book, int $sortOrder): array
    {
        $title = null;
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $m)) {
            $title = trim(strip_tags($m[1])) ?: null;
        }
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
            $content = trim($m[1]);
        } else {
            $content = $html;
        }

        return [$content, $title];
    }

    private function extractCover(ZipArchive $zip, SimpleXMLElement $opf, array $manifest, string $opfDir, Book $book): void
    {
        // Implemented fully in Task 8.
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/EpubParsingServiceTest.php`
Expected: PASS (4 tests) — the placeholder `processChapterHtml`/`extractCover` are enough to satisfy this task's assertions (image rewriting and cover extraction are tested in the next two tasks)

- [ ] **Step 5: Commit**

```bash
git add app/Services/EpubParsingService.php tests/Feature/EpubParsingServiceTest.php
git commit -m "feat: parse EPUB metadata, manifest, spine, and chapters"
```

---

## Task 7: `EpubParsingService` — image extraction and rewriting

**Files:**
- Modify: `app/Services/EpubParsingService.php`
- Modify: `tests/Feature/EpubParsingServiceTest.php`

- [ ] **Step 1: Add the failing test**

Append to `tests/Feature/EpubParsingServiceTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/EpubParsingServiceTest.php`
Expected: FAIL — image `src` is unchanged since `processChapterHtml` doesn't process images yet

- [ ] **Step 3: Implement image extraction and path resolution**

Replace `processChapterHtml()` in `app/Services/EpubParsingService.php` and add `resolvePath()`:

```php
    private function processChapterHtml(string $html, ZipArchive $zip, string $chapterPath, Book $book, int $sortOrder): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $title = null;
        $titleNodes = $doc->getElementsByTagName('title');
        if ($titleNodes->length > 0) {
            $title = trim($titleNodes->item(0)->textContent) ?: null;
        }

        $chapterDir = dirname($chapterPath);
        $imageIndex = 0;

        foreach (iterator_to_array($doc->getElementsByTagName('img')) as $img) {
            $src = $img->getAttribute('src');
            if (! $src) {
                continue;
            }

            $resolvedPath = $this->resolvePath($chapterDir, $src);
            $bytes = $zip->getFromName($resolvedPath);
            if ($bytes === false) {
                continue;
            }

            $ext = strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION)) ?: 'jpg';
            $filename = 'img-' . (++$imageIndex) . '-' . uniqid() . '.' . $ext;
            Storage::disk('public')->put("books/{$book->id}/images/{$filename}", $bytes);
            $img->setAttribute('src', Storage::disk('public')->url("books/{$book->id}/images/{$filename}"));
        }

        $body = $doc->getElementsByTagName('body')->item(0);
        $content = $body ? $this->innerHtml($body) : $html;

        return [$content, $title];
    }

    private function innerHtml(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }

        return $html;
    }

    private function resolvePath(string $baseDir, string $relative): string
    {
        $combined = ($baseDir === '.' || $baseDir === '') ? $relative : $baseDir . '/' . $relative;
        $parts = [];

        foreach (explode('/', $combined) as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            }
            if ($segment === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $segment;
        }

        return implode('/', $parts);
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/EpubParsingServiceTest.php`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/EpubParsingService.php tests/Feature/EpubParsingServiceTest.php
git commit -m "feat: extract and rewrite chapter images during EPUB parsing"
```

---

## Task 8: `EpubParsingService` — cover extraction

**Files:**
- Modify: `app/Services/EpubParsingService.php`
- Modify: `tests/Feature/EpubParsingServiceTest.php`

- [ ] **Step 1: Add the failing tests**

Append to `tests/Feature/EpubParsingServiceTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/EpubParsingServiceTest.php`
Expected: FAIL — `extractCover()` is currently a no-op

- [ ] **Step 3: Implement cover extraction**

Replace the `extractCover()` stub in `app/Services/EpubParsingService.php`:

```php
    private function extractCover(ZipArchive $zip, SimpleXMLElement $opf, array $manifest, string $opfDir, Book $book): void
    {
        $opf->registerXPathNamespace('opf', self::OPF_NS);

        $coverId = null;
        $metaCover = $opf->xpath('//opf:metadata/opf:meta[@name="cover"]');
        if (! empty($metaCover)) {
            $coverId = (string) $metaCover[0]['content'];
        }

        $coverItem = null;
        if ($coverId && isset($manifest[$coverId])) {
            $coverItem = $manifest[$coverId];
        } else {
            foreach ($manifest as $item) {
                if (str_contains($item['properties'], 'cover-image')) {
                    $coverItem = $item;
                    break;
                }
            }
        }

        if (! $coverItem) {
            foreach ($manifest as $item) {
                if (str_starts_with($item['media_type'], 'image/')) {
                    $coverItem = $item;
                    break;
                }
            }
        }

        if (! $coverItem) {
            return;
        }

        $coverPath = $opfDir . $coverItem['href'];
        $bytes = $zip->getFromName($coverPath);
        if ($bytes === false) {
            return;
        }

        $ext = strtolower(pathinfo($coverPath, PATHINFO_EXTENSION)) ?: 'jpg';
        Storage::disk('public')->put("books/{$book->id}/cover.{$ext}", $bytes);
        $book->cover_path = "books/{$book->id}/cover.{$ext}";
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/EpubParsingServiceTest.php`
Expected: PASS (7 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/EpubParsingService.php tests/Feature/EpubParsingServiceTest.php
git commit -m "feat: extract EPUB cover image during parsing"
```

---

## Task 9: `ParseEpubBookJob`

**Files:**
- Create: `app/Jobs/ParseEpubBookJob.php`
- Test: `tests/Feature/ParseEpubBookJobTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Jobs\ParseEpubBookJob;
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ParseEpubBookJobTest.php`
Expected: FAIL — class `App\Jobs\ParseEpubBookJob` not found

- [ ] **Step 3: Implement the job**

```php
<?php
namespace App\Jobs;

use App\Models\Book;
use App\Services\EpubParsingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ParseEpubBookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Book $book) {}

    public function handle(EpubParsingService $parser): void
    {
        $this->book->update(['status' => 'processing']);
        $parser->parse($this->book);
        $this->book->status = 'ready';
        $this->book->save();
    }

    public function failed(Throwable $e): void
    {
        $this->book->update(['status' => 'failed', 'status_reason' => $e->getMessage()]);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ParseEpubBookJobTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/ParseEpubBookJob.php tests/Feature/ParseEpubBookJobTest.php
git commit -m "feat: add ParseEpubBookJob with status lifecycle"
```

---

## Task 10: Upload, status, and retry endpoints

**Files:**
- Create: `app/Http/Controllers/Admin/BookController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/BookUploadTest.php`

- [ ] **Step 1: Write the failing test**

```php
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
    expect(Book::count())->toBe(1);
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

    $this->actingAs($this->admin)
        ->postJson("/admin/library/books/{$book->id}/retry")
        ->assertOk()
        ->assertJsonPath('status', 'pending');

    Queue::assertPushed(\App\Jobs\ParseEpubBookJob::class);
    expect($book->fresh()->status_reason)->toBeNull();
});

it('rejects retrying a book that is not failed', function () {
    $book = Book::factory()->create(['status' => 'ready', 'uploaded_by' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->postJson("/admin/library/books/{$book->id}/retry")
        ->assertStatus(422);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/BookUploadTest.php`
Expected: FAIL — 404s, `BookController` doesn't exist

- [ ] **Step 3: Implement `BookController`**

```php
<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ParseEpubBookJob;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BookController extends Controller
{
    private const ALLOWED_EPUB_MIMES = ['application/epub+zip', 'application/zip', 'application/octet-stream'];

    public function store(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|max:102400']);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();

        if ($ext !== 'epub' || ! in_array($mime, self::ALLOWED_EPUB_MIMES, true)) {
            return response()->json(['message' => 'File must be a valid .epub file.'], 422);
        }

        $path = $file->storeAs('books/uploads', Str::uuid() . '.epub', 'public');

        $book = Book::create([
            'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'source_epub_path' => $path,
            'uploaded_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        ParseEpubBookJob::dispatch($book);

        return response()->json([
            'id' => $book->id,
            'title' => $book->title,
            'slug' => $book->slug,
            'status' => $book->status,
        ]);
    }

    public function status(Book $book): JsonResponse
    {
        return response()->json([
            'id' => $book->id,
            'title' => $book->title,
            'slug' => $book->slug,
            'status' => $book->status,
            'status_reason' => $book->status_reason,
        ]);
    }

    public function retry(Book $book): JsonResponse
    {
        abort_unless($book->isFailed(), 422, 'Only failed books can be retried.');

        $book->update(['status' => 'pending', 'status_reason' => null]);
        ParseEpubBookJob::dispatch($book);

        return response()->json(['id' => $book->id, 'status' => $book->status]);
    }
}
```

- [ ] **Step 4: Add the routes**

In `routes/web.php`, add near the existing `MediaController` route group:

```php
use App\Http\Controllers\Admin\BookController;

Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::post('library/books', [BookController::class, 'store'])->name('library.books.store');
        Route::post('library/books/{book}/retry', [BookController::class, 'retry'])->name('library.books.retry');
    });

Route::middleware(['auth', 'role:admin,editor,viewer'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('library/books/{book}/status', [BookController::class, 'status'])->name('library.books.status');
    });
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/BookUploadTest.php`
Expected: PASS (6 tests)

- [ ] **Step 6: Run the full backend suite**

Run: `php artisan test`
Expected: all green except the pre-existing unrelated `ExampleTest` failure

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/BookController.php routes/web.php tests/Feature/BookUploadTest.php
git commit -m "feat: add book upload/status/retry endpoints"
```

---

## Out of scope (tracked in the spec, future plans)

- Admin management UI (list, manual edit, bulk-upload dropzone) — Plan 2
- Public library browsing/reading UI — Plan 3
- RAG chat over the library — separate spec, depends on this plan's content existing
