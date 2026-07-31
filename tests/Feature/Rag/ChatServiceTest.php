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

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'generateContent')
                && str_contains($request['contents'][0]['parts'][0]['text'], 'First question?');
        });
    } finally {
        DB::connection('rag')->table('chat_messages')->where('thread_id', $first['thread']->id)->delete();
        DB::connection('rag')->table('chat_threads')->where('id', $first['thread']->id)->delete();
    }
});
