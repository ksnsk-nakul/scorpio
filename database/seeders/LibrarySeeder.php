<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\User;
use Illuminate\Database\Seeder;

class LibrarySeeder extends Seeder
{
    public function run(): void
    {
        if (Book::where('title', 'Introduction to the Library')->exists()) {
            return;
        }

        $adminEmail = filled(env('ADMIN_EMAIL')) ? env('ADMIN_EMAIL') : 'admin@example.com';
        $adminId = User::where('email', $adminEmail)->value('id');
        abort_if(is_null($adminId), 1, 'Admin user not found — run UserSeeder first.');

        $author = Author::findOrCreateByName('Scorpio');

        $book = Book::create([
            'author_id' => $author->id,
            'title' => 'Introduction to the Library',
            'description' => 'A short, built-in guide to how the Library works — reading modes, themes, and where real books come from.',
            'source_epub_path' => 'seeded/library-demo',
            'status' => 'ready',
            'uploaded_by' => $adminId,
        ]);

        $dataDir = database_path('seeders/data/library/introduction-to-the-library');
        $chapters = [
            ['title' => 'Welcome to the Library', 'file' => 'chapter-1-welcome.html'],
            ['title' => 'How to Use This Library', 'file' => 'chapter-2-how-to-use.html'],
        ];

        foreach ($chapters as $sortOrder => $chapter) {
            Chapter::create([
                'book_id' => $book->id,
                'title' => $chapter['title'],
                'content' => file_get_contents("{$dataDir}/{$chapter['file']}"),
                'sort_order' => $sortOrder,
            ]);
        }
    }
}
