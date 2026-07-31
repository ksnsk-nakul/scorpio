# Library RAG Chat Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the "ask your library" admin chat per `docs/superpowers/specs/2026-07-30-library-rag-chat-design.md` — index ready books into pgvector-backed chunks, retrieve relevant excerpts by cosine similarity, and generate cited answers via Gemini.

**Architecture:** A named `rag` Postgres (Supabase) connection holds `book_chunks`/`chat_threads`/`chat_messages`, separate from the app's primary SQLite database (no cross-DB FKs). A hand-rolled `GeminiClient` service wraps the two HTTP endpoints needed (embed, generate) — no new Composer dependency, matching this app's established pattern (EPUB parsing, LibreOffice/unrar shelling out are all hand-rolled rather than pulling in packages). `IndexBookChunksJob` runs when a book becomes `ready`. `RetrievalService` does the pgvector similarity query. `ChatService` assembles the prompt and persists the exchange. An admin-only Inertia page provides the chat UI.

**Tech Stack:** Laravel 13, `rag` named Postgres connection (pgvector extension), Gemini REST API via Laravel's `Http` facade, Pest (`DatabaseTransactions` for the `rag` connection, `Http::fake()` for all Gemini calls), Vue 3 + Inertia.js v2 for the admin UI.

**Environment (already verified working during planning — do not re-verify, just use):**
- `rag` connection added to `config/database.php` (driver `pgsql`, reading `DB_RAG_*` env vars), pointed at Supabase.
- `.env` already has `DB_RAG_HOST`/`DB_RAG_PORT`/`DB_RAG_DATABASE`/`DB_RAG_USERNAME`/`DB_RAG_PASSWORD` and `GEMINI_API_KEY` set.
- The `vector` Postgres extension is enabled on the `rag` database (`CREATE EXTENSION vector` already run, version 0.8.0).
- Gemini API key confirmed working. Embedding model is `gemini-embedding-001` with `outputDimensionality: 768` (confirmed via a live test call — 768 dimensions is well within pgvector's indexable range, unlike the model's 3072-dim default). Generation model is `gemini-2.5-flash`.

