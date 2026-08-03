<?php

use App\Models\ChatThread;
use App\Models\User;
use App\Support\RagConnectionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);
beforeEach(function () {
    if (! RagConnectionGuard::available()) {
        $this->markTestSkipped('The `rag` Postgres connection is not configured/reachable.');
    }
});
beforeEach(fn () => $this->seed());

// ChatThread/ChatMessage live on the real `rag` Postgres connection (rag_test schema),
// which RefreshDatabase's sqlite transaction rollback does not touch. Clean up explicitly
// so these tests don't leave rows behind in the shared database.
function deleteRagThread(int $threadId): void
{
    DB::connection('rag')->table('chat_messages')->where('thread_id', $threadId)->delete();
    DB::connection('rag')->table('chat_threads')->where('id', $threadId)->delete();
}

it('requires authentication', function () {
    $this->post('/admin/library/chat', ['question' => 'Test question?'])->assertRedirect('/login');
});

it('lets an admin post a question and get a persisted answer', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*embedContent*' => Http::response([
            'embedding' => ['values' => array_fill(0, 768, 0.1)],
        ], 200),
        'generativelanguage.googleapis.com/*generateContent*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'An answer.']]]]],
        ], 200),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    try {
        $response = $this->actingAs($admin)->postJson('/admin/library/chat', ['question' => 'Test question?']);

        $response->assertOk();
        $response->assertJson([
            'answer' => 'An answer.',
            'citations' => [],
        ]);
        $response->assertJsonStructure(['thread_id', 'answer', 'citations']);
        expect(ChatThread::where('user_id', $admin->id)->count())->toBe(1);
    } finally {
        ChatThread::where('user_id', $admin->id)->get()->each(fn (ChatThread $t) => deleteRagThread($t->id));
    }
});

it('persists citations with a book_slug and chapter_sort_order the UI can link to', function () {
    $vector = '['.implode(',', array_fill(0, 768, 0.1)).']';

    $book = \App\Models\Book::factory()->create(['status' => 'ready']);
    $chapter = \App\Models\Chapter::factory()->create(['book_id' => $book->id, 'sort_order' => 3]);

    DB::connection('rag')->statement(
        'insert into book_chunks (book_id, chapter_id, chunk_index, content, embedding) values (?, ?, ?, ?, ?::vector)',
        [$book->id, $chapter->id, 0, 'Some indexed content.', $vector]
    );

    Http::fake([
        'generativelanguage.googleapis.com/*embedContent*' => Http::response([
            'embedding' => ['values' => array_fill(0, 768, 0.1)],
        ], 200),
        'generativelanguage.googleapis.com/*generateContent*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'An answer with a citation.']]]]],
        ], 200),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    try {
        $response = $this->actingAs($admin)->postJson('/admin/library/chat', ['question' => 'Test question?']);

        $response->assertOk();
        $response->assertJsonPath('citations.0.book_slug', $book->slug);
        $response->assertJsonPath('citations.0.chapter_sort_order', 3);
    } finally {
        ChatThread::where('user_id', $admin->id)->get()->each(fn (ChatThread $t) => deleteRagThread($t->id));
        DB::connection('rag')->table('book_chunks')->where('book_id', $book->id)->delete();
    }
});

it('blocks non-admin/editor/viewer roles', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/admin/library/chat', ['question' => 'Test question?'])->assertForbidden();
});

it('never lets a user post to another users thread_id and read their history', function () {
    $victim = User::factory()->create();
    $victim->assignRole('admin');
    $attacker = User::factory()->create();
    $attacker->assignRole('admin');

    $victimThread = ChatThread::create(['user_id' => $victim->id, 'title' => 'Private']);

    try {
        $response = $this->actingAs($attacker)->postJson('/admin/library/chat', [
            'question' => 'trying to read your thread',
            'thread_id' => $victimThread->id,
        ]);

        // The controller must not silently succeed by appending the attacker's message to
        // the victim's thread. A 404 (ChatService's ownership-scoped lookup throwing
        // ModelNotFoundException) is the expected outcome — what's NOT acceptable is a 200
        // response that succeeded against the victim's thread.
        $response->assertStatus(404);

        // Belt-and-suspenders: assert the attack didn't actually leave a trace in the
        // victim's thread either (the request failing with 404 isn't proof enough on its
        // own if some other bug appended the message before the exception).
        expect($victimThread->fresh('messages')->messages)->toHaveCount(0);
    } finally {
        deleteRagThread($victimThread->id);
    }
});
