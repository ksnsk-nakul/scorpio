<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Chapter;
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
            ? ChatThread::where('user_id', $userId)->findOrFail($threadId)
            : ChatThread::create(['user_id' => $userId, 'title' => Str::limit($question, 80, '')]);

        // Read history BEFORE persisting the current question, so buildPrompt()'s
        // "CONVERSATION SO FAR" and its trailing "QUESTION:" line never both contain
        // the same current-turn question.
        $priorMessages = $thread->messages()->get();

        ChatMessage::create([
            'thread_id' => $thread->id,
            'role' => 'user',
            'content' => $question,
        ]);

        $retrieval = app(RetrievalService::class);
        $chunks = $retrieval->search($question);

        $prompt = $this->buildPrompt($priorMessages, $chunks, $question);

        $gemini = app(GeminiClient::class);
        $answer = $gemini->generate($prompt);

        $uniqueChunks = collect($chunks)->unique(fn ($c) => $c['book_id'] . ':' . $c['chapter_id']);

        // Batch-load slugs/sort_orders (not returned by RetrievalService::search()) so
        // citation links can point at a real reader URL instead of a broken one.
        $books = Book::whereIn('id', $uniqueChunks->pluck('book_id')->unique())->get()->keyBy('id');
        $chapters = Chapter::whereIn('id', $uniqueChunks->pluck('chapter_id')->unique())->get()->keyBy('id');

        $citations = $uniqueChunks
            ->map(fn ($c) => [
                'book_id' => $c['book_id'],
                'chapter_id' => $c['chapter_id'],
                'book_title' => $c['book_title'],
                'chapter_title' => $c['chapter_title'],
                'book_slug' => $books->get($c['book_id'])?->slug,
                'chapter_sort_order' => $chapters->get($c['chapter_id'])?->sort_order,
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

    /** @param \Illuminate\Support\Collection<int, ChatMessage> $priorMessages */
    private function buildPrompt($priorMessages, array $chunks, string $question): string
    {
        $excerpts = collect($chunks)
            ->map(fn ($c) => "From \"{$c['book_title']}\"" . ($c['chapter_title'] ? " ({$c['chapter_title']})" : '') . ":\n{$c['content']}")
            ->implode("\n\n---\n\n");

        $history = $priorMessages
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