**Test isolation (discovered and fixed during Task 4's review):** the `rag` Postgres connection points at the same real Supabase database in both the app and the test suite — `phpunit.xml` only overrides the default SQLite connection, not `DB_RAG_*`. Since `Book::factory()` creates books with small auto-incrementing IDs (1, 2, 3…) in the test's isolated in-memory SQLite DB, and those same small integers are used as `book_id` when writing to the shared `rag.book_chunks` table, running the test suite was silently deleting and overwriting real production RAG data for low book IDs. Fixed by adding a `rag_test` Postgres **schema** (not a separate database — same Supabase instance) mirroring all three tables, and setting `DB_RAG_SEARCH_PATH=rag_test,public` in `phpunit.xml` (tests need `public` on the path too, since pgvector's `vector` type itself lives in the `public` schema). This schema was created manually via `php artisan tinker` as a one-time setup step, not via a tracked migration — a fresh clone of this repo needs that one-time step re-run before the RAG test suite will pass (mirror the 3 production migrations' `CREATE TABLE`/`CREATE INDEX` statements, prefixed with `rag_test.`, plus `CREATE SCHEMA IF NOT EXISTS rag_test`).

**Design decision (resolved during planning, not fully pinned down in the spec): citation granularity.** The spec says citations record "which chunks were used" but getting an LLM to reliably self-report which specific chunks it drew from requires structured-output prompting significantly beyond a plain `Http`-client text completion. This plan instead records citations as the deduplicated `{book_id, chapter_id, book_title, chapter_title}` set of **every chunk retrieved and fed into the prompt** for that question — a deterministic, always-accurate approximation (matches the spec's stored shape exactly) rather than an LLM self-report that could hallucinate its own citation list.

---

### Task 1: `rag` connection, migrations, and models

**Files:**
- Modify: `config/database.php` (already done during planning — verify it's present, don't re-add)
- Create: `database/migrations/2026_07_31_100001_create_book_chunks_table.php`
- Create: `database/migrations/2026_07_31_100002_create_chat_threads_table.php`
- Create: `database/migrations/2026_07_31_100003_create_chat_messages_table.php`
- Create: `app/Models/BookChunk.php`
- Create: `app/Models/ChatThread.php`
- Create: `app/Models/ChatMessage.php`
- Test: `tests/Feature/Rag/RagSchemaTest.php`

- [ ] **Step 1: Verify the `rag` connection exists**

Run: `grep -A12 "'rag' =>" config/database.php`
Expected: a `pgsql`-driver block reading `DB_RAG_*` env vars. If it's missing, add it to `config/database.php` right before the `'sqlsrv'` entry:

```php
        'rag' => [
            'driver' => 'pgsql',
            'host' => env('DB_RAG_HOST', '127.0.0.1'),
            'port' => env('DB_RAG_PORT', '5432'),
            'database' => env('DB_RAG_DATABASE', 'postgres'),
            'username' => env('DB_RAG_USERNAME', 'postgres'),
            'password' => env('DB_RAG_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'require'),
        ],
```

- [ ] **Step 2: Write the failing schema test**

```php
<?php
// tests/Feature/Rag/RagSchemaTest.php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates the book_chunks, chat_threads, and chat_messages tables on the rag connection', function () {
    expect(Schema::connection('rag')->hasTable('book_chunks'))->toBeTrue();
    expect(Schema::connection('rag')->hasTable('chat_threads'))->toBeTrue();
    expect(Schema::connection('rag')->hasTable('chat_messages'))->toBeTrue();

    expect(Schema::connection('rag')->hasColumns('book_chunks', [
        'id', 'book_id', 'chapter_id', 'chunk_index', 'content', 'embedding', 'created_at',
    ]))->toBeTrue();

    expect(Schema::connection('rag')->hasColumns('chat_messages', [
        'id', 'thread_id', 'role', 'content', 'citations', 'created_at',
    ]))->toBeTrue();
});

it('can insert and query a pgvector embedding column directly', function () {
    $vector = '[' . implode(',', array_fill(0, 768, 0.01)) . ']';

    DB::connection('rag')->statement(
        "insert into book_chunks (book_id, chapter_id, chunk_index, content, embedding) values (?, ?, ?, ?, ?::vector)",
        [999999, 999999, 0, 'test chunk', $vector]
    );

    $row = DB::connection('rag')->selectOne(
        "select content from book_chunks where book_id = ? order by embedding <=> ?::vector limit 1",
        [999999, $vector]
    );

    expect($row->content)->toBe('test chunk');

    DB::connection('rag')->table('book_chunks')->where('book_id', 999999)->delete();
});
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --filter=RagSchemaTest`
Expected: FAIL — tables don't exist yet.

- [ ] **Step 4: Write the migrations**

```php
<?php
// database/migrations/2026_07_31_100001_create_book_chunks_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('rag')->statement('CREATE EXTENSION IF NOT EXISTS vector');

        DB::connection('rag')->statement(<<<SQL
            CREATE TABLE book_chunks (
                id BIGSERIAL PRIMARY KEY,
                book_id BIGINT NOT NULL,
                chapter_id BIGINT NOT NULL,
                chunk_index INTEGER NOT NULL,
                content TEXT NOT NULL,
                embedding VECTOR(768) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT now()
            )
        SQL);

        DB::connection('rag')->statement('CREATE INDEX book_chunks_book_id_index ON book_chunks (book_id)');
    }

    public function down(): void
    {
        DB::connection('rag')->statement('DROP TABLE IF EXISTS book_chunks');
    }
};
```

```php
<?php
// database/migrations/2026_07_31_100002_create_chat_threads_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('rag')->create('chat_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('rag')->dropIfExists('chat_threads');
    }
};
```

```php
<?php
// database/migrations/2026_07_31_100003_create_chat_messages_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('rag')->create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('chat_threads')->cascadeOnDelete();
            $table->string('role');
            $table->text('content');
            $table->json('citations')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('rag')->dropIfExists('chat_messages');
    }
};
```

- [ ] **Step 5: Write the models**

```php
<?php
// app/Models/BookChunk.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookChunk extends Model
{
    protected $connection = 'rag';

    public $timestamps = false;

    protected $fillable = ['book_id', 'chapter_id', 'chunk_index', 'content'];
}
```

```php
<?php
// app/Models/ChatThread.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatThread extends Model
{
    protected $connection = 'rag';

    protected $fillable = ['user_id', 'title'];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'thread_id')->orderBy('id');
    }
}
```

```php
<?php
// app/Models/ChatMessage.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $connection = 'rag';

    public $timestamps = false;

    protected $fillable = ['thread_id', 'role', 'content', 'citations'];

    protected $casts = [
        'citations' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ChatMessage $message) {
            $message->created_at ??= now();
        });
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }
}
```

- [ ] **Step 6: Run migrations and tests**

Run: `php artisan migrate`
Expected: the three new migrations run successfully against the `rag` connection (tracked in the default SQLite `migrations` table).

Run: `php artisan test --filter=RagSchemaTest`
Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add config/database.php database/migrations/2026_07_31_100001_create_book_chunks_table.php database/migrations/2026_07_31_100002_create_chat_threads_table.php database/migrations/2026_07_31_100003_create_chat_messages_table.php app/Models/BookChunk.php app/Models/ChatThread.php app/Models/ChatMessage.php tests/Feature/Rag/RagSchemaTest.php
git commit -m "feat: add rag Postgres connection, pgvector schema, and RAG models"
```

---

### Task 2: `GeminiClient` service

**Files:**
- Create: `app/Services/GeminiClient.php`
- Test: `tests/Unit/GeminiClientTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Unit/GeminiClientTest.php
use App\Services\GeminiClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.gemini.key' => 'test-key']);
});

it('embeds text and returns a 768-length float vector', function () {
    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent*' => Http::response([
            'embedding' => ['values' => array_fill(0, 768, 0.123)],
        ], 200),
    ]);

    $embedding = (new GeminiClient())->embed('some chapter text');

    expect($embedding)->toHaveCount(768);
    expect($embedding[0])->toBe(0.123);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'gemini-embedding-001:embedContent')
            && $request['outputDimensionality'] === 768
            && $request['content']['parts'][0]['text'] === 'some chapter text';
    });
});

it('throws when the embed call fails', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'bad request']], 400),
    ]);

    (new GeminiClient())->embed('text');
})->throws(RuntimeException::class);

