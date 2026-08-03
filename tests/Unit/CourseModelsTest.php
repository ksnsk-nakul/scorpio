<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\Module;
use App\Models\PricingTier;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds the full Course -> Module -> Topic -> Material chain with a unique slug', function () {
    $course = Course::create(['code' => 'C001-HTML-101', 'title' => 'Introduction to HTML', 'source_path' => '/tmp/x']);
    expect($course->slug)->toBe('introduction-to-html');

    $module = Module::create(['course_id' => $course->id, 'title' => 'HTML Fundamentals', 'slug' => 'html-fundamentals', 'sort_order' => 0]);
    $topic = Topic::create(['module_id' => $module->id, 'title' => 'Intro to HTML', 'slug' => 'intro-to-html', 'sort_order' => 0]);
    $material = Material::create(['topic_id' => $topic->id, 'type' => 'notes', 'content' => '# Notes', 'download_policy' => 'downloadable']);

    expect($course->modules)->toHaveCount(1)
        ->and($course->modules->first()->topics)->toHaveCount(1)
        ->and($course->modules->first()->topics->first()->materials)->toHaveCount(1)
        ->and($material->topic->id)->toBe($topic->id);
});

it('generates a unique slug when two courses would otherwise collide', function () {
    Course::create(['code' => 'C001-HTML-101', 'title' => 'Intro', 'source_path' => '/tmp/x']);
    $second = Course::create(['code' => 'C002-CSS-101', 'title' => 'Intro', 'source_path' => '/tmp/y']);

    expect($second->slug)->toBe('intro-2');
});

it('creates a pricing tier belonging to a course', function () {
    $course = Course::create(['code' => 'C001-HTML-101', 'title' => 'Intro to HTML', 'source_path' => '/tmp/x']);
    $tier = PricingTier::create([
        'course_id' => $course->id, 'name' => 'Self-Paced Pro',
        'price_inr_paise' => 599900, 'price_usd_cents' => 14900,
        'description' => 'Full notes, slides, videos, tasks, demo (all 20 topics), certificate',
    ]);

    expect($course->pricingTiers->first()->id)->toBe($tier->id);
});

it('creates an enrollment linking a user, course, and pricing tier', function () {
    $user = User::factory()->create();
    $course = Course::create(['code' => 'C001-HTML-101', 'title' => 'Intro to HTML', 'source_path' => '/tmp/x']);
    $tier = PricingTier::create(['course_id' => $course->id, 'name' => 'Pro', 'price_inr_paise' => 599900, 'price_usd_cents' => 14900]);

    $enrollment = Enrollment::create([
        'user_id' => $user->id, 'course_id' => $course->id, 'pricing_tier_id' => $tier->id,
        'amount_paise_charged' => 599900, 'enrolled_at' => now(),
    ]);

    expect($user->enrollments->first()->id)->toBe($enrollment->id)
        ->and($enrollment->course->id)->toBe($course->id)
        ->and($enrollment->pricingTier->id)->toBe($tier->id);
});
