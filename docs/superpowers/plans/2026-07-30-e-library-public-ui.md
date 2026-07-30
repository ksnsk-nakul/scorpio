# E-Library Public UI Implementation Plan (Plan 3/3)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Public, unauthenticated browsing and reading of the e-library — a book grid, a book detail page with chapter table of contents, a chapter reader with prev/next navigation and reader themes, and an author page. This is the last piece needed for the original "upload → browse → read" test.

**Architecture:** A new top-level `LibraryController` (not under `Admin/`) serves four public routes, inserted before the existing catch-all `/{slug}` portfolio route so they aren't shadowed. Only `status: ready` books are ever shown publicly — pending/processing/failed books stay admin-only. The book grid reuses the pagination (15/page) and title-clamp-with-tooltip conventions already established on the admin page; the chapter reader reuses the `useReaderTheme` composable and the same markdown-body CSS pattern already built for the file-viewer's `TextRenderer`.

**Tech Stack:** Laravel 13, Vue 3 `<script setup>`, Inertia.js, Tailwind — matching `resources/js/Pages/Public/ProjectDetail.vue`'s existing public-page conventions (no shared `PublicLayout.vue` exists in this codebase; each public page builds its own nav inline, following that precedent).

**Reference:** `docs/superpowers/specs/2026-07-30-e-library-design.md`; Plans 1 and 2 (already implemented on this branch)

---

## File Map

**New**
- `app/Http/Controllers/LibraryController.php`
- `resources/js/Pages/Public/Library/Index.vue`
- `resources/js/Pages/Public/Library/BookDetail.vue`
- `resources/js/Pages/Public/Library/ChapterReader.vue`
- `resources/js/Pages/Public/Library/AuthorShow.vue`
- `tests/Feature/PublicLibraryTest.php`

**Modified**
- `routes/web.php` — 4 new public routes, inserted before the catch-all `/{slug}`

---

## Task 1: `LibraryController::index()` — public book grid

**Files:**
- Create: `app/Http/Controllers/LibraryController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/PublicLibraryTest.php`

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/PublicLibraryTest.php`
Expected: FAIL — 404, route/controller don't exist

- [ ] **Step 3: Implement `LibraryController::index()`**

```php
<?php
namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Book;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Public/Library/Index', [
            'books' => Book::with('author')
                ->where('status', 'ready')
                ->latest()
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Book $book) => [
                    'title' => $book->title,
                    'slug' => $book->slug,
                    'author' => $book->author?->name,
                    'cover_url' => $book->cover_url,
                ]),
        ]);
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, find the "Admin portfolio inner pages at root (must be last to avoid shadowing other routes)" comment near the end of the file — add the new routes **immediately before** that comment block, so they're registered ahead of the catch-all `/{slug}`:

```php
use App\Http\Controllers\LibraryController;

Route::get('/library', [LibraryController::class, 'index'])->name('library.index');

// Admin portfolio inner pages at root (must be last to avoid shadowing other routes)
Route::get('/{slug}', [\App\Http\Controllers\PublicController::class, 'adminPage'])
    ->name('admin.page')
    ->where('slug', '[a-z0-9\-]+');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/PublicLibraryTest.php`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/LibraryController.php routes/web.php tests/Feature/PublicLibraryTest.php
git commit -m "feat: add public library index endpoint"
```

---

## Task 2: `LibraryController::show()` — book detail

**Files:**
- Modify: `app/Http/Controllers/LibraryController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/PublicLibraryTest.php`

- [ ] **Step 1: Write the failing tests**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/PublicLibraryTest.php --filter="shows a ready book|404s for a book|404s for a nonexistent"`
Expected: FAIL — 404 route doesn't exist (different from the intentional 404s being tested, but same status code — verify by checking the response has no Inertia component, confirming it's a routing 404 not the controller's own 404)

- [ ] **Step 3: Implement `show()`**

Add to `LibraryController`:

