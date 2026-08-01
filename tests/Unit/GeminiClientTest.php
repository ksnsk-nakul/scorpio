<?php

use App\Services\GeminiClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.gemini.key' => 'test-key']);
});

it('embeds text and returns a 768-length float vector', function () {
    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent*' => Http::response([
            'embedding' => ['values' => array_fill(0, 768, 0.123)],
        ], 200),
    ]);

    $embedding = (new GeminiClient())->embed('some chapter text');

    expect($embedding)->toHaveCount(768);
    expect($embedding[0])->toBe(0.123);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'gemini-embedding-001:embedContent')
            && $request['outputDimensionality'] === 768
            && $request['content']['parts'][0]['text'] === 'some chapter text';
    });
});

it('throws when the embed call fails', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'bad request']], 400),
    ]);

    (new GeminiClient())->embed('text');
})->throws(RuntimeException::class);

it('generates text from a prompt', function () {
    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'The answer is 42.']]]],
            ],
        ], 200),
    ]);

    $answer = (new GeminiClient())->generate('What is the answer?');

    expect($answer)->toBe('The answer is 42.');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'gemini-flash-latest:generateContent')
            && $request['contents'][0]['parts'][0]['text'] === 'What is the answer?';
    });
});

it('throws when the generate call fails', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'quota exceeded']], 429),
    ]);

    (new GeminiClient())->generate('question');
})->throws(RuntimeException::class);
