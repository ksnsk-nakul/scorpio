<?php

use App\Support\RagConnectionGuard;
use Illuminate\Support\Facades\DB;

function resetRagConnectionGuardCache(): void
{
    $property = new ReflectionProperty(RagConnectionGuard::class, 'available');
    $property->setAccessible(true);
    $property->setValue(null, null);
}

it('reports unavailable, without throwing, when the rag connection cannot connect', function () {
    $original = config('database.connections.rag');

    config(['database.connections.rag.host' => '127.0.0.1', 'database.connections.rag.port' => 1]);
    DB::purge('rag');
    resetRagConnectionGuardCache();

    try {
        expect(RagConnectionGuard::available())->toBeFalse();
    } finally {
        config(['database.connections.rag' => $original]);
        DB::purge('rag');
        resetRagConnectionGuardCache();
    }
});

it('caches the result so it only attempts the connection once', function () {
    $original = config('database.connections.rag');

    config(['database.connections.rag.host' => '127.0.0.1', 'database.connections.rag.port' => 1]);
    DB::purge('rag');
    resetRagConnectionGuardCache();

    try {
        expect(RagConnectionGuard::available())->toBeFalse();
        // Second call must not re-attempt a real connection (which would be slow/throw again) —
        // it should return the cached false immediately.
        expect(RagConnectionGuard::available())->toBeFalse();
    } finally {
        config(['database.connections.rag' => $original]);
        DB::purge('rag');
        resetRagConnectionGuardCache();
    }
});