```php
    public function show(string $slug): Response
    {
        $book = Book::where('slug', $slug)
            ->where('status', 'ready')
            ->with(['author', 'chapters' => fn ($q) => $q->orderBy('sort_order')])
            ->firstOrFail();

        return Inertia::render('Public/Library/BookDetail', [
            'book' => [
                'title' => $book->title,
                'slug' => $book->slug,
                'description' => $book->description,
                'cover_url' => $book->cover_url,
                'language' => $book->language,
                'publisher' => $book->publisher,
                'published_date' => $book->published_date?->toDateString(),
                'author' => $book->author ? ['name' => $book->author->name, 'slug' => $book->author->slug] : null,
                'chapters' => $book->chapters->map(fn ($c) => ['title' => $c->title, 'sort_order' => $c->sort_order])->values(),
            ],
        ]);
    }
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, in the same spot as Task 1's route (before the catch-all):

```php
Route::get('/library/books/{slug}', [LibraryController::class, 'show'])
    ->name('library.book')
    ->where('slug', '[a-z0-9\-]+');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/PublicLibraryTest.php`
Expected: PASS (5 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/LibraryController.php routes/web.php tests/Feature/PublicLibraryTest.php
git commit -m "feat: add public book detail endpoint"
```

---

## Task 3: `LibraryController::chapter()` — chapter reader endpoint

**Files:**
- Modify: `app/Http/Controllers/LibraryController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/PublicLibraryTest.php`

- [ ] **Step 1: Write the failing tests**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/PublicLibraryTest.php --filter="chapter"`
Expected: FAIL — route doesn't exist

- [ ] **Step 3: Implement `chapter()`**

Add to `LibraryController`:

```php
    public function chapter(string $slug, int $sortOrder): Response
    {
        $book = Book::where('slug', $slug)->where('status', 'ready')->firstOrFail();
        $chapter = $book->chapters()->where('sort_order', $sortOrder)->firstOrFail();

        return Inertia::render('Public/Library/ChapterReader', [
            'book' => ['title' => $book->title, 'slug' => $book->slug],
            'chapter' => [
                'title' => $chapter->title,
                'content' => $chapter->content,
                'sort_order' => $chapter->sort_order,
            ],
            'hasPrev' => $sortOrder > 0 && $book->chapters()->where('sort_order', $sortOrder - 1)->exists(),
            'hasNext' => $book->chapters()->where('sort_order', $sortOrder + 1)->exists(),
        ]);
    }
```

- [ ] **Step 4: Add the route**

```php
Route::get('/library/books/{slug}/chapters/{sortOrder}', [LibraryController::class, 'chapter'])
    ->name('library.chapter')
    ->where(['slug' => '[a-z0-9\-]+', 'sortOrder' => '[0-9]+']);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/PublicLibraryTest.php`
Expected: PASS (8 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/LibraryController.php routes/web.php tests/Feature/PublicLibraryTest.php
git commit -m "feat: add public chapter reader endpoint"
```

---

## Task 4: `LibraryController::author()` — author page

**Files:**
- Modify: `app/Http/Controllers/LibraryController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/PublicLibraryTest.php`

- [ ] **Step 1: Write the failing tests**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/PublicLibraryTest.php --filter="author"`
Expected: FAIL — route doesn't exist

- [ ] **Step 3: Implement `author()`**

Add to `LibraryController`:

```php
    public function author(string $slug): Response
    {
        $author = Author::where('slug', $slug)->firstOrFail();

        return Inertia::render('Public/Library/AuthorShow', [
            'author' => ['name' => $author->name, 'bio' => $author->bio],
            'books' => $author->books()
                ->where('status', 'ready')
                ->latest()
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Book $book) => [
                    'title' => $book->title,
                    'slug' => $book->slug,
                    'cover_url' => $book->cover_url,
                ]),
        ]);
    }