it('generates text from a prompt', function () {
    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'The answer is 42.']]]],
            ],
        ], 200),
    ]);

    $answer = (new GeminiClient())->generate('What is the answer?');

    expect($answer)->toBe('The answer is 42.');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'gemini-2.5-flash:generateContent')
            && $request['contents'][0]['parts'][0]['text'] === 'What is the answer?';
    });
});

it('throws when the generate call fails', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'quota exceeded']], 429),
    ]);

    (new GeminiClient())->generate('question');
})->throws(RuntimeException::class);
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=GeminiClientTest`
Expected: FAIL — `App\Services\GeminiClient` doesn't exist.

- [ ] **Step 3: Add the Gemini API key to config**

Add to `config/services.php` (inside the top-level array, alongside other provider entries):

```php
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
    ],
```

- [ ] **Step 4: Implement the service**

```php
<?php
// app/Services/GeminiClient.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiClient
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';
    private const EMBEDDING_MODEL = 'gemini-embedding-001';
    private const EMBEDDING_DIMENSIONS = 768;
    private const GENERATION_MODEL = 'gemini-2.5-flash';

    private function apiKey(): string
    {
        $key = config('services.gemini.key');
        if (! $key) {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }
        return $key;
    }

    /** @return float[] */
    public function embed(string $text): array
    {
        $response = Http::post(
            self::BASE_URL . '/' . self::EMBEDDING_MODEL . ':embedContent?key=' . $this->apiKey(),
            [
                'content' => ['parts' => [['text' => $text]]],
                'outputDimensionality' => self::EMBEDDING_DIMENSIONS,
            ]
        );

        if ($response->failed()) {
            throw new RuntimeException('Gemini embed request failed: ' . ($response->json('error.message') ?? $response->body()));
        }

        $values = $response->json('embedding.values');
        if (! is_array($values)) {
            throw new RuntimeException('Gemini embed response missing embedding.values.');
        }

        return $values;
    }

    public function generate(string $prompt): string
    {
        $response = Http::post(
            self::BASE_URL . '/' . self::GENERATION_MODEL . ':generateContent?key=' . $this->apiKey(),
            [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]
        );

        if ($response->failed()) {
            throw new RuntimeException('Gemini generate request failed: ' . ($response->json('error.message') ?? $response->body()));
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($text)) {
            throw new RuntimeException('Gemini generate response missing candidate text.');
        }

        return $text;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=GeminiClientTest`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add config/services.php app/Services/GeminiClient.php tests/Unit/GeminiClientTest.php
git commit -m "feat: add GeminiClient service for embeddings and generation"
```

---

### Task 3: Chapter chunking

**Files:**
- Create: `app/Services/ChapterChunker.php`
- Test: `tests/Unit/ChapterChunkerTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Unit/ChapterChunkerTest.php
use App\Services\ChapterChunker;

it('extracts plain-text paragraphs from chapter HTML', function () {
    $html = '<h1>Title</h1><p>First paragraph.</p><p>Second paragraph.</p>';
    $chunks = (new ChapterChunker())->chunk($html);

    expect($chunks)->toHaveCount(1);
    expect($chunks[0])->toContain('First paragraph.')
        ->toContain('Second paragraph.');
});

it('strips images and other non-text tags', function () {
    $html = '<p>Before image.</p><img src="pic.jpg" alt="a picture"><p>After image.</p>';
    $chunks = (new ChapterChunker())->chunk($html);

    expect($chunks[0])->toContain('Before image.')
        ->toContain('After image.')
        ->not->toContain('<img');
});

it('splits into multiple chunks at paragraph boundaries once the size cap is exceeded', function () {
    $paragraph = '<p>' . str_repeat('word ', 200) . '</p>'; // ~1000 chars per paragraph
    $html = str_repeat($paragraph, 5); // ~5000 chars total, cap is 3000

    $chunks = (new ChapterChunker())->chunk($html);

    expect(count($chunks))->toBeGreaterThan(1);
    foreach ($chunks as $chunk) {
        expect(mb_strlen($chunk))->toBeLessThanOrEqual(3000 + 1000); // cap + one paragraph's worth of slack
    }
});

it('never splits a single paragraph in the middle', function () {
    $longWord = str_repeat('a', 50);
    $html = '<p>' . implode(' ', array_fill(0, 10, $longWord)) . '</p>';
    $chunks = (new ChapterChunker())->chunk($html);

    // the whole paragraph must appear intact in exactly one chunk
    $matches = array_filter($chunks, fn ($c) => str_contains($c, $longWord . ' ' . $longWord));
    expect($matches)->not->toBeEmpty();
});

it('returns an empty array for empty content', function () {
    expect((new ChapterChunker())->chunk(''))->toBe([]);
    expect((new ChapterChunker())->chunk('<p></p>'))->toBe([]);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ChapterChunkerTest`
Expected: FAIL — `App\Services\ChapterChunker` doesn't exist.

- [ ] **Step 3: Implement the chunker**

```php
<?php
// app/Services/ChapterChunker.php
namespace App\Services;

use DOMDocument;
use DOMXPath;

class ChapterChunker
{
    private const MAX_CHUNK_CHARS = 3000;

    /** @return string[] */
    public function chunk(string $html): array
    {
        $paragraphs = $this->extractParagraphs($html);
        if (empty($paragraphs)) {
            return [];
        }

        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $candidate = $current === '' ? $paragraph : $current . "\n\n" . $paragraph;

            if (mb_strlen($candidate) > self::MAX_CHUNK_CHARS && $current !== '') {
                $chunks[] = $current;
                $current = $paragraph;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /** @return string[] */
    private function extractParagraphs(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>');
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $blocks = $xpath->query('//p | //h1 | //h2 | //h3 | //h4 | //h5 | //h6 | //li | //blockquote');

        $paragraphs = [];
        foreach ($blocks as $block) {
            $text = trim(preg_replace('/\s+/', ' ', $block->textContent));
            if ($text !== '') {
                $paragraphs[] = $text;
            }
        }

        return $paragraphs;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=ChapterChunkerTest`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/ChapterChunker.php tests/Unit/ChapterChunkerTest.php
git commit -m "feat: add ChapterChunker for splitting chapter HTML into text chunks"
```

---

### Task 4: `IndexBookChunksJob`, wired to book readiness

**Files:**
- Create: `app/Jobs/IndexBookChunksJob.php`
- Modify: `app/Jobs/ParseEpubBookJob.php`
- Test: `tests/Feature/Rag/IndexBookChunksJobTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Rag/IndexBookChunksJobTest.php
use App\Jobs\IndexBookChunksJob;
use App\Models\Book;
use App\Models\BookChunk;
use App\Models\Chapter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class, DatabaseTransactions::class);

