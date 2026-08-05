<?php

namespace App\Jobs;

use App\Models\Topic;
use App\Services\GeminiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateTopicSlidesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Topic $topic) {}

    public function handle(GeminiClient $gemini): void
    {
        $slides = $this->topic->material('slides');
        if (! $slides) {
            return;
        }

        $notes = $this->topic->material('notes');
        if (! $notes || ! trim((string) $notes->content)) {
            $slides->update(['status' => 'failed']);
            return;
        }

        $prompt = <<<PROMPT
        Read this topic's notes below. Summarize it into a markdown slide deck for
        a beginner-friendly coding course. Rules:
        - One slide per major concept or section heading in the notes, in the same order.
        - Each slide: a short title line, then 3-6 bullet points, plain language.
        - Include short code snippets from the notes verbatim where they illustrate
          a concept - don't invent new examples.
        - Separate slides with a line containing exactly "---".
        - Output ONLY the slide deck, no commentary before or after.

        Notes:
        {$notes->content}
        PROMPT;

        try {
            $deck = $gemini->generate($prompt);
            $slides->update(['content' => $deck, 'status' => 'ready']);
        } catch (Throwable $e) {
            Log::warning('GenerateTopicSlidesJob: failed to generate slides', [
                'topic_id' => $this->topic->id,
                'error' => $e->getMessage(),
            ]);
            $slides->update(['status' => 'failed']);
        }
    }
}
