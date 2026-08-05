<?php

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use App\Support\RagConnectionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

// Tests that create real ChatThread/ChatMessage rows need the `rag` Postgres connection
// to actually be reachable; skip only those (not the rag-unavailable test below, which
// must always run — mirrors the pattern in LibraryChatUnavailableTest.php).
function skipIfRagUnavailable(): void
{
    if (! RagConnectionGuard::available()) {
        test()->markTestSkipped('The `rag` Postgres connection is not configured/reachable.');
    }
}

// ChatThread/ChatMessage live on the real `rag` Postgres connection (rag_test schema),
// which RefreshDatabase's sqlite transaction rollback does not touch. Clean up explicitly
// so these tests don't leave rows behind in the shared database.
function deleteRagThreadForHistory(int $threadId): void
{
    DB::connection('rag')->table('chat_messages')->where('thread_id', $threadId)->delete();
    DB::connection('rag')->table('chat_threads')->where('id', $threadId)->delete();
}

it('returns empty history for a user with no threads', function () {
    skipIfRagUnavailable();

    $user = User::factory()->create();
    $user->assignRole('admin');

    $response = $this->actingAs($user)->getJson('/admin/library/chat/history');

    $response->assertOk();
    $response->assertExactJson(['thread_id' => null, 'messages' => []]);
});

it('returns the users most recent thread with all its messages in order', function () {
    skipIfRagUnavailable();

    $user = User::factory()->create();
    $user->assignRole('admin');

    $thread = ChatThread::create(['user_id' => $user->id, 'title' => 'A conversation']);

    try {
        $m1 = ChatMessage::create(['thread_id' => $thread->id, 'role' => 'user', 'content' => 'First question?']);
        $m2 = ChatMessage::create([
            'thread_id' => $thread->id,
            'role' => 'assistant',
            'content' => 'First answer.',
            'citations' => [['book_id' => 1, 'book_title' => 'Some Book']],
        ]);
        $m3 = ChatMessage::create(['thread_id' => $thread->id, 'role' => 'user', 'content' => 'Second question?']);

        $response = $this->actingAs($user)->getJson('/admin/library/chat/history');

        $response->assertOk();
        $response->assertJson([
            'thread_id' => $thread->id,
            'messages' => [
                ['role' => 'user', 'content' => 'First question?', 'citations' => []],
                ['role' => 'assistant', 'content' => 'First answer.', 'citations' => [['book_id' => 1, 'book_title' => 'Some Book']]],
                ['role' => 'user', 'content' => 'Second question?', 'citations' => []],
            ],
        ]);
    } finally {
        deleteRagThreadForHistory($thread->id);
    }
});

it('never returns another users thread history', function () {
    skipIfRagUnavailable();

    $userA = User::factory()->create();
    $userA->assignRole('admin');
    $userB = User::factory()->create();
    $userB->assignRole('admin');

    $threadA = ChatThread::create(['user_id' => $userA->id, 'title' => 'A conversation']);
    $threadB = ChatThread::create(['user_id' => $userB->id, 'title' => 'B conversation']);

    try {
        ChatMessage::create(['thread_id' => $threadA->id, 'role' => 'user', 'content' => 'From A']);
        ChatMessage::create(['thread_id' => $threadB->id, 'role' => 'user', 'content' => 'From B — secret']);

        $response = $this->actingAs($userA)->getJson('/admin/library/chat/history');

        $response->assertOk();
        $response->assertJson(['thread_id' => $threadA->id]);
        $response->assertJsonMissing(['content' => 'From B — secret']);
        expect(collect($response->json('messages'))->pluck('content'))->not->toContain('From B — secret');
    } finally {
        deleteRagThreadForHistory($threadA->id);
        deleteRagThreadForHistory($threadB->id);
    }
});

it('returns empty history gracefully when rag is unavailable, not a 500', function () {
    $original = config('database.connections.rag');
    config(['database.connections.rag.host' => '127.0.0.1', 'database.connections.rag.port' => 1]);
    DB::purge('rag');

    $property = new ReflectionProperty(RagConnectionGuard::class, 'available');
    $property->setAccessible(true);
    $property->setValue(null, null);

    try {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->getJson('/admin/library/chat/history');

        $response->assertOk();
        $response->assertExactJson(['thread_id' => null, 'messages' => []]);
    } finally {
        config(['database.connections.rag' => $original]);
        DB::purge('rag');
        $property->setValue(null, null);
    }
});

it('deletes a users own chat thread and its messages', function () {
    skipIfRagUnavailable();

    $user = User::factory()->create();
    $user->assignRole('admin');

    $thread = ChatThread::create(['user_id' => $user->id, 'title' => 'A conversation']);
    ChatMessage::create(['thread_id' => $thread->id, 'role' => 'user', 'content' => 'First question?']);
    ChatMessage::create(['thread_id' => $thread->id, 'role' => 'assistant', 'content' => 'First answer.']);

    try {
        $response = $this->actingAs($user)->deleteJson("/admin/library/chat/{$thread->id}");

        $response->assertOk();
        $response->assertExactJson(['deleted' => true]);

        expect(ChatThread::find($thread->id))->toBeNull();
        expect(ChatMessage::where('thread_id', $thread->id)->count())->toBe(0);
    } finally {
        deleteRagThreadForHistory($thread->id);
    }
});

it('does not delete another users thread and does not error', function () {
    skipIfRagUnavailable();

    $userA = User::factory()->create();
    $userA->assignRole('admin');
    $userB = User::factory()->create();
    $userB->assignRole('admin');

    $threadB = ChatThread::create(['user_id' => $userB->id, 'title' => 'B conversation']);
    ChatMessage::create(['thread_id' => $threadB->id, 'role' => 'user', 'content' => 'From B — secret']);

    try {
        $response = $this->actingAs($userA)->deleteJson("/admin/library/chat/{$threadB->id}");

        $response->assertOk();
        $response->assertExactJson(['deleted' => true]);

        expect(ChatThread::find($threadB->id))->not->toBeNull();
        expect(ChatMessage::where('thread_id', $threadB->id)->count())->toBe(1);
    } finally {
        deleteRagThreadForHistory($threadB->id);
    }
});

it('is a no-op when the thread does not exist', function () {
    skipIfRagUnavailable();

    $user = User::factory()->create();
    $user->assignRole('admin');

    $response = $this->actingAs($user)->deleteJson('/admin/library/chat/999999');

    $response->assertOk();
    $response->assertExactJson(['deleted' => true]);
});
