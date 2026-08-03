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

it('shows a topic viewer with materials split by download policy, and hides raw content for view-only materials from the download action', function () {
    $course = Course::factory()->create(['status' => 'ready']);
    $module = \App\Models\Module::create(['course_id' => $course->id, 'title' => 'Fundamentals', 'slug' => 'fundamentals', 'sort_order' => 0]);
    $topic = \App\Models\Topic::create(['module_id' => $module->id, 'title' => 'Intro', 'slug' => 'intro', 'sort_order' => 0]);
    \App\Models\Material::create(['topic_id' => $topic->id, 'type' => 'notes', 'content' => '# Notes body', 'download_policy' => 'downloadable', 'status' => 'ready']);
    \App\Models\Material::create(['topic_id' => $topic->id, 'type' => 'task', 'content' => '# Task body', 'download_policy' => 'view_only', 'status' => 'ready']);
    \App\Models\Material::create(['topic_id' => $topic->id, 'type' => 'video', 'download_policy' => 'view_only', 'status' => 'not_generated']);

    $response = $this->get("/courses/{$course->slug}/topics/{$topic->slug}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/EdTech/TopicViewer')
        ->where('topic.title', 'Intro')
        ->where('topic.materials.notes.content', '# Notes body')
        ->where('topic.materials.notes.downloadable', true)
        ->where('topic.materials.task.downloadable', false)
        ->where('topic.materials.video.status', 'not_generated'));
});

it('serves a downloadable material file via an authenticated download action', function () {
    $course = Course::factory()->create(['status' => 'ready']);
    $module = \App\Models\Module::create(['course_id' => $course->id, 'title' => 'F', 'slug' => 'f', 'sort_order' => 0]);
    $topic = \App\Models\Topic::create(['module_id' => $module->id, 'title' => 'T', 'slug' => 't', 'sort_order' => 0]);
    $notes = \App\Models\Material::create(['topic_id' => $topic->id, 'type' => 'notes', 'content' => '# Downloadable', 'download_policy' => 'downloadable', 'status' => 'ready']);
    $task = \App\Models\Material::create(['topic_id' => $topic->id, 'type' => 'task', 'content' => '# Not downloadable', 'download_policy' => 'view_only', 'status' => 'ready']);

    $this->get("/courses/{$course->slug}/topics/{$topic->slug}/materials/{$notes->id}/download")
        ->assertOk()
        ->assertSee('# Downloadable');

    $this->get("/courses/{$course->slug}/topics/{$topic->slug}/materials/{$task->id}/download")
        ->assertForbidden();
});
