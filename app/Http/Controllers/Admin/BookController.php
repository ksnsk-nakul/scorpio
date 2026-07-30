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
