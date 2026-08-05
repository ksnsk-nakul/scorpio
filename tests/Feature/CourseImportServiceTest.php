<?php

use App\Models\Course;
use App\Services\CourseImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CourseFixtureBuilder;

uses(RefreshDatabase::class);

beforeEach(fn () => Queue::fake());

it('imports course metadata and pricing tiers, converting rupee/dollar strings to paise/cents', function () {
    $tmp = sys_get_temp_dir() . '/edtech-test-' . uniqid();
    $courseDir = CourseFixtureBuilder::build(
        tmpDir: $tmp,
        code: 'C001-HTML-101',
        courseTitle: 'Introduction to HTML',
        description: 'Learn HTML from scratch.',
        modules: [],
        tiers: [
            ['name' => 'Free preview', 'included' => 'Sample topic', 'price_inr' => 'Free', 'price_usd' => 'Free'],
            ['name' => 'Self-Paced Pro', 'included' => 'Full course', 'price_inr' => '₹5,999', 'price_usd' => '$149'],
        ],
    );

    $course = (new CourseImportService())->importCourse($courseDir, 'C001-HTML-101');

    expect($course->code)->toBe('C001-HTML-101')
        ->and($course->title)->toBe('Introduction to HTML')
        ->and($course->description)->toBe('Learn HTML from scratch.')
        ->and($course->status)->toBe('ready')
        ->and($course->pricingTiers)->toHaveCount(2);

    $free = $course->pricingTiers->firstWhere('name', 'Free preview');
    expect($free->price_inr_paise)->toBe(0)->and($free->price_usd_cents)->toBe(0);

    $pro = $course->pricingTiers->firstWhere('name', 'Self-Paced Pro');
    expect($pro->price_inr_paise)->toBe(599900)->and($pro->price_usd_cents)->toBe(14900);
});

it('imports modules, topics, and their materials with correct download policy', function () {
    $tmp = sys_get_temp_dir() . '/edtech-test-' . uniqid();
    $courseDir = CourseFixtureBuilder::build(
        tmpDir: $tmp,
        code: 'C001-HTML-101',
        courseTitle: 'Introduction to HTML',
        description: 'Learn HTML.',
        modules: [
            ['title' => 'HTML Fundamentals', 'topics' => [
                ['title' => 'Intro to HTML', 'notes' => '# Intro to HTML notes'],
                ['title' => 'Document Structure', 'notes' => '# Doc structure notes'],
            ]],
        ],
    );

    $course = (new CourseImportService())->importCourse($courseDir, 'C001-HTML-101');

    expect($course->modules)->toHaveCount(1);
    $module = $course->modules->first();
    expect($module->title)->toBe('HTML Fundamentals')
        ->and($module->sort_order)->toBe(0)
        ->and($module->topics)->toHaveCount(2);

    $topic = $module->topics->first();
    expect($topic->title)->toBe('Intro to HTML')
        ->and($topic->sort_order)->toBe(0);

    $notes = $topic->material('notes');
    expect($notes->content)->toContain('Intro to HTML notes')
        ->and($notes->download_policy)->toBe('downloadable')
        ->and($notes->status)->toBe('ready');

    $task = $topic->material('task');
    expect($task->download_policy)->toBe('view_only');

    $demoProblem = $topic->material('demo_problem');
    expect($demoProblem->download_policy)->toBe('downloadable')
        ->and($demoProblem->content)->toContain('problem');

    $demoTryIt = $topic->material('demo_try_it');
    expect($demoTryIt->download_policy)->toBe('view_only');

    $slides = $topic->material('slides');
    expect($slides->status)->toBe('generating');

    $video = $topic->material('video');
    expect($video->status)->toBe('not_generated');
});

it('re-importing updates existing topics in place rather than duplicating', function () {
    $tmp = sys_get_temp_dir() . '/edtech-test-' . uniqid();
    $courseDir = CourseFixtureBuilder::build(
        $tmp, 'C001-HTML-101', 'Introduction to HTML', 'desc',
        modules: [['title' => 'Fundamentals', 'topics' => [['title' => 'Intro', 'notes' => 'v1 notes']]]],
    );

    $service = new CourseImportService();
    $service->importCourse($courseDir, 'C001-HTML-101');

    $topicDir = "{$courseDir}/selfpaced/module-01-fundamentals/topic-01-intro";
    file_put_contents("{$topicDir}/notes/notes.md", 'v2 notes');
    $course = $service->importCourse($courseDir, 'C001-HTML-101');

    expect(\App\Models\Topic::count())->toBe(1);
    expect($course->modules->first()->topics->first()->material('notes')->content)->toBe('v2 notes');
});

it('marks the course as failed with a status_reason, without throwing, when importing modules errors', function () {
    $tmp = sys_get_temp_dir() . '/edtech-test-' . uniqid();
    $courseDir = CourseFixtureBuilder::build($tmp, 'C001-HTML-101', 'Introduction to HTML', 'desc', []);

    $service = Mockery::mock(CourseImportService::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('importModules')->once()->andThrow(new RuntimeException('disk read error'));

    $course = $service->importCourse($courseDir, 'C001-HTML-101');

    expect($course->status)->toBe('failed')
        ->and($course->status_reason)->toBe('disk read error');
});

it('skips redundant slide regeneration when re-importing unchanged notes with already-ready slides', function () {
    $tmp = sys_get_temp_dir() . '/edtech-test-' . uniqid();
    $courseDir = CourseFixtureBuilder::build(
        $tmp, 'C001-HTML-101', 'Introduction to HTML', 'desc',
        modules: [['title' => 'Fundamentals', 'topics' => [['title' => 'Intro', 'notes' => 'v1 notes']]]],
    );

    $service = new CourseImportService();
    $course = $service->importCourse($courseDir, 'C001-HTML-101');

    $topic = $course->modules->first()->topics->first();
    expect($topic->material('slides')->status)->toBe('generating');

    \App\Models\Material::where('topic_id', $topic->id)->where('type', 'slides')->update(['status' => 'ready']);

    $service->importCourse($courseDir, 'C001-HTML-101');

    Queue::assertPushed(\App\Jobs\GenerateTopicSlidesJob::class, 1);
});

it('re-importing the same course code updates it in place instead of duplicating', function () {
    $tmp = sys_get_temp_dir() . '/edtech-test-' . uniqid();
    $courseDir = CourseFixtureBuilder::build($tmp, 'C001-HTML-101', 'Introduction to HTML', 'v1 description', [], []);

    $service = new CourseImportService();
    $first = $service->importCourse($courseDir, 'C001-HTML-101');

    file_put_contents("{$courseDir}/00-course-info/intro-and-summary.md", "# Intro\n\nv2 description\n");
    $second = $service->importCourse($courseDir, 'C001-HTML-101');

    expect($second->id)->toBe($first->id)
        ->and($second->description)->toBe('v2 description')
        ->and(Course::count())->toBe(1);
});