it('embeds and stores chunks for every chapter of a book', function () {
    $book = Book::factory()->create(['status' => 'ready']);
    Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 0, 'content' => '<p>Chapter one content here.</p>']);
    Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 1, 'content' => '<p>Chapter two content here.</p>']);

    Http::fake([
        'generativelanguage.googleapis.com/*embedContent*' => Http::response([
            'embedding' => ['values' => array_fill(0, 768, 0.05)],
        ], 200),
    ]);

    (new IndexBookChunksJob($book))->handle(app(\App\Services\GeminiClient::class), app(\App\Services\ChapterChunker::class));

    $chunks = BookChunk::where('book_id', $book->id)->get();
    expect($chunks)->toHaveCount(2);
    expect($chunks->pluck('chapter_id')->unique())->toHaveCount(2);
});

it('replaces existing chunks on re-run rather than duplicating them', function () {
    $book = Book::factory()->create(['status' => 'ready']);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 0, 'content' => '<p>Original content.</p>']);

    Http::fake([
        'generativelanguage.googleapis.com/*embedContent*' => Http::response([
            'embedding' => ['values' => array_fill(0, 768, 0.05)],
        ], 200),
    ]);

    $job = new IndexBookChunksJob($book);
    $job->handle(app(\App\Services\GeminiClient::class), app(\App\Services\ChapterChunker::class));
    $job->handle(app(\App\Services\GeminiClient::class), app(\App\Services\ChapterChunker::class));

    expect(BookChunk::where('book_id', $book->id)->count())->toBe(1);
});

it('skips chapters with no extractable text', function () {
    $book = Book::factory()->create(['status' => 'ready']);
    Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 0, 'content' => '<img src="cover.jpg">']);

    Http::fake();

    (new IndexBookChunksJob($book))->handle(app(\App\Services\GeminiClient::class), app(\App\Services\ChapterChunker::class));

    expect(BookChunk::where('book_id', $book->id)->count())->toBe(0);
    Http::assertNothingSent();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=IndexBookChunksJobTest`
Expected: FAIL — `App\Jobs\IndexBookChunksJob` doesn't exist.

- [ ] **Step 3: Implement the job**

```php
<?php
// app/Jobs/IndexBookChunksJob.php
namespace App\Jobs;

