<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Setting;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(fn () => $this->seed());

it('seeds the three roles', function () {
    expect(Role::pluck('name')->toArray())
        ->toContain('admin', 'editor', 'viewer');
});

it('seeds default settings', function () {
    expect(Setting::where('key', 'site_name')->exists())->toBeTrue();
});

it('seeds a ready sample library book with chapters in order', function () {
    $book = Book::where('title', 'Introduction to the Library')->first();

    expect($book)->not->toBeNull()
        ->and($book->status)->toBe('ready')
        ->and($book->author->name)->toBe('Scorpio');

    $chapters = $book->chapters()->orderBy('sort_order')->get();
    expect($chapters)->toHaveCount(2)
        ->and($chapters[0]->title)->toBe('Welcome to the Library')
        ->and($chapters[0]->content)->toContain('<h1>Welcome to the Library</h1>')
        ->and($chapters[1]->title)->toBe('How to Use This Library');
});

it('does not duplicate the sample book when seeded twice', function () {
    $this->seed(\Database\Seeders\LibrarySeeder::class);

    expect(Book::where('title', 'Introduction to the Library')->count())->toBe(1)
        ->and(Chapter::count())->toBe(2);
});
