<?php
namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\LibraryEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LibraryEntryController extends Controller
{
    public function follow(Request $request, string $slug): JsonResponse
    {
        $book = Book::where('slug', $slug)->firstOrFail();
        $entry = LibraryEntry::firstOrCreate(
            ['user_id' => $request->user()->id, 'book_id' => $book->id],
            ['status' => 'reading']
        );

        return response()->json(['status' => $entry->status]);
    }

    public function updateStatus(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:reading,completed,on_hold,plan_to_read,dropped'],
        ]);

        $book = Book::where('slug', $slug)->firstOrFail();
        $entry = LibraryEntry::updateOrCreate(
            ['user_id' => $request->user()->id, 'book_id' => $book->id],
            ['status' => $data['status']]
        );

        return response()->json(['status' => $entry->status]);
    }

    public function unfollow(Request $request, string $slug): JsonResponse
    {
        $book = Book::where('slug', $slug)->firstOrFail();
        LibraryEntry::where('user_id', $request->user()->id)->where('book_id', $book->id)->delete();

        return response()->json(['unfollowed' => true]);
    }

    public function recordProgress(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate(['sort_order' => ['required', 'integer', 'min:0']]);

        $book = Book::where('slug', $slug)->firstOrFail();
        $chapter = $book->chapters()->where('sort_order', $data['sort_order'])->firstOrFail();
        $entry = LibraryEntry::updateOrCreate(
            ['user_id' => $request->user()->id, 'book_id' => $book->id],
            ['last_chapter_id' => $chapter->id, 'last_read_at' => now()]
        );

        return response()->json(['last_chapter_id' => $entry->last_chapter_id, 'last_read_at' => $entry->last_read_at]);
    }
}
