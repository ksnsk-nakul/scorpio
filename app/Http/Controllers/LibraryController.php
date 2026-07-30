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