```

- [ ] **Step 4: Add the route**

```php
Route::get('/library/authors/{slug}', [LibraryController::class, 'author'])
    ->name('library.author')
    ->where('slug', '[a-z0-9\-]+');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/PublicLibraryTest.php`
Expected: PASS (10 tests)

- [ ] **Step 6: Run the full backend suite**

Run: `php artisan test`
Expected: all green except the pre-existing unrelated `ExampleTest` failure

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/LibraryController.php routes/web.php tests/Feature/PublicLibraryTest.php
git commit -m "feat: add public author page endpoint"
```

---

## Task 5: `Public/Library/Index.vue` — public book grid

**Files:**
- Create: `resources/js/Pages/Public/Library/Index.vue`

No dedicated Vitest test — an Inertia page wired to routes/session, verified by manual browser check in Task 9, matching the convention already established for the admin Library page.

- [ ] **Step 1: Write the page**

A cover-grid browsing experience (not the admin's list/grid/icons management view — public visitors browse a library, they don't manage records) with pagination and the same title-clamp-with-tooltip treatment used everywhere else in this feature:

```vue
<template>
  <Head>
    <title>Library</title>
  </Head>

  <div class="min-h-screen bg-white font-sans">
    <nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl bg-white/80 border-b border-slate-100">
      <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
        <a href="/" class="text-sm text-slate-500 hover:text-orange-500 transition-colors">← Home</a>
        <span class="text-sm font-semibold text-slate-800">Library</span>
      </div>
    </nav>

    <main class="pt-20 pb-16 max-w-6xl mx-auto px-6">
      <h1 class="text-3xl font-bold text-slate-800 mb-8">Library</h1>

      <div v-if="books.data.length === 0" class="text-sm text-slate-400 py-12 text-center">
        No books available yet.
      </div>

      <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-6">
        <Link v-for="book in books.data" :key="book.slug" :href="`/library/books/${book.slug}`" class="group">
          <div class="aspect-[2/3] rounded-lg overflow-hidden bg-slate-100 mb-2 shadow-sm group-hover:shadow-md transition-shadow">
            <img v-if="book.cover_url" :src="book.cover_url" class="w-full h-full object-cover" />
          </div>
          <p class="text-sm font-medium text-slate-800 line-clamp-2" :title="book.title">{{ book.title }}</p>
          <p v-if="book.author" class="text-xs text-slate-400 mt-0.5 truncate">{{ book.author }}</p>
        </Link>
      </div>

      <div v-if="books.last_page > 1" class="flex flex-wrap gap-1 mt-10 justify-center">
        <Link
          v-for="(link, i) in books.links"
          :key="i"
          :href="link.url ?? '#'"
          v-html="link.label"
          class="px-3 py-1.5 text-sm rounded-lg"
          :class="link.active ? 'bg-orange-500 text-white' : link.url ? 'text-slate-600 hover:bg-slate-100' : 'text-slate-300 pointer-events-none'"
        />
      </div>
    </main>
  </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineProps({ books: { type: Object, required: true } })
</script>
```

- [ ] **Step 2: Run the build**