use App\Models\Book;
use App\Models\BookChunk;
use App\Services\ChapterChunker;
use App\Services\GeminiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class IndexBookChunksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Book $book) {}

    public function handle(GeminiClient $gemini, ChapterChunker $chunker): void
    {
        BookChunk::where('book_id', $this->book->id)->delete();

        foreach ($this->book->chapters as $chapter) {
            $textChunks = $chunker->chunk($chapter->content ?? '');

            foreach ($textChunks as $index => $text) {
                $embedding = $gemini->embed($text);
                $vectorLiteral = '[' . implode(',', $embedding) . ']';

                DB::connection('rag')->statement(
                    'insert into book_chunks (book_id, chapter_id, chunk_index, content, embedding) values (?, ?, ?, ?, ?::vector)',
                    [$this->book->id, $chapter->id, $index, $text, $vectorLiteral]
                );
            }
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=IndexBookChunksJobTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Wire the job to dispatch when a book becomes ready**

In `app/Jobs/ParseEpubBookJob.php`, add the import `use App\Jobs\IndexBookChunksJob;` and change:

```php
    public function handle(EpubParsingService $parser): void
    {
        $this->book->update(['status' => 'processing']);
        $parser->parse($this->book);
        $this->book->status = 'ready';
        $this->book->save();
    }
```

to:

```php
    public function handle(EpubParsingService $parser): void
    {
        $this->book->update(['status' => 'processing']);
        $parser->parse($this->book);
        $this->book->status = 'ready';
        $this->book->save();

        IndexBookChunksJob::dispatch($this->book);
    }
```

- [ ] **Step 6: Add a regression test confirming the dispatch**

Add to `tests/Feature/EpubUploadTest.php` (or wherever `ParseEpubBookJob` is currently tested — locate it first with `grep -rl "ParseEpubBookJob" tests/`):

```php
it('dispatches IndexBookChunksJob once the book is parsed and ready', function () {
    \Illuminate\Support\Facades\Queue::fake();

    // ... reuse this file's existing setup for building/dispatching a ParseEpubBookJob against a real fixture book ...

    \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\IndexBookChunksJob::class);
});
```

(Adapt the `...` setup lines to match whatever pattern the existing tests in that file already use to run `ParseEpubBookJob` against a fixture — read the file first and follow its established pattern exactly rather than guessing.)

- [ ] **Step 7: Run the full backend test suite**

Run: `php artisan test`
Expected: all tests pass, including the new dispatch assertion.

- [ ] **Step 8: Commit**

```bash
git add app/Jobs/IndexBookChunksJob.php app/Jobs/ParseEpubBookJob.php tests/Feature/Rag/IndexBookChunksJobTest.php tests/Feature/EpubUploadTest.php
git commit -m "feat: index book chunks into pgvector when a book becomes ready"
```

---

### Task 5: `RetrievalService`

**Files:**
- Create: `app/Services/RetrievalService.php`
- Test: `tests/Feature/Rag/RetrievalServiceTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Rag/RetrievalServiceTest.php
use App\Services\RetrievalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

function insertTestChunk(int $bookId, int $chapterId, int $index, string $content, array $embedding): void
{
    $vector = '[' . implode(',', $embedding) . ']';
    DB::connection('rag')->statement(
        'insert into book_chunks (book_id, chapter_id, chunk_index, content, embedding) values (?, ?, ?, ?, ?::vector)',
        [$bookId, $chapterId, $index, $content, $vector]
    );
}

it('returns the closest chunks by cosine distance', function () {
    $close = array_fill(0, 768, 0.1);
    $far = array_fill(0, 768, -0.9);

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

    DB::connection('rag')->table('book_chunks')->where('book_id', 1)->delete();
});

it('respects the limit parameter', function () {
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

    DB::connection('rag')->table('book_chunks')->where('book_id', 2)->delete();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=RetrievalServiceTest`
Expected: FAIL — `App\Services\RetrievalService` doesn't exist.

- [ ] **Step 3: Implement the service**

```php
<?php
// app/Services/RetrievalService.php
namespace App\Services;

use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Support\Facades\DB;

class RetrievalService
{
    /** @return array<int, array{book_id:int, chapter_id:int, content:string, book_title:string, chapter_title:?string}> */
    public function search(string $question, int $limit = 6): array
    {
        $gemini = app(GeminiClient::class);
        $embedding = $gemini->embed($question);
        $vectorLiteral = '[' . implode(',', $embedding) . ']';

        $rows = DB::connection('rag')->select(
            'select book_id, chapter_id, content from book_chunks order by embedding <=> ?::vector limit ?',
            [$vectorLiteral, $limit]
        );

        $results = [];
        foreach ($rows as $row) {
            $book = Book::find($row->book_id);
            $chapter = Chapter::find($row->chapter_id);

            $results[] = [
                'book_id' => (int) $row->book_id,
                'chapter_id' => (int) $row->chapter_id,
                'content' => $row->content,
                'book_title' => $book?->title ?? 'Unknown book',
                'chapter_title' => $chapter?->title,
            ];
        }

        return $results;
    }
}
```

Note: `RetrievalService` deliberately has no constructor — it resolves `GeminiClient` via the `app()` helper inside `search()` instead of constructor injection, since the tests above construct it directly with `new RetrievalService()`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=RetrievalServiceTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/RetrievalService.php tests/Feature/Rag/RetrievalServiceTest.php
git commit -m "feat: add RetrievalService for pgvector cosine-similarity search"
```

---

### Task 6: `ChatService`

**Files:**
- Create: `app/Services/ChatService.php`
- Test: `tests/Feature/Rag/ChatServiceTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Rag/ChatServiceTest.php
use App\Models\ChatThread;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

it('creates a new thread, asks a question, and persists both messages with citations', function () {
    $vector = '[' . implode(',', array_fill(0, 768, 0.1)) . ']';
    DB::connection('rag')->statement(
        'insert into book_chunks (book_id, chapter_id, chunk_index, content, embedding) values (?, ?, ?, ?, ?::vector)',
        [1, 1, 0, 'The sky is blue because of Rayleigh scattering.', $vector]
    );

    Http::fake([
        'generativelanguage.googleapis.com/*embedContent*' => Http::response([
            'embedding' => ['values' => array_fill(0, 768, 0.1)],
        ], 200),
        'generativelanguage.googleapis.com/*generateContent*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'The sky is blue due to Rayleigh scattering.']]]]],
        ], 200),
    ]);

    $result = (new ChatService())->ask(userId: 1, threadId: null, question: 'Why is the sky blue?');

    expect($result['thread'])->toBeInstanceOf(ChatThread::class);
    expect($result['thread']->title)->toBe('Why is the sky blue?');
    expect($result['thread']->messages)->toHaveCount(2);
    expect($result['thread']->messages[0]->role)->toBe('user');
    expect($result['thread']->messages[1]->role)->toBe('assistant');
    expect($result['thread']->messages[1]->citations)->not->toBeEmpty();

    DB::connection('rag')->table('book_chunks')->where('book_id', 1)->delete();
});

