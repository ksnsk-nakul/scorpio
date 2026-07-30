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
}
