<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\LibraryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

it('lets a logged-in user follow a book', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create(['status' => 'ready']);

    $response = $this->actingAs($user)->postJson("/library/books/{$book->slug}/follow");

    $response->assertOk()->assertJsonPath('status', 'reading');
    expect(LibraryEntry::where('user_id', $user->id)->where('book_id', $book->id)->first())
        ->not->toBeNull()
        ->status->toBe('reading');
});

it('is idempotent when following an already-followed book', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create(['status' => 'ready']);

    $this->actingAs($user)->postJson("/library/books/{$book->slug}/follow")->assertOk();
    $this->actingAs($user)->postJson("/library/books/{$book->slug}/follow")->assertOk();

    expect(LibraryEntry::where('user_id', $user->id)->where('book_id', $book->id)->count())->toBe(1);
});

it('lets a user set a status on a book, implicitly following it if not already', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create(['status' => 'ready']);

    $response = $this->actingAs($user)->patchJson("/library/books/{$book->slug}/status", ['status' => 'completed']);

    $response->assertOk()->assertJsonPath('status', 'completed');
    $entry = LibraryEntry::where('user_id', $user->id)->where('book_id', $book->id)->first();
    expect($entry)->not->toBeNull()->and($entry->status)->toBe('completed');
});

it('rejects an invalid status value', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create(['status' => 'ready']);

    $response = $this->actingAs($user)->patchJson("/library/books/{$book->slug}/status", ['status' => 'not_a_real_status']);

    $response->assertStatus(422);
    expect(LibraryEntry::where('user_id', $user->id)->where('book_id', $book->id)->count())->toBe(0);
});

it('lets a user unfollow a book, deleting the entry', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create(['status' => 'ready']);
    LibraryEntry::create(['user_id' => $user->id, 'book_id' => $book->id, 'status' => 'reading']);

    $response = $this->actingAs($user)->deleteJson("/library/books/{$book->slug}/follow");

    $response->assertOk()->assertJsonPath('unfollowed', true);
    expect(LibraryEntry::where('user_id', $user->id)->where('book_id', $book->id)->count())->toBe(0);
});

it('redirects guests to login instead of 500ing when hitting mutating library-entry endpoints', function () {
    $book = Book::factory()->create(['status' => 'ready']);
    Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 0]);

    $this->postJson("/library/books/{$book->slug}/follow")->assertUnauthorized();
    $this->patchJson("/library/books/{$book->slug}/status", ['status' => 'reading'])->assertUnauthorized();
    $this->deleteJson("/library/books/{$book->slug}/follow")->assertUnauthorized();
    $this->postJson("/library/books/{$book->slug}/progress", ['sort_order' => 0])->assertUnauthorized();

    // Non-JSON requests hit the same auth-protected routes and should redirect to
    // login (standard Laravel web `auth` middleware behavior) rather than 500ing.
    $this->post("/library/books/{$book->slug}/follow")->assertRedirect('/login');
    $this->patch("/library/books/{$book->slug}/status", ['status' => 'reading'])->assertRedirect('/login');
    $this->delete("/library/books/{$book->slug}/follow")->assertRedirect('/login');
    $this->post("/library/books/{$book->slug}/progress", ['sort_order' => 0])->assertRedirect('/login');
});

it('records reading progress silently when a logged-in user views a chapter, without changing an existing status', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create(['status' => 'ready']);
    $chapter0 = Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 0]);
    $chapter1 = Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 1]);
    LibraryEntry::create(['user_id' => $user->id, 'book_id' => $book->id, 'status' => 'on_hold']);

    $response = $this->actingAs($user)->get("/library/books/{$book->slug}/chapters/1");

    $response->assertOk();
    $entry = LibraryEntry::where('user_id', $user->id)->where('book_id', $book->id)->first();
    expect($entry->last_chapter_id)->toBe($chapter1->id)
        ->and($entry->last_read_at)->not->toBeNull()
        ->and($entry->status)->toBe('on_hold');
});

it('does not record progress for guests viewing a chapter', function () {
    $book = Book::factory()->create(['status' => 'ready']);
    Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 0]);

    $response = $this->get("/library/books/{$book->slug}/chapters/0");

    $response->assertOk();
    expect(LibraryEntry::where('book_id', $book->id)->count())->toBe(0);
});

it('only shows the authenticated users own entries in My Library, never another users', function () {
    $userA = User::factory()->create();
    $userA->assignRole('admin');
    $userB = User::factory()->create();
    $userB->assignRole('admin');

    $bookA = Book::factory()->create(['title' => 'Book Owned By A', 'status' => 'ready']);
    $bookB = Book::factory()->create(['title' => 'Book Owned By B', 'status' => 'ready']);

    LibraryEntry::create(['user_id' => $userA->id, 'book_id' => $bookA->id, 'status' => 'reading']);
    LibraryEntry::create(['user_id' => $userB->id, 'book_id' => $bookB->id, 'status' => 'reading']);

    $response = $this->actingAs($userA)->get('/admin/library/my');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Library/Index')
        ->has('myLibrary.data', 1)
        ->where('myLibrary.data.0.title', 'Book Owned By A'));
});

it('filters My Library by status tab', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $reading = Book::factory()->create(['title' => 'Reading Book', 'status' => 'ready']);
    $completed = Book::factory()->create(['title' => 'Completed Book', 'status' => 'ready']);

    LibraryEntry::create(['user_id' => $user->id, 'book_id' => $reading->id, 'status' => 'reading']);
    LibraryEntry::create(['user_id' => $user->id, 'book_id' => $completed->id, 'status' => 'completed']);

    $response = $this->actingAs($user)->get('/admin/library/my?tab=completed');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('myLibrary.data', 1)
        ->where('myLibrary.data.0.title', 'Completed Book'));
});

it('searches My Library by title', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $bookOne = Book::factory()->create(['title' => 'The Great Adventure', 'status' => 'ready']);
    $bookTwo = Book::factory()->create(['title' => 'A Different Story', 'status' => 'ready']);

    LibraryEntry::create(['user_id' => $user->id, 'book_id' => $bookOne->id, 'status' => 'reading']);
    LibraryEntry::create(['user_id' => $user->id, 'book_id' => $bookTwo->id, 'status' => 'reading']);

    $response = $this->actingAs($user)->get('/admin/library/my?search=Great');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('myLibrary.data', 1)
        ->where('myLibrary.data.0.title', 'The Great Adventure'));
});

it('computes chapters_read and chapters_total correctly', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $book = Book::factory()->create(['title' => 'Ten Chapter Book', 'status' => 'ready']);

    $chapters = collect(range(0, 9))->map(
        fn (int $i) => Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => $i])
    );
    $chapterAtIndex4 = $chapters->firstWhere('sort_order', 4);

    LibraryEntry::create([
        'user_id' => $user->id,
        'book_id' => $book->id,
        'status' => 'reading',
        'last_chapter_id' => $chapterAtIndex4->id,
        'last_read_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/admin/library/my');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('myLibrary.data.0.chapters_read', 5)
        ->where('myLibrary.data.0.chapters_total', 10));
});

it('cannot have two library_entries rows for the same user and book due to the unique constraint', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create(['status' => 'ready']);

    LibraryEntry::create(['user_id' => $user->id, 'book_id' => $book->id, 'status' => 'reading']);

    expect(fn () => LibraryEntry::create(['user_id' => $user->id, 'book_id' => $book->id, 'status' => 'completed']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
