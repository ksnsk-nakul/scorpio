<?php

use App\Models\Course;
use App\Services\CourseImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CourseFixtureBuilder;

uses(RefreshDatabase::class);

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
