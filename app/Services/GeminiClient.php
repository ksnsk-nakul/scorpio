<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiClient
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';
    private const EMBEDDING_MODEL = 'gemini-embedding-001';
    private const EMBEDDING_DIMENSIONS = 768;
    private const GENERATION_MODEL = 'gemini-2.5-flash';

    private function apiKey(): string
    {
        $key = config('services.gemini.key');
        if (! $key) {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }
        return $key;
    }

    /** @return float[] */
    public function embed(string $text): array
    {
        $response = Http::post(
            self::BASE_URL . '/' . self::EMBEDDING_MODEL . ':embedContent?key=' . $this->apiKey(),
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
        $response = Http::post(
            self::BASE_URL . '/' . self::GENERATION_MODEL . ':generateContent?key=' . $this->apiKey(),
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
