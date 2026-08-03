<?php
namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Book;
use App\Models\LibraryEntry;
use App\Models\Series;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Book::with(['author', 'series'])->where('status', 'ready');

        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhereHas('author', fn ($a) => $a->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('series', fn ($s) => $s->where('name', 'like', "%{$term}%"));
            });
        }

        return Inertia::render('Public/Library/Index', [
            'books' => $query
                ->latest()
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Book $book) => [
                    'title' => $book->title,
                    'slug' => $book->slug,
                    'author' => $book->author?->name,
                    'cover_url' => $book->cover_url,
                    'series' => $book->series ? ['name' => $book->series->name, 'slug' => $book->series->slug, 'volume_number' => $book->volume_number] : null,
                ]),
            'filters' => $request->only(['search']),
        ]);
    }

    public function show(string $slug): Response
    {
        $book = Book::where('slug', $slug)
            ->where('status', 'ready')
            ->with(['author', 'series', 'chapters' => fn ($q) => $q->orderBy('sort_order')])
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
                'series' => $book->series ? [
                    'name' => $book->series->name,
                    'slug' => $book->series->slug,
                    'volume_number' => $book->volume_number,
                    'other_volumes' => $book->series->books()->where('status', 'ready')->where('id', '!=', $book->id)->orderBy('volume_number')->get()->map(fn ($b) => ['title' => $b->title, 'slug' => $b->slug, 'volume_number' => $b->volume_number, 'cover_url' => $b->cover_url])->values(),
                ] : null,
            ],
        ]);
    }

    public function chapter(string $slug, int $sortOrder): Response
    {
        $book = Book::where('slug', $slug)->where('status', 'ready')->firstOrFail();
        $chapter = $book->chapters()->where('sort_order', $sortOrder)->firstOrFail();

        if ($user = auth()->user()) {
            LibraryEntry::updateOrCreate(
                ['user_id' => $user->id, 'book_id' => $book->id],
                ['last_chapter_id' => $chapter->id, 'last_read_at' => now()]
            );
        }

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

    public function series(string $slug): Response
    {
        $series = Series::where('slug', $slug)->firstOrFail();

        return Inertia::render('Public/Library/SeriesShow', [
            'series' => ['name' => $series->name, 'slug' => $series->slug, 'description' => $series->description],
            'books' => $series->books()->where('status', 'ready')->orderBy('volume_number')
                ->paginate(15)->withQueryString()
                ->through(fn (Book $book) => ['title' => $book->title, 'slug' => $book->slug, 'volume_number' => $book->volume_number, 'cover_url' => $book->cover_url]),
        ]);
    }

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
}
