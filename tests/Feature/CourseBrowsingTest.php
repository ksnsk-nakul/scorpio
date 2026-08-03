<?php

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists only ready courses on the public index, paginated', function () {
    Course::factory()->count(3)->create(['status' => 'ready']);
    Course::factory()->create(['status' => 'pending']);

    $response = $this->get('/courses');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/EdTech/Index')
        ->has('courses.data', 3));
});

it('shows a course detail page with modules, topics, and pricing tiers', function () {
    $course = Course::factory()->create(['status' => 'ready', 'title' => 'Intro to HTML']);
    $module = \App\Models\Module::create(['course_id' => $course->id, 'title' => 'Fundamentals', 'slug' => 'fundamentals', 'sort_order' => 0]);
    \App\Models\Topic::create(['module_id' => $module->id, 'title' => 'Intro', 'slug' => 'intro', 'sort_order' => 0]);
    \App\Models\PricingTier::create(['course_id' => $course->id, 'name' => 'Pro', 'price_inr_paise' => 599900, 'price_usd_cents' => 14900]);

    $response = $this->get("/courses/{$course->slug}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/EdTech/CourseDetail')
        ->where('course.title', 'Intro to HTML')
        ->has('course.modules', 1)
        ->has('course.modules.0.topics', 1)
        ->has('course.pricingTiers', 1));
});

it('404s for a pending (not-yet-imported) course', function () {
    $course = Course::factory()->create(['status' => 'pending']);

    $this->get("/courses/{$course->slug}")->assertNotFound();
});
