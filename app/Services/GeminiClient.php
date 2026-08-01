<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiClient
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';
    private const EMBEDDING_MODEL = 'gemini-embedding-001';
    private const EMBEDDING_DIMENSIONS = 768;
    // 'gemini-2.5-flash' returns "no longer available to new users" on this API key's
    // project (confirmed via a live call during Task 9 verification), and the dated
    // 2.0-flash models have a free-tier quota of 0 for this project. 'gemini-flash-latest'
    // is Google's rolling alias to the current recommended flash model and works.
    private const GENERATION_MODEL = 'gemini-flash-latest';

    private function apiKey(): string
    {
        $key = config('services.gemini.key');
        if (! $key) {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }
        return $key;
    }

    /** Shared pending request: header auth (not query string, to keep the key out of logs/URLs),
     *  a bounded timeout, and a retry with backoff on transient rate-limit/server errors. */
    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders(['x-goog-api-key' => $this->apiKey()])
            ->timeout(30)
            ->retry(3, 2000, fn ($exception) => $exception instanceof \Illuminate\Http\Client\RequestException
                && in_array($exception->response->status(), [429, 500, 502, 503, 504], true), throw: false);
    }

    /** @return float[] */
    public function embed(string $text): array
    {
        $response = $this->http()->post(
            self::BASE_URL . '/' . self::EMBEDDING_MODEL . ':embedContent',
            [
                'content' => ['parts' => [['text' => $text]]],
                'outputDimensionality' => self::EMBEDDING_DIMENSIONS,
            ]
        );

        if ($response->failed()) {
            throw new RuntimeException('Gemini embed request failed: ' . ($response->json('error.message') ?? $response->body()));
        }

        $values = $response->json('embedding.values');
        if (! is_array($values)) {
            throw new RuntimeException('Gemini embed response missing embedding.values.');
        }

        return $values;
    }

    public function generate(string $prompt): string
    {
        $response = $this->http()->post(
            self::BASE_URL . '/' . self::GENERATION_MODEL . ':generateContent',
            [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]
        );

        if ($response->failed()) {
            throw new RuntimeException('Gemini generate request failed: ' . ($response->json('error.message') ?? $response->body()));
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($text)) {
            throw new RuntimeException('Gemini generate response missing candidate text.');
        }

        return $text;
    }
}