Run: `npm run build`
Expected: clean build, no errors

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Public/Library/Index.vue
git commit -m "feat: add public library book grid page"
```

---

## Task 6: `Public/Library/BookDetail.vue`

**Files:**
- Create: `resources/js/Pages/Public/Library/BookDetail.vue`

- [ ] **Step 1: Write the page**

```vue
<template>
  <Head>
    <title>{{ book.title }}</title>
    <meta name="description" :content="book.description ?? book.title" />
  </Head>

  <div class="min-h-screen bg-white font-sans">
    <nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl bg-white/80 border-b border-slate-100">
      <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
        <a href="/library" class="text-sm text-slate-500 hover:text-orange-500 transition-colors">← Library</a>
        <span class="text-sm font-semibold text-slate-800 truncate max-w-xs">{{ book.title }}</span>
      </div>
    </nav>

    <main class="pt-20 pb-16 max-w-4xl mx-auto px-6">
      <div class="flex flex-col sm:flex-row gap-8 mb-10">
        <div class="w-40 flex-shrink-0 mx-auto sm:mx-0">
          <div class="aspect-[2/3] rounded-lg overflow-hidden bg-slate-100 shadow-sm">
            <img v-if="book.cover_url" :src="book.cover_url" class="w-full h-full object-cover" />
          </div>
        </div>
        <div class="flex-1">
          <h1 class="text-2xl font-bold text-slate-800 mb-2">{{ book.title }}</h1>
          <a v-if="book.author" :href="`/library/authors/${book.author.slug}`" class="text-sm text-orange-500 hover:underline">
            {{ book.author.name }}
          </a>
          <p v-if="book.description" class="text-sm text-slate-600 leading-relaxed mt-4">{{ book.description }}</p>
          <dl class="mt-4 space-y-1 text-xs text-slate-400">
            <div v-if="book.publisher"><dt class="inline font-medium">Publisher:</dt> <dd class="inline">{{ book.publisher }}</dd></div>
            <div v-if="book.published_date"><dt class="inline font-medium">Published:</dt> <dd class="inline">{{ book.published_date }}</dd></div>
            <div v-if="book.language"><dt class="inline font-medium">Language:</dt> <dd class="inline">{{ book.language }}</dd></div>
          </dl>
        </div>
      </div>

      <h2 class="text-lg font-semibold text-slate-800 mb-3">Chapters</h2>
      <ol class="divide-y divide-slate-100 border border-slate-100 rounded-xl overflow-hidden">
        <li v-for="chapter in book.chapters" :key="chapter.sort_order">
          <Link
            :href="`/library/books/${book.slug}/chapters/${chapter.sort_order}`"
            class="flex items-center justify-between px-4 py-3 text-sm hover:bg-slate-50 transition-colors"
          >
            <span class="line-clamp-1" :title="chapter.title ?? `Chapter ${chapter.sort_order + 1}`">
              {{ chapter.title ?? `Chapter ${chapter.sort_order + 1}` }}
            </span>
            <span class="text-slate-300">›</span>
          </Link>
        </li>
      </ol>
    </main>
  </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineProps({ book: { type: Object, required: true } })
</script>
```

- [ ] **Step 2: Run the build**

Run: `npm run build`
Expected: clean build, no errors

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Public/Library/BookDetail.vue
git commit -m "feat: add public book detail page"
```

---

## Task 7: `Public/Library/ChapterReader.vue`

**Files:**
- Create: `resources/js/Pages/Public/Library/ChapterReader.vue`

Reuses `useReaderTheme` (from `resources/js/composables/useReaderTheme.js`, built for the file-viewer feature) and the same hand-rolled markdown-body CSS pattern from `resources/js/Components/FileViewer/renderers/TextRenderer.vue`, since chapter `content` is real HTML (headings/paragraphs/lists) needing the same preflight-override treatment.

- [ ] **Step 1: Write the page**