it('continues an existing thread using its prior messages as context', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*embedContent*' => Http::response([
            'embedding' => ['values' => array_fill(0, 768, 0.1)],
        ], 200),
        'generativelanguage.googleapis.com/*generateContent*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Second answer.']]]]],
        ], 200),
    ]);

    $service = new ChatService();
    $first = $service->ask(userId: 1, threadId: null, question: 'First question?');
    $second = $service->ask(userId: 1, threadId: $first['thread']->id, question: 'Follow-up question?');

    expect($second['thread']->id)->toBe($first['thread']->id);
    expect($second['thread']->messages)->toHaveCount(4);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'generateContent')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'First question?');
    });
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ChatServiceTest`
Expected: FAIL — `App\Services\ChatService` doesn't exist.

- [ ] **Step 3: Implement the service**

```php
<?php
// app/Services/ChatService.php
namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use Illuminate\Support\Str;

class ChatService
{
    private const SYSTEM_PROMPT = <<<'TEXT'
        You are a helpful librarian assistant. Answer the reader's question using ONLY the
        excerpts provided below. If the excerpts don't contain enough information to answer,
        say so plainly rather than guessing or using outside knowledge. When you use an excerpt,
        mention which book it came from.
        TEXT;

    /** @return array{thread: ChatThread} */
    public function ask(int $userId, ?int $threadId, string $question): array
    {
        $thread = $threadId
            ? ChatThread::findOrFail($threadId)
            : ChatThread::create(['user_id' => $userId, 'title' => Str::limit($question, 80, '')]);

        ChatMessage::create([
            'thread_id' => $thread->id,
            'role' => 'user',
            'content' => $question,
        ]);

        $retrieval = app(RetrievalService::class);
        $chunks = $retrieval->search($question);

        $prompt = $this->buildPrompt($thread, $chunks, $question);

        $gemini = app(GeminiClient::class);
        $answer = $gemini->generate($prompt);

        $citations = collect($chunks)
            ->unique(fn ($c) => $c['book_id'] . ':' . $c['chapter_id'])
            ->map(fn ($c) => [
                'book_id' => $c['book_id'],
                'chapter_id' => $c['chapter_id'],
                'book_title' => $c['book_title'],
                'chapter_title' => $c['chapter_title'],
            ])
            ->values()
            ->all();

        ChatMessage::create([
            'thread_id' => $thread->id,
            'role' => 'assistant',
            'content' => $answer,
            'citations' => $citations,
        ]);

        return ['thread' => $thread->fresh('messages')];
    }

    private function buildPrompt(ChatThread $thread, array $chunks, string $question): string
    {
        $excerpts = collect($chunks)
            ->map(fn ($c) => "From \"{$c['book_title']}\"" . ($c['chapter_title'] ? " ({$c['chapter_title']})" : '') . ":\n{$c['content']}")
            ->implode("\n\n---\n\n");

        $history = $thread->messages()
            ->get()
            ->map(fn (ChatMessage $m) => strtoupper($m->role) . ': ' . $m->content)
            ->implode("\n\n");

        $sections = array_filter([
            self::SYSTEM_PROMPT,
            $excerpts !== '' ? "EXCERPTS:\n\n{$excerpts}" : "EXCERPTS: (none found)",
            $history !== '' ? "CONVERSATION SO FAR:\n\n{$history}" : null,
            "QUESTION: {$question}",
        ]);

        return implode("\n\n", $sections);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=ChatServiceTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/ChatService.php tests/Feature/Rag/ChatServiceTest.php
git commit -m "feat: add ChatService for thread-aware RAG question answering"
```

---

### Task 7: Admin chat routes and controller

**Files:**
- Create: `app/Http/Controllers/Admin/LibraryChatController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Rag/LibraryChatControllerTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Rag/LibraryChatControllerTest.php
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class, DatabaseTransactions::class);

it('requires authentication', function () {
    $this->get('/admin/library/chat')->assertRedirect('/login');
});

it('lets an admin view the chat index with their threads', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    ChatThread::create(['user_id' => $admin->id, 'title' => 'My thread']);

    $this->actingAs($admin)
        ->get('/admin/library/chat')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Library/Chat/Index')
            ->has('threads', 1));
});

it('lets an admin post a question and get a persisted answer', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*embedContent*' => Http::response([
            'embedding' => ['values' => array_fill(0, 768, 0.1)],
        ], 200),
        'generativelanguage.googleapis.com/*generateContent*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'An answer.']]]]],
        ], 200),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post('/admin/library/chat', ['question' => 'Test question?']);

    $response->assertRedirect();
    expect(ChatThread::where('user_id', $admin->id)->count())->toBe(1);
});

it('blocks non-admin/editor/viewer roles', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/library/chat')->assertForbidden();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=LibraryChatControllerTest`
Expected: FAIL — route/controller don't exist. (An Inertia stub page will also be needed for `assertInertia` to resolve the component — see Step 4's note.)

- [ ] **Step 3: Implement the controller**

