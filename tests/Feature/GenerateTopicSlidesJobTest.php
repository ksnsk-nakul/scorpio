<?php

use App\Jobs\GenerateTopicSlidesJob;
use App\Models\Course;
use App\Models\Material;
use App\Models\Module;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeTopicWithNotes(string $notes): Topic
{
    $course = Course::create(['code' => 'C001-HTML-101', 'title' => 'Intro to HTML', 'source_path' => '/tmp/x']);
    $module = Module::create(['course_id' => $course->id, 'title' => 'Fundamentals', 'slug' => 'fundamentals', 'sort_order' => 0]);
    $topic = Topic::create(['module_id' => $module->id, 'title' => 'Intro', 'slug' => 'intro', 'sort_order' => 0]);
    Material::create(['topic_id' => $topic->id, 'type' => 'notes', 'content' => $notes, 'download_policy' => 'downloadable', 'status' => 'ready']);
    Material::create(['topic_id' => $topic->id, 'type' => 'slides', 'download_policy' => 'view_only', 'status' => 'generating']);
    return $topic;
}

it('generates a slide deck from notes and stores it on the slides material', function () {
    $topic = makeTopicWithNotes('# What is HTML\n\nHTML is a markup language.');

    Http::fake([
        'generativelanguage.googleapis.com/*generateContent*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => "# What is HTML\n- Markup language\n---\n# Summary\n- Structure not style"]]]]],
        ], 200),
    ]);

    (new GenerateTopicSlidesJob($topic))->handle(app(\App\Services\GeminiClient::class));

    $slides = $topic->fresh()->material('slides');
    expect($slides->status)->toBe('ready')
        ->and($slides->content)->toContain('---')
        ->and($slides->content)->toContain('Markup language');
});

it('marks the slides material as failed, without throwing, when Gemini errors', function () {
    $topic = makeTopicWithNotes('# Notes');

    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'quota exceeded']], 429)]);

    (new GenerateTopicSlidesJob($topic))->handle(app(\App\Services\GeminiClient::class));

    expect($topic->fresh()->material('slides')->status)->toBe('failed');
});

it('does nothing if the topic has no notes material yet', function () {
    $course = Course::create(['code' => 'C001-HTML-101', 'title' => 'Intro', 'source_path' => '/tmp/x']);
    $module = Module::create(['course_id' => $course->id, 'title' => 'F', 'slug' => 'f', 'sort_order' => 0]);
    $topic = Topic::create(['module_id' => $module->id, 'title' => 'T', 'slug' => 't', 'sort_order' => 0]);
    Material::create(['topic_id' => $topic->id, 'type' => 'slides', 'download_policy' => 'view_only', 'status' => 'generating']);

    Http::fake();

    (new GenerateTopicSlidesJob($topic))->handle(app(\App\Services\GeminiClient::class));

    expect($topic->fresh()->material('slides')->status)->toBe('failed');
    Http::assertNothingSent();
});
