<?php

use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);
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
    $this->get('/admin/library/chat')->assertRedirect('/login');
});

it('lets an admin view the chat index with their threads', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $thread = ChatThread::create(['user_id' => $admin->id, 'title' => 'My thread']);

    try {
        $this->actingAs($admin)
            ->get('/admin/library/chat')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Library/Chat/Index')
                ->has('threads', 1));
    } finally {
        deleteRagThread($thread->id);
    }
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
        $response = $this->actingAs($admin)->post('/admin/library/chat', ['question' => 'Test question?']);

        $response->assertRedirect();
        expect(ChatThread::where('user_id', $admin->id)->count())->toBe(1);
    } finally {
        ChatThread::where('user_id', $admin->id)->get()->each(fn (ChatThread $t) => deleteRagThread($t->id));
    }
});

it('blocks non-admin/editor/viewer roles', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/library/chat')->assertForbidden();
});

it('never lets a user post to another users thread_id and read their history', function () {
    $victim = User::factory()->create();
    $victim->assignRole('admin');
    $attacker = User::factory()->create();
    $attacker->assignRole('admin');

    $victimThread = ChatThread::create(['user_id' => $victim->id, 'title' => 'Private']);

    try {
        $response = $this->actingAs($attacker)->post('/admin/library/chat', [
            'question' => 'trying to read your thread',
            'thread_id' => $victimThread->id,
        ]);

        // The controller must not silently succeed by appending the attacker's message to
        // the victim's thread. Either a 404 (ChatService's ownership-scoped lookup throwing
        // ModelNotFoundException) or a validation/authorization failure is acceptable — what's
        // NOT acceptable is a 200/redirect that succeeded against the victim's thread.
        $response->assertStatus(404);

        // Belt-and-suspenders: assert the attack didn't actually leave a trace in the
        // victim's thread either (the request failing with 404 isn't proof enough on its
        // own if some other bug appended the message before the exception).
        expect($victimThread->fresh('messages')->messages)->toHaveCount(0);
    } finally {
        deleteRagThread($victimThread->id);
    }
});

it('403s when a user tries to view another users thread', function () {
    $victim = User::factory()->create();
    $victim->assignRole('admin');
    $attacker = User::factory()->create();
    $attacker->assignRole('admin');

    $victimThread = ChatThread::create(['user_id' => $victim->id, 'title' => 'Private']);

    try {
        $this->actingAs($attacker)
            ->get("/admin/library/chat/{$victimThread->id}")
            ->assertForbidden();
    } finally {
        deleteRagThread($victimThread->id);
    }
});