```php
<?php
// app/Http/Controllers/Admin/LibraryChatController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatThread;
use App\Services\ChatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LibraryChatController extends Controller
{
    public function index(Request $request): Response
    {
        $threads = ChatThread::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'updated_at']);

        return Inertia::render('Admin/Library/Chat/Index', [
            'threads' => $threads,
            'activeThread' => null,
        ]);
    }

    public function show(Request $request, ChatThread $thread): Response
    {
        abort_unless($thread->user_id === $request->user()->id, 403);

        $threads = ChatThread::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'updated_at']);

        return Inertia::render('Admin/Library/Chat/Index', [
            'threads' => $threads,
            'activeThread' => $thread->load('messages'),
        ]);
    }

    public function store(Request $request, ChatService $chatService): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'thread_id' => ['nullable', 'integer'],
        ]);

        $result = $chatService->ask(
            userId: $request->user()->id,
            threadId: $data['thread_id'] ?? null,
            question: $data['question'],
        );

        return redirect()->route('admin.library.chat.show', $result['thread']->id);
    }
}
```

- [ ] **Step 4: Add the routes**

In `routes/web.php`, add near the other `admin/library/...` routes (after the `library.index` block from around line 96):

```php
use App\Http\Controllers\Admin\LibraryChatController;

Route::middleware(['auth', 'role:admin,editor,viewer'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('library/chat', [LibraryChatController::class, 'index'])->name('library.chat.index');
        Route::get('library/chat/{thread}', [LibraryChatController::class, 'show'])->name('library.chat.show');
        Route::post('library/chat', [LibraryChatController::class, 'store'])->name('library.chat.store');
    });
```

Since `testing.ensure_pages_exist` requires the Vue page file to exist for `assertInertia()->component()` to pass, create a minimal placeholder now (Task 8 replaces it with the real page):

```vue
<!-- resources/js/Pages/Admin/Library/Chat/Index.vue -->
<template><div /></template>
<script setup>
defineProps({ threads: Array, activeThread: Object })
</script>
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=LibraryChatControllerTest`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/LibraryChatController.php routes/web.php resources/js/Pages/Admin/Library/Chat/Index.vue tests/Feature/Rag/LibraryChatControllerTest.php
git commit -m "feat: add admin library chat routes and controller"
```

---

### Task 8: Admin chat UI

**Files:**
- Modify: `resources/js/Pages/Admin/Library/Chat/Index.vue` (replacing the Task 7 placeholder)
- Modify: `resources/js/Layouts/AdminLayout.vue`

- [ ] **Step 1: Add the nav entry**

In `resources/js/Layouts/AdminLayout.vue`, find the `allNav` array (the `Library` entry is around line 110) and add a new entry immediately after it:

```js
  { label: 'Library Chat',  href: '/admin/library/chat', roles: ['admin','editor','viewer'], icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8-1.5 0-2.9-.33-4.14-.9L3 20l1.05-3.16C3.38 15.6 3 14.35 3 13c0-4.418 4.03-8 9-8s9 3.582 9 7z' },
```

- [ ] **Step 2: Implement the real page**

```vue
<!-- resources/js/Pages/Admin/Library/Chat/Index.vue -->
<template>
  <Head title="Library Chat" />
  <AdminLayout>
    <div class="flex h-[calc(100vh-4rem)]">
      <aside class="w-64 flex-shrink-0 border-r border-slate-200 overflow-y-auto">
        <div class="p-4">
          <Link href="/admin/library/chat" class="block w-full text-center px-3 py-2 rounded-lg bg-slate-900 text-white text-sm hover:bg-slate-700 transition-colors">
            + New Chat
          </Link>
        </div>
        <nav class="px-2 space-y-1">
          <Link
            v-for="t in threads"
            :key="t.id"
            :href="`/admin/library/chat/${t.id}`"
            class="block px-3 py-2 rounded-lg text-sm truncate"
            :class="activeThread?.id === t.id ? 'bg-slate-100 text-slate-900 font-medium' : 'text-slate-600 hover:bg-slate-50'"
          >
            {{ t.title || 'Untitled thread' }}
          </Link>
          <p v-if="threads.length === 0" class="px-3 py-2 text-sm text-slate-400">No threads yet.</p>
        </nav>
      </aside>

      <section class="flex-1 flex flex-col">
        <div class="flex-1 overflow-y-auto p-6 space-y-4">
          <div v-if="!activeThread" class="text-sm text-slate-400 text-center py-20">
            Ask a question about your library to start a new thread.
          </div>
          <template v-else>
            <div v-for="m in activeThread.messages" :key="m.id" class="max-w-2xl" :class="m.role === 'user' ? 'ml-auto' : ''">
              <div
                class="px-4 py-2.5 rounded-2xl text-sm"
                :class="m.role === 'user' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-800'"
              >
                {{ m.content }}
              </div>
              <div v-if="m.citations?.length" class="mt-1.5 flex flex-wrap gap-1.5">
                <a
                  v-for="(c, i) in m.citations"
                  :key="i"
                  :href="`/library/books/${bookSlugFor(c)}/chapters/0`"
                  class="text-xs px-2 py-0.5 rounded-full bg-orange-50 text-orange-600 hover:bg-orange-100 transition-colors"
                >
                  {{ c.book_title }}<template v-if="c.chapter_title"> — {{ c.chapter_title }}</template>
                </a>
              </div>
            </div>
          </template>
        </div>

        <form @submit.prevent="submit" class="border-t border-slate-200 p-4 flex gap-3">
          <input
            v-model="form.question"
            type="text"
            placeholder="Ask about your library…"
            class="flex-1 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-slate-900"
            :disabled="form.processing"
          />
          <button
            type="submit"
            :disabled="form.processing || !form.question.trim()"
            class="px-5 py-2.5 bg-slate-900 text-white rounded-xl text-sm font-medium hover:bg-slate-700 disabled:opacity-50 transition-colors"
          >
            {{ form.processing ? 'Asking…' : 'Ask' }}
          </button>
        </form>
      </section>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  threads: { type: Array, default: () => [] },
  activeThread: { type: Object, default: null },
})

