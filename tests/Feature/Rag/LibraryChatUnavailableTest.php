<?php

use App\Models\User;
use App\Support\RagConnectionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

// Deliberately forces RagConnectionGuard::available() to false for these tests —
// the opposite of the sibling LibraryChatControllerTest.php, which skips itself
// when the rag connection isn't reachable. This file specifically exercises what
// happens when it isn't, so it must never inherit that skip.
function forceRagUnavailable(): callable
{
    $original = config('database.connections.rag');
    config(['database.connections.rag.host' => '127.0.0.1', 'database.connections.rag.port' => 1]);
    DB::purge('rag');

    $property = new ReflectionProperty(RagConnectionGuard::class, 'available');
    $property->setAccessible(true);
    $property->setValue(null, null);

    return function () use ($original, $property) {
        config(['database.connections.rag' => $original]);
        DB::purge('rag');
        $property->setValue(null, null);
    };
}

it('rejects asking a question with a clean 503 JSON error instead of a 500 when rag is unreachable', function () {
    $restore = forceRagUnavailable();

    try {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->postJson('/admin/library/chat', ['question' => 'Is anyone there?']);

        $response->assertStatus(503);
        $response->assertJsonStructure(['message']);
    } finally {
        $restore();
    }
});