```vue
<template>
  <Head>
    <title>{{ chapter.title ?? `Chapter ${chapter.sort_order + 1}` }} · {{ book.title }}</title>
  </Head>

  <div class="min-h-screen font-sans" :class="themeClass">
    <nav class="sticky top-0 z-50 backdrop-blur-xl border-b border-current/10" :class="themeClass">
      <div class="max-w-3xl mx-auto px-6 h-14 flex items-center justify-between text-sm">
        <a :href="`/library/books/${book.slug}`" class="opacity-70 hover:opacity-100 transition-opacity truncate max-w-[10rem]">
          ← {{ book.title }}
        </a>
        <div class="flex items-center gap-3">
          <button @click="setTheme('white')" class="opacity-70 hover:opacity-100">White</button>
          <button @click="setTheme('sepia')" class="opacity-70 hover:opacity-100">Sepia</button>
          <button @click="setTheme('sepia-dark')" class="opacity-70 hover:opacity-100">Dark Sepia</button>
          <button @click="setTheme('black')" class="opacity-70 hover:opacity-100">Black</button>
          <button @click="decreaseFontSize" class="opacity-70 hover:opacity-100">A−</button>
          <button @click="increaseFontSize" class="opacity-70 hover:opacity-100">A+</button>
        </div>
      </div>
    </nav>

    <main class="max-w-3xl mx-auto px-6 py-10">
      <h1 class="text-xl font-bold mb-6">{{ chapter.title ?? `Chapter ${chapter.sort_order + 1}` }}</h1>
      <div class="markdown-body" :style="fontStyle" v-html="chapter.content"></div>

      <div class="flex items-center justify-between mt-12 pt-6 border-t border-current/10 text-sm">
        <Link v-if="hasPrev" :href="`/library/books/${book.slug}/chapters/${chapter.sort_order - 1}`" class="opacity-70 hover:opacity-100">← Previous</Link>
        <span v-else></span>
        <Link v-if="hasNext" :href="`/library/books/${book.slug}/chapters/${chapter.sort_order + 1}`" class="opacity-70 hover:opacity-100">Next →</Link>
        <span v-else></span>
      </div>
    </main>
  </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { useReaderTheme } from '@/composables/useReaderTheme'

defineProps({
  book: { type: Object, required: true },
  chapter: { type: Object, required: true },
  hasPrev: { type: Boolean, required: true },
  hasNext: { type: Boolean, required: true },
})

const { themeClass, fontStyle, setTheme, increaseFontSize, decreaseFontSize } = useReaderTheme()
</script>

<style scoped>
/*
 * Same hand-rolled markdown typography as
 * resources/js/Components/FileViewer/renderers/TextRenderer.vue — Tailwind's
 * preflight strips default heading/list styling, and this app has no
 * @tailwindcss/typography dependency. color: inherit throughout so it keeps
 * working across all four reader themes.
 */
.markdown-body :deep(h1),
.markdown-body :deep(h2),
.markdown-body :deep(h3),
.markdown-body :deep(h4),
.markdown-body :deep(h5),
.markdown-body :deep(h6) {
  color: inherit;
  font-weight: 700;
  line-height: 1.25;
  margin-top: 1em;
  margin-bottom: 0.5em;
}

.markdown-body :deep(h1) { font-size: 1.75em; }
.markdown-body :deep(h2) { font-size: 1.5em; }
.markdown-body :deep(h3) { font-size: 1.25em; }
.markdown-body :deep(h4) { font-size: 1.1em; }
.markdown-body :deep(h5) { font-size: 1em; }
.markdown-body :deep(h6) { font-size: 0.875em; }

.markdown-body :deep(p),
.markdown-body :deep(blockquote) {
  margin-top: 0.5em;
  margin-bottom: 0.5em;
}

.markdown-body :deep(ul),
.markdown-body :deep(ol) {
  margin-top: 0.5em;
  margin-bottom: 0.5em;
  padding-left: 1.5em;
}

.markdown-body :deep(ul) { list-style: disc; }
.markdown-body :deep(ol) { list-style: decimal; }
.markdown-body :deep(li) { margin-top: 0.25em; }

.markdown-body :deep(blockquote) {
  border-left: 3px solid currentColor;
  padding-left: 0.75em;
  opacity: 0.85;
}

.markdown-body :deep(img) {
  max-width: 100%;
  height: auto;
  border-radius: 0.5em;
}
</style>
```

- [ ] **Step 2: Run the build**