const form = useForm({ question: '', thread_id: props.activeThread?.id ?? null })

const submit = () => {
  form.thread_id = props.activeThread?.id ?? null
  form.post('/admin/library/chat', {
    preserveScroll: true,
    onSuccess: () => { form.question = '' },
  })
}

const bookSlugFor = () => '' // citations carry book_id, not slug; left as a known gap, see plan note below
</script>
```

**Known gap, intentionally deferred:** citations only carry `book_id`/`chapter_id` (per the `chat_messages.citations` JSON shape defined in the spec), not the book's `slug`, so the citation link's `href` above can't resolve to a real `/library/books/{slug}/...` URL yet without an extra lookup. Task 9's manual verification will surface exactly how broken this is; the fix (either embedding `book_slug` into the stored citation JSON in `ChatService::ask()`, or resolving slugs client-side via an extra prop) is deferred to a follow-up commit after seeing the real UI, rather than guessed at now.

- [ ] **Step 3: Verify**

Run: `npm run build`
Expected: clean build.

Run: `php artisan test --filter=LibraryChatControllerTest`
Expected: still PASS (4 tests) — the real page satisfies the same Inertia component/props contract as the Task 7 placeholder.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Admin/Library/Chat/Index.vue resources/js/Layouts/AdminLayout.vue
git commit -m "feat: add admin library chat UI"
```

---

### Task 9: Full verification pass

**Files:** none (verification + the citation-slug fix identified in Task 8)

- [ ] **Step 1: Fix the citation slug gap found in Task 8**

In `app/Services/ChatService.php`, update the citation-building block inside `ask()` to include the book's slug (needed for real citation links):

```php
        $citations = collect($chunks)
            ->unique(fn ($c) => $c['book_id'] . ':' . $c['chapter_id'])
            ->map(function ($c) {
                $book = \App\Models\Book::find($c['book_id']);
                $chapter = \App\Models\Chapter::find($c['chapter_id']);
                return [
                    'book_id' => $c['book_id'],
                    'chapter_id' => $c['chapter_id'],
                    'book_title' => $c['book_title'],
                    'chapter_title' => $c['chapter_title'],
                    'book_slug' => $book?->slug,
                    'chapter_sort_order' => $chapter?->sort_order,
                ];
            })
            ->values()
            ->all();
```

Update `resources/js/Pages/Admin/Library/Chat/Index.vue`'s citation link and remove the placeholder helper:

```vue
                <a
                  v-for="(c, i) in m.citations"
                  :key="i"
                  :href="`/library/books/${c.book_slug}/chapters/${c.chapter_sort_order ?? 0}`"
                  class="text-xs px-2 py-0.5 rounded-full bg-orange-50 text-orange-600 hover:bg-orange-100 transition-colors"
                >
                  {{ c.book_title }}<template v-if="c.chapter_title"> — {{ c.chapter_title }}</template>
                </a>
```

(remove the `const bookSlugFor = () => ''` line from the script block entirely — it's no longer used.)

Run: `php artisan test --filter=ChatServiceTest` and `php artisan test --filter=LibraryChatControllerTest`
Expected: still PASS.

Run: `npm run build`
Expected: clean.

Commit:

```bash
git add app/Services/ChatService.php resources/js/Pages/Admin/Library/Chat/Index.vue
git commit -m "fix: include book slug and chapter sort order in chat citations"
```

- [ ] **Step 2: Run the full test suites**

Run: `php artisan test`
Expected: all tests pass (existing suite plus every new RAG test from Tasks 1–8).

Run: `npm run test:unit && npm run build`
Expected: clean.

- [ ] **Step 3: Index a real book and verify manually in the browser**

Using a book already `ready` in the library (from the e-library feature): run `php artisan tinker` and dispatch `\App\Jobs\IndexBookChunksJob::dispatchSync(\App\Models\Book::where('status','ready')->first())` (or trigger it by re-saving the book's status to `ready` through the normal retry flow, which now auto-dispatches indexing per Task 4). Confirm `App\Models\BookChunk::count()` is non-zero afterward.

Then, logged in as admin, visit `/admin/library/chat` and verify against the spec's own checklist:

- [ ] Ask a question whose answer clearly exists in an uploaded book — confirm the answer is actually correct, not a plausible-sounding hallucination.
- [ ] Confirm the citation link actually navigates to the real book/chapter that contains the answer.
- [ ] Ask a question the library has no coverage for — confirm the assistant says so rather than making something up.
- [ ] Ask a follow-up question in the same thread — confirm it uses the prior context (e.g., answers "what about the second one?" correctly based on the previous exchange).
- [ ] Confirm a non-admin/editor/viewer user gets a 403 when visiting `/admin/library/chat` directly.

- [ ] **Step 4: Commit any fixes found during manual verification. This plan is then complete.**
