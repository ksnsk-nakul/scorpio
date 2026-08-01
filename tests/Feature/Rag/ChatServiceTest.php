<?php

use App\Models\ChatThread;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('creates a new thread, asks a question, and persists both messages with citations', function () {
    $vector = '[' . implode(',', array_fill(0, 768, 0.1)) . ']';

    try {
        DB::connection('rag')->statement(
            'insert into book_chunks (book_id, chapter_id, chunk_index, content, embedding) values (?, ?, ?, ?, ?::vector)',
            [999903, 999903, 0, 'The sky is blue because of Rayleigh scattering.', $vector]
        );

        Http::fake([
            'generativelanguage.googleapis.com/*embedContent*' => Http::response([
                'embedding' => ['values' => array_fill(0, 768, 0.1)],
            ], 200),
            'generativelanguage.googleapis.com/*generateContent*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'The sky is blue due to Rayleigh scattering.']]]]],
            ], 200),
        ]);

        $result = (new ChatService())->ask(userId: 1, threadId: null, question: 'Why is the sky blue?');

        expect($result['thread'])->toBeInstanceOf(ChatThread::class);
        expect($result['thread']->title)->toBe('Why is the sky blue?');
        expect($result['thread']->messages)->toHaveCount(2);
        expect($result['thread']->messages[0]->role)->toBe('user');
        expect($result['thread']->messages[1]->role)->toBe('assistant');
        expect($result['thread']->messages[1]->citations)->not->toBeEmpty();
    } finally {
        DB::connection('rag')->table('book_chunks')->where('book_id', 999903)->delete();
    }
});

it('continues an existing thread using its prior messages as context', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*embedContent*' => Http::response([
            'embedding' => ['values' => array_fill(0, 768, 0.1)],
        ], 200),
        'generativelanguage.googleapis.com/*generateContent*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Second answer.']]]]],
        ], 200),
    ]);

    $service = new ChatService();
    $first = $service->ask(userId: 1, threadId: null, question: 'First question?');

    try {
        $second = $service->ask(userId: 1, threadId: $first['thread']->id, question: 'Follow-up question?');

        expect($second['thread']->id)->toBe($first['thread']->id);
        expect($second['thread']->messages)->toHaveCount(4);

        // Assert specifically on the SECOND generateContent call (the follow-up), not
        // "any" recorded request — the first call's own current question also contains
        // the literal text "First question?", so a plain Http::assertSent() here would
        // pass even if history were never included at all.
        $generateCalls = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'generateContent'))
            ->values();

        expect($generateCalls)->toHaveCount(2);
        $secondCallPrompt = $generateCalls[1][0]['contents'][0]['parts'][0]['text'];
        expect($secondCallPrompt)->toContain('CONVERSATION SO FAR')
            ->toContain('First question?');
    } finally {
        DB::connection('rag')->table('chat_messages')->where('thread_id', $first['thread']->id)->delete();
        DB::connection('rag')->table('chat_threads')->where('id', $first['thread']->id)->delete();
    }
});

it('refuses to let one user continue another user\'s thread', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*embedContent*' => Http::response([
            'embedding' => ['values' => array_fill(0, 768, 0.1)],
        ], 200),
        'generativelanguage.googleapis.com/*generateContent*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'An answer.']]]]],
        ], 200),
    ]);

    $service = new ChatService();
    $ownersThread = $service->ask(userId: 1, threadId: null, question: 'A private question?');

    try {
        $service->ask(userId: 2, threadId: $ownersThread['thread']->id, question: 'Snooping?');
    } finally {
        DB::connection('rag')->table('chat_messages')->where('thread_id', $ownersThread['thread']->id)->delete();
        DB::connection('rag')->table('chat_threads')->where('id', $ownersThread['thread']->id)->delete();
    }
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

it('deduplicates citations when multiple retrieved chunks share the same book and chapter', function () {
    $vector = '[' . implode(',', array_fill(0, 768, 0.1)) . ']';

    try {
        // two chunks, same book_id/chapter_id, different chunk_index — RetrievalService
        // will return both as separate rows, but the citation list should collapse to one
        DB::connection('rag')->statement(
            'insert into book_chunks (book_id, chapter_id, chunk_index, content, embedding) values (?, ?, ?, ?, ?::vector)',
            [999904, 999904, 0, 'First chunk of the same chapter.', $vector]
        );
        DB::connection('rag')->statement(
            'insert into book_chunks (book_id, chapter_id, chunk_index, content, embedding) values (?, ?, ?, ?, ?::vector)',
            [999904, 999904, 1, 'Second chunk of the same chapter.', $vector]
        );

        Http::fake([
            'generativelanguage.googleapis.com/*embedContent*' => Http::response([
                'embedding' => ['values' => array_fill(0, 768, 0.1)],
            ], 200),
            'generativelanguage.googleapis.com/*generateContent*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'An answer.']]]]],
            ], 200),
        ]);

        $result = (new ChatService())->ask(userId: 1, threadId: null, question: 'A question?');

        expect($result['thread']->messages[1]->citations)->toHaveCount(1);
    } finally {
        DB::connection('rag')->table('book_chunks')->where('book_id', 999904)->delete();
    }
});

it('does not include the current question twice in the prompt sent to Gemini', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*embedContent*' => Http::response([
            'embedding' => ['values' => array_fill(0, 768, 0.1)],
        ], 200),
        'generativelanguage.googleapis.com/*generateContent*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'An answer.']]]]],
        ], 200),
    ]);

    (new ChatService())->ask(userId: 1, threadId: null, question: 'UNIQUE_MARKER_QUESTION');

    $generateCalls = collect(Http::recorded())
        ->filter(fn ($pair) => str_contains($pair[0]->url(), 'generateContent'))
        ->values();

    $prompt = $generateCalls[0][0]['contents'][0]['parts'][0]['text'];
    expect(substr_count($prompt, 'UNIQUE_MARKER_QUESTION'))->toBe(1);
});