Run: `npm run build`
Expected: clean build, no errors

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Public/Library/ChapterReader.vue
git commit -m "feat: add public chapter reader page with reader themes"
```

---

## Task 8: `Public/Library/AuthorShow.vue`

**Files:**
- Create: `resources/js/Pages/Public/Library/AuthorShow.vue`

- [ ] **Step 1: Write the page**

```vue
<template>
  <Head>
    <title>{{ author.name }}</title>
  </Head>

  <div class="min-h-screen bg-white font-sans">
    <nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl bg-white/80 border-b border-slate-100">
      <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
        <a href="/library" class="text-sm text-slate-500 hover:text-orange-500 transition-colors">← Library</a>
        <span class="text-sm font-semibold text-slate-800">{{ author.name }}</span>
      </div>
    </nav>

    <main class="pt-20 pb-16 max-w-6xl mx-auto px-6">
      <h1 class="text-2xl font-bold text-slate-800 mb-2">{{ author.name }}</h1>
      <p v-if="author.bio" class="text-sm text-slate-500 mb-8 max-w-2xl">{{ author.bio }}</p>

      <div v-if="books.data.length === 0" class="text-sm text-slate-400 py-12 text-center">
        No books by this author yet.
      </div>

      <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-6">
        <Link v-for="book in books.data" :key="book.slug" :href="`/library/books/${book.slug}`" class="group">
          <div class="aspect-[2/3] rounded-lg overflow-hidden bg-slate-100 mb-2 shadow-sm group-hover:shadow-md transition-shadow">
            <img v-if="book.cover_url" :src="book.cover_url" class="w-full h-full object-cover" />
          </div>
          <p class="text-sm font-medium text-slate-800 line-clamp-2" :title="book.title">{{ book.title }}</p>
        </Link>
      </div>

      <div v-if="books.last_page > 1" class="flex flex-wrap gap-1 mt-10 justify-center">
        <Link
          v-for="(link, i) in books.links"
          :key="i"
          :href="link.url ?? '#'"
          v-html="link.label"
          class="px-3 py-1.5 text-sm rounded-lg"
          :class="link.active ? 'bg-orange-500 text-white' : link.url ? 'text-slate-600 hover:bg-slate-100' : 'text-slate-300 pointer-events-none'"
        />
      </div>
    </main>
  </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineProps({
  author: { type: Object, required: true },
  books: { type: Object, required: true },
})
</script>
```

- [ ] **Step 2: Run the build**

Run: `npm run build`
Expected: clean build, no errors

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Public/Library/AuthorShow.vue
git commit -m "feat: add public author page"
```

---

## Task 9: Manual verification in the browser

**Files:** none (verification only)

- [ ] **Step 1: Start the app**

Run: `npm run build && php artisan serve` (and `php artisan queue:work` in another terminal if testing a fresh upload)

- [ ] **Step 2: Exercise the full "upload → browse → read" flow**

- Visit `/library` while logged out — confirm it loads with no auth prompt, shows only `ready` books (upload a `pending`/`failed` test book via the admin page first and confirm it does NOT appear here).
- Confirm pagination appears once there are more than 15 ready books, and the page-link clicks work.
- Confirm long titles clamp to 2 lines with a hover tooltip showing the full title (same treatment verified on the admin page).
- Click into a book — confirm cover, description, author link, and chapter list in correct reading order.
- Click the author name — confirm the author page shows only that author's `ready` books.
- Click a chapter — confirm content renders with real heading/list styling (not flat unstyled text), prev/next navigation works across the whole book, and the boundary cases are correct (no "Previous" on chapter 1, no "Next" on the last chapter).
- Toggle all 4 reader themes and both font-size buttons — confirm they visibly change and persist across navigating to the next chapter (since `useReaderTheme`'s preference is a shared module-level singleton, per the file-viewer feature's existing behavior).

- [ ] **Step 3: Fix anything broken, then run the full suite**

Run: `php artisan test && npm run build`
Expected: all green (except the pre-existing unrelated `ExampleTest` failure), clean build

- [ ] **Step 4: Commit any fixes from manual testing**

If manual testing surfaces real bugs, commit fixes with descriptive messages, one fix per commit — don't batch unrelated fixes together.

---

## Out of scope (tracked in the spec, future work)

- RAG chat over the library (separate spec, depends on this content existing — already written: `docs/superpowers/specs/2026-07-30-library-rag-chat-design.md`)
- Reader accounts, reading progress, personal shelves (deferred, noted in the e-library spec)
- App-wide pagination/responsive-table retrofit for the ~10 other existing admin listing pages (separate future project, agreed to sequence after this plan)
