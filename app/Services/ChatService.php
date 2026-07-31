<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use Illuminate\Support\Str;

class ChatService
{
    private const SYSTEM_PROMPT = <<<'TEXT'
        You are a helpful librarian assistant. Answer the reader's question using ONLY the
        excerpts provided below. If the excerpts don't contain enough information to answer,
        say so plainly rather than guessing or using outside knowledge. When you use an excerpt,
        mention which book it came from.
        TEXT;

    /** @return array{thread: ChatThread} */
    public function ask(int $userId, ?int $threadId, string $question): array
    {
        $thread = $threadId
            ? ChatThread::findOrFail($threadId)
            : ChatThread::create(['user_id' => $userId, 'title' => Str::limit($question, 80, '')]);

        ChatMessage::create([
            'thread_id' => $thread->id,
            'role' => 'user',
            'content' => $question,
        ]);

        $retrieval = app(RetrievalService::class);
        $chunks = $retrieval->search($question);

        $prompt = $this->buildPrompt($thread, $chunks, $question);

        $gemini = app(GeminiClient::class);
        $answer = $gemini->generate($prompt);

        $citations = collect($chunks)
            ->unique(fn ($c) => $c['book_id'] . ':' . $c['chapter_id'])
            ->map(fn ($c) => [
                'book_id' => $c['book_id'],
                'chapter_id' => $c['chapter_id'],
                'book_title' => $c['book_title'],
                'chapter_title' => $c['chapter_title'],
            ])
            ->values()
            ->all();

        ChatMessage::create([
            'thread_id' => $thread->id,
            'role' => 'assistant',
            'content' => $answer,
            'citations' => $citations,
        ]);

        return ['thread' => $thread->fresh('messages')];
    }

    private function buildPrompt(ChatThread $thread, array $chunks, string $question): string
    {
        $excerpts = collect($chunks)
            ->map(fn ($c) => "From \"{$c['book_title']}\"" . ($c['chapter_title'] ? " ({$c['chapter_title']})" : '') . ":\n{$c['content']}")
            ->implode("\n\n---\n\n");

        $history = $thread->messages()
            ->get()
            ->map(fn (ChatMessage $m) => strtoupper($m->role) . ': ' . $m->content)
            ->implode("\n\n");

        $sections = array_filter([
            self::SYSTEM_PROMPT,
            $excerpts !== '' ? "EXCERPTS:\n\n{$excerpts}" : "EXCERPTS: (none found)",
            $history !== '' ? "CONVERSATION SO FAR:\n\n{$history}" : null,
            "QUESTION: {$question}",
        ]);

        return implode("\n\n", $sections);
    }
}
