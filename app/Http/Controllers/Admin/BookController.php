<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ParseEpubBookJob;
use App\Models\Author;
use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    private const ALLOWED_EPUB_MIMES = ['application/epub+zip', 'application/zip', 'application/octet-stream'];

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Library/Index', [
            'books' => $this->filteredQuery($request)
                ->with('author')->latest()->latest('id')->paginate(15)->withQueryString()->through(fn (Book $book) => [
                'id' => $book->id,
                'title' => $book->title,
                'slug' => $book->slug,
                'author' => $book->author?->name,
                'description' => $book->description,
                'status' => $book->status,
                'status_reason' => $book->status_reason,
                'is_stuck' => $book->isStuck(),
                'cover_url' => $book->cover_url,
                'created_at' => $book->created_at->toDateTimeString(),
            ]),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function uploadPage(Request $request): Response
    {
        return Inertia::render('Admin/Library/Upload', [
            'recentUploads' => Book::where('uploaded_by', $request->user()->id)
                ->latest()
                ->latest('id')
                ->limit(100)
                ->get(['id', 'title', 'slug', 'status', 'status_reason', 'created_at'])
                ->map(fn (Book $book) => [
                    'id' => $book->id,
                    'title' => $book->title,
                    'slug' => $book->slug,
                    'status' => $book->status,
                    'status_reason' => $book->status_reason,
                    'created_at' => $book->created_at->toDateTimeString(),
                ]),
        ]);
    }

    /** Shared by index() (for display) and bulkDestroy()'s "all matching filter" mode
     *  (for deletion) so the two can never silently disagree on what a filter means. */
    private function filteredQuery(Request $request): Builder
    {
        return Book::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search');
                $query->where(function ($q) use ($term) {
                    $q->where('title', 'like', "%{$term}%")
                        ->orWhereHas('author', fn ($a) => $a->where('name', 'like', "%{$term}%"));
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $status = $request->string('status');
                // "active" is a UI-level grouping (pending + processing), not a real
                // status value in the books table — expand it here so index() and
                // bulkDestroy()'s "all matching filter" mode both understand it.
                $status === 'active'
                    ? $query->whereIn('status', ['pending', 'processing'])
                    : $query->where('status', $status);
            });
    }

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

    public function retry(Book $book): JsonResponse
    {
        abort_unless($book->isFailed() || $book->isStuck(), 422, 'Only failed or stuck books can be retried.');

        // A retry that's doomed to fail again (source file gone — e.g. it was cleaned
        // up between upload and processing) is worse than not retrying: it just looks
        // stuck forever instead of clearly telling the admin what to do next.
        if (! Storage::disk('public')->exists($book->source_epub_path)) {
            $book->update(['status' => 'failed', 'status_reason' => 'Source .epub file is missing on disk — delete this entry and re-upload.']);
            return response()->json(['id' => $book->id, 'status' => $book->status, 'status_reason' => $book->status_reason]);
        }

        $book->update(['status' => 'pending', 'status_reason' => null]);
        ParseEpubBookJob::dispatch($book);

        return response()->json(['id' => $book->id, 'status' => $book->status]);
    }

    /** Give a stuck (pending/processing) book an explicit "failed" status instead of
     *  silently sitting there forever — lets it flow through the normal retry/delete
     *  actions rather than needing a third, special-cased UI path. */
    public function markFailed(Book $book): JsonResponse
    {
        abort_unless($book->isStuck(), 422, 'Only stuck books can be marked as failed.');

        $book->update(['status' => 'failed', 'status_reason' => 'Manually marked as failed — was stuck processing.']);

        return response()->json(['id' => $book->id, 'status' => $book->status]);
    }

    public function destroy(Book $book): JsonResponse
    {
        $this->deleteBookCompletely($book);

        return response()->json(['deleted' => true]);
    }

    /**
     * Batch delete. Three modes, one endpoint, so "select some," "select all matching
     * the current search/status filter," and "delete everything" can never drift into
     * three separately-maintained deletion code paths:
     *   - {ids: [1,2,3]}                      — exactly those books
     *   - {all_matching_filter: true, search, status}  — every book matching that filter
     *   - {all_matching_filter: true} (no filter)      — every book, period
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => 'sometimes|array',
            'ids.*' => 'integer',
            'all_matching_filter' => 'sometimes|boolean',
            'search' => 'sometimes|string',
            'status' => 'sometimes|string',
        ]);

        if (! empty($data['ids'])) {
            $books = Book::whereIn('id', $data['ids'])->get();
        } elseif (! empty($data['all_matching_filter'])) {
            $books = $this->filteredQuery($request)->get();
        } else {
            return response()->json(['message' => 'No books specified.'], 422);
        }

        $count = $books->count();
        foreach ($books as $book) {
            $this->deleteBookCompletely($book);
        }

        return response()->json(['deleted' => $count]);
    }

    private function deleteBookCompletely(Book $book): void
    {
        Storage::disk('public')->deleteDirectory("books/{$book->id}");
        Storage::disk('public')->delete($book->source_epub_path);

        // book_chunks lives on the separate `rag` Postgres connection with no FK to
        // sqlite's books table (cross-database, can't cascade) — clean it up explicitly
        // so a delete doesn't leave orphaned embeddings behind. Skip cleanly if the rag
        // connection isn't configured/reachable here, same as the migrations do.
        if (\App\Support\RagConnectionGuard::available()) {
            DB::connection('rag')->table('book_chunks')->where('book_id', $book->id)->delete();
        }

        $book->delete();
    }
}
