# E-Library Admin UI Implementation Plan (Plan 2/3)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give admins/editors a way to manage the e-library — bulk-upload EPUBs with live per-file status, see every book in a list, manually override title/author/description after parsing, delete a book, and retry a failed parse.

**Architecture:** Extends the existing `BookController` (from Plan 1) with `index`/`update`/`destroy` actions. One Vue page, `Admin/Library/Index.vue`, holds the whole flow — a multi-file drop zone that uploads each file as its own request (reusing Plan 1's `store` endpoint per file) with live status polling, a table of every book, and a Teleport modal for editing — matching this codebase's existing `Admin/Pages/Index.vue` (list) and `Admin/Users/Index.vue` (table) conventions. No separate Edit.vue page — three fields don't warrant one.

**Tech Stack:** Laravel 13 (existing `Book`/`Author` models from Plan 1), Vue 3 `<script setup>`, Inertia.js, Tailwind (slate/blue palette, matching every other admin page).

**Reference:** `docs/superpowers/specs/2026-07-30-e-library-design.md`, `docs/superpowers/plans/2026-07-30-e-library-backend-ingestion.md` (Plan 1 — already implemented and merged-in-progress on this same branch)

---

## File Map

**Modified**
- `app/Http/Controllers/Admin/BookController.php` — add `index`, `update`, `destroy`
- `routes/web.php` — add index/update/destroy routes
- `resources/js/Layouts/AdminLayout.vue` — add "Library" nav entry
- `tests/Feature/BookUploadTest.php` — extend with index/update/destroy tests

**New**
- `resources/js/Pages/Admin/Library/Index.vue`

---

## Task 1: `BookController::index()`

**Files:**
- Modify: `app/Http/Controllers/Admin/BookController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/BookUploadTest.php`

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/BookUploadTest.php`:

```php
it('lists all books with author names for the admin index page', function () {
    $author = \App\Models\Author::factory()->create(['name' => 'Jane Doe']);
    Book::factory()->create(['title' => 'A Book', 'author_id' => $author->id, 'status' => 'ready', 'uploaded_by' => $this->admin->id]);
    Book::factory()->create(['title' => 'Pending Book', 'author_id' => null, 'status' => 'pending', 'uploaded_by' => $this->admin->id]);

    $response = $this->actingAs($this->admin)->get('/admin/library');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Library/Index')
        ->has('books', 2)
        ->where('books.0.title', 'Pending Book') // latest() first
        ->where('books.1.author', 'Jane Doe'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/BookUploadTest.php --filter="lists all books"`
Expected: FAIL — 404, route doesn't exist

- [ ] **Step 3: Implement `index()`**

Add to `app/Http/Controllers/Admin/BookController.php` (add `use Inertia\Inertia;` and `use Inertia\Response;` to the imports at the top):

```php
    public function index(): Response
    {
        return Inertia::render('Admin/Library/Index', [
            'books' => Book::with('author')->latest()->get()->map(fn (Book $book) => [
                'id' => $book->id,
                'title' => $book->title,
                'slug' => $book->slug,
                'author' => $book->author?->name,
                'description' => $book->description,
                'status' => $book->status,
                'status_reason' => $book->status_reason,
                'cover_url' => $book->cover_url,
                'created_at' => $book->created_at->toDateTimeString(),
            ]),
        ]);
    }
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, in the existing `admin,editor,viewer` group that already has the `library/books/{book}/status` route (added in Plan 1), add:

```php
        Route::get('library', [BookController::class, 'index'])->name('library.index');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/BookUploadTest.php --filter="lists all books"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/BookController.php routes/web.php tests/Feature/BookUploadTest.php
git commit -m "feat: add book library admin index endpoint"
```

---

## Task 2: `BookController::update()`

**Files:**
- Modify: `app/Http/Controllers/Admin/BookController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/BookUploadTest.php`

- [ ] **Step 1: Write the failing test**

```php
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

    expect(\App\Models\Author::count())->toBe(2); // the book's original factory author + this one
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/BookUploadTest.php --filter="updates title"`
Expected: FAIL — 404

- [ ] **Step 3: Implement `update()`**

Add to `BookController` (add `use App\Models\Author;` to imports):

```php
    public function update(Request $request, Book $book): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'author_name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
        ]);

        if (isset($data['title'])) {
            $book->title = $data['title'];
            $book->slug = Book::uniqueSlug($data['title'], $book->id);
        }

        if (isset($data['author_name'])) {
            $book->author_id = Author::findOrCreateByName($data['author_name'])->id;
        }

        if (array_key_exists('description', $data)) {
            $book->description = $data['description'];
        }

        $book->save();

        return response()->json(['id' => $book->id, 'title' => $book->title, 'slug' => $book->slug]);
    }
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, in the existing `admin,editor` group (the one with `store`/`retry`):

```php
        Route::patch('library/books/{book}', [BookController::class, 'update'])->name('library.books.update');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/BookUploadTest.php --filter="updates title|reuses an existing author"`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/BookController.php routes/web.php tests/Feature/BookUploadTest.php
git commit -m "feat: add book manual-override update endpoint"
```

---

## Task 3: `BookController::destroy()`

**Files:**
- Modify: `app/Http/Controllers/Admin/BookController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/BookUploadTest.php`

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/BookUploadTest.php --filter="deletes a book"`
Expected: FAIL — 404

- [ ] **Step 3: Implement `destroy()`**

Add to `BookController`:

```php
    public function destroy(Book $book): JsonResponse
    {
        Storage::disk('public')->deleteDirectory("books/{$book->id}");
        Storage::disk('public')->delete($book->source_epub_path);
        $book->delete();

        return response()->json(['deleted' => true]);
    }
```

Note: `Chapter` rows cascade-delete automatically via the `book_id` foreign key's `cascadeOnDelete()` set up in Plan 1's migration — no manual chapter cleanup needed in this method.

- [ ] **Step 4: Add the route**

In `routes/web.php`, same `admin,editor` group:

```php
        Route::delete('library/books/{book}', [BookController::class, 'destroy'])->name('library.books.destroy');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/BookUploadTest.php --filter="deletes a book"`
Expected: PASS

- [ ] **Step 6: Run the full backend suite**

Run: `php artisan test`
Expected: all green except the pre-existing unrelated `ExampleTest` failure

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/BookController.php routes/web.php tests/Feature/BookUploadTest.php
git commit -m "feat: add book delete endpoint with storage cleanup"
```

---

## Task 4: Add "Library" to the admin nav

**Files:**
- Modify: `resources/js/Layouts/AdminLayout.vue`

- [ ] **Step 1: Add the nav entry**

In `resources/js/Layouts/AdminLayout.vue`, find the nav array (starts around line 68 with `{ label: 'Dashboard', ... }`) and add, right after the `Pages` entry:

```js
  { label: 'Library',       href: '/admin/library',      roles: ['admin','editor','viewer'] },
```

- [ ] **Step 2: Verify the build succeeds**

Run: `npm run build`
Expected: clean build, no errors

- [ ] **Step 3: Commit**

```bash
git add resources/js/Layouts/AdminLayout.vue
git commit -m "feat: add Library link to admin nav"
```

---

## Task 5: `Admin/Library/Index.vue` — table, bulk upload, status polling

**Files:**
- Create: `resources/js/Pages/Admin/Library/Index.vue`

No dedicated Vitest test for this page — it's an Inertia page component wired to Laravel routes/CSRF/session, which is exercised by the manual browser verification in Task 7, matching how other Inertia admin pages in this codebase (e.g. `Admin/Pages/Index.vue`, `Admin/Users/Index.vue`) have no unit tests of their own.

- [ ] **Step 1: Write the page**

```vue
<template>
  <AdminLayout>
    <div class="max-w-5xl mx-auto">
      <h1 class="text-2xl font-bold text-slate-800 mb-6">Library</h1>

      <!-- Bulk upload -->
      <div
        class="border-2 border-dashed rounded-xl p-6 text-center mb-6 transition"
        :class="dragging ? 'border-blue-400 bg-blue-50' : 'border-slate-200 hover:border-blue-300'"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="onDrop"
      >
        <p class="text-sm text-slate-400 mb-2">
          Drop .epub files here or <span class="text-blue-600 font-medium cursor-pointer" @click="fileInput.click()">browse</span>
        </p>
        <p class="text-xs text-slate-300">Multiple files accepted — each uploads and parses independently.</p>
        <input ref="fileInput" type="file" accept=".epub" multiple class="hidden" @change="onFileChange" />
      </div>

      <!-- In-flight uploads -->
      <div v-if="uploading.length" class="mb-6 space-y-2">
        <div v-for="u in uploading" :key="u.tempId" class="flex items-center justify-between bg-white border border-slate-200 rounded-lg px-4 py-2 text-sm">
          <span class="truncate">{{ u.filename }}</span>
          <span class="text-xs" :class="u.status === 'failed' ? 'text-red-500' : 'text-slate-400'">
            {{ u.status === 'failed' ? (u.status_reason ?? 'Failed') : u.status }}
          </span>
        </div>
      </div>

      <div v-if="books.length === 0 && uploading.length === 0"
        class="bg-white border border-slate-200 rounded-xl px-6 py-12 text-center text-sm text-slate-400">
        No books yet.
      </div>

      <div v-else class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Cover</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Title</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Author</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Uploaded</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="book in books" :key="book.id" class="hover:bg-slate-50">
              <td class="px-5 py-3">
                <img v-if="book.cover_url" :src="book.cover_url" class="w-8 h-11 object-cover rounded" />
                <div v-else class="w-8 h-11 bg-slate-100 rounded"></div>
              </td>
              <td class="px-5 py-3 font-medium text-slate-800">{{ book.title }}</td>
              <td class="px-5 py-3 text-slate-500">{{ book.author ?? '—' }}</td>
              <td class="px-5 py-3">
                <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="statusBadge(book.status)">
                  {{ book.status }}
                </span>
                <span v-if="book.status === 'failed' && book.status_reason" class="block text-xs text-red-400 mt-1">
                  {{ book.status_reason }}
                </span>
              </td>
              <td class="px-5 py-3 text-slate-400 text-xs">{{ book.created_at }}</td>
              <td class="px-5 py-3 flex items-center gap-3">
                <button @click="openEdit(book)" class="text-xs text-blue-500 hover:text-blue-700">Edit</button>
                <button v-if="book.status === 'failed'" @click="retry(book)" class="text-xs text-amber-500 hover:text-amber-700">Retry</button>
                <button @click="destroy(book)" class="text-xs text-red-500 hover:text-red-700">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Edit modal -->
    <Teleport to="body">
      <div v-if="editing" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-96 shadow-xl">
          <h2 class="font-semibold mb-4 text-slate-800">Edit Book</h2>
          <label class="block text-xs text-slate-500 mb-1">Title</label>
          <input v-model="editForm.title" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 outline-none focus:ring-2 focus:ring-blue-500" />
          <label class="block text-xs text-slate-500 mb-1">Author</label>
          <input v-model="editForm.author_name" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 outline-none focus:ring-2 focus:ring-blue-500" />
          <label class="block text-xs text-slate-500 mb-1">Description</label>
          <textarea v-model="editForm.description" rows="4" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-4 outline-none focus:ring-2 focus:ring-blue-500"></textarea>
          <div class="flex gap-2 justify-end">
            <button @click="editing = null" class="px-4 py-2 text-sm text-slate-500">Cancel</button>
            <button @click="submitEdit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg">Save</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>

<script setup>
import { ref, onBeforeUnmount, reactive } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ books: { type: Array, required: true } })

const fileInput = ref(null)
const dragging = ref(false)
const uploading = ref([])
const editing = ref(null)
const editForm = reactive({ title: '', author_name: '', description: '' })
const pollTimers = {}

const statusBadge = (status) => ({
  ready: 'bg-green-50 text-green-700',
  pending: 'bg-slate-100 text-slate-600',
  processing: 'bg-blue-50 text-blue-700',
  failed: 'bg-red-50 text-red-700',
}[status] ?? 'bg-slate-100 text-slate-600')

const uploadFile = async (file) => {
  const tempId = `${file.name}-${Date.now()}`
  uploading.value.push({ tempId, filename: file.name, status: 'pending' })

  const fd = new FormData()
  fd.append('file', file)

  try {
    const { data } = await axios.post('/admin/library/books', fd)
    const entry = uploading.value.find((u) => u.tempId === tempId)
    entry.id = data.id
    entry.status = data.status
    if (data.status === 'pending' || data.status === 'processing') {
      startPolling(tempId, data.id)
    } else {
      finishUpload(tempId)
    }
  } catch (err) {
    const entry = uploading.value.find((u) => u.tempId === tempId)
    entry.status = 'failed'
    entry.status_reason = err.response?.data?.message ?? 'Upload failed.'
  }
}

const startPolling = (tempId, bookId) => {
  pollTimers[tempId] = setInterval(async () => {
    const { data } = await axios.get(`/admin/library/books/${bookId}/status`)
    const entry = uploading.value.find((u) => u.tempId === tempId)
    if (!entry) return
    entry.status = data.status
    entry.status_reason = data.status_reason
    if (data.status === 'ready' || data.status === 'failed') {
      clearInterval(pollTimers[tempId])
      delete pollTimers[tempId]
      if (data.status === 'ready') finishUpload(tempId)
    }
  }, 2000)
}

const finishUpload = (tempId) => {
  uploading.value = uploading.value.filter((u) => u.tempId !== tempId)
  router.reload({ only: ['books'] })
}

const onFileChange = (e) => {
  Array.from(e.target.files).forEach(uploadFile)
  e.target.value = ''
}

const onDrop = (e) => {
  dragging.value = false
  Array.from(e.dataTransfer.files).forEach(uploadFile)
}

const openEdit = (book) => {
  editing.value = book
  editForm.title = book.title
  editForm.author_name = book.author ?? ''
  editForm.description = book.description ?? ''
}

const submitEdit = async () => {
  await axios.patch(`/admin/library/books/${editing.value.id}`, { ...editForm })
  editing.value = null
  router.reload({ only: ['books'] })
}

const retry = async (book) => {
  await axios.post(`/admin/library/books/${book.id}/retry`)
  router.reload({ only: ['books'] })
}

const destroy = async (book) => {
  if (!confirm(`Delete "${book.title}"? This cannot be undone.`)) return
  await axios.delete(`/admin/library/books/${book.id}`)
  router.reload({ only: ['books'] })
}

onBeforeUnmount(() => {
  Object.values(pollTimers).forEach(clearInterval)
})
</script>
```

- [ ] **Step 2: Run the build**

Run: `npm run build`
Expected: clean build, no errors

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Admin/Library/Index.vue
git commit -m "feat: add e-library admin management page"
```

---

## Task 6: Manual verification in the browser

**Files:** none (verification only)

- [ ] **Step 1: Start the app**

Run: `npm run build && php artisan serve` (and in another terminal: `php artisan queue:work`, since uploads dispatch a queued job)

- [ ] **Step 2: Exercise the flow**

Log in as admin, visit `/admin/library`:
- Drag-and-drop or browse-select 2-3 real `.epub` files at once — confirm each shows in the "in-flight" list with live status, and each resolves to `ready` (or `failed` with a reason) independently without blocking the others.
- Confirm the table populates with correct cover/title/author/status once ready.
- Click "Edit" on a book, change the title/author/description, save, confirm the table reflects the change.
- Click "Delete" on a book, confirm it disappears and a re-upload of the same file works cleanly (no leftover state).
- Manually break a book's `source_epub_path` in the DB (or upload a corrupt file) to produce a `failed` status, confirm "Retry" appears and re-attempts parsing.

- [ ] **Step 3: Fix anything broken, then run the full suite**

Run: `php artisan test && npm run build`
Expected: all green (except the pre-existing unrelated `ExampleTest` failure), clean build

- [ ] **Step 4: Commit any fixes from manual testing**

If manual testing surfaced real bugs, commit fixes with descriptive messages following this plan's established pattern — don't batch unrelated fixes into one commit.

---

## Out of scope (tracked in the spec, Plan 3)

- Public library browsing (index/detail/author pages)
- Chapter reader
