<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PricingTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

it('lists courses with status and counts on the admin index', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $course = Course::factory()->create(['status' => 'ready']);
    $module = \App\Models\Module::create(['course_id' => $course->id, 'title' => 'M', 'slug' => 'm', 'sort_order' => 0]);
    \App\Models\Topic::create(['module_id' => $module->id, 'title' => 'T', 'slug' => 't', 'sort_order' => 0]);

    $response = $this->actingAs($admin)->get('/admin/edtech');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/EdTech/Index')
        ->where('courses.data.0.module_count', 1)
        ->where('courses.data.0.topic_count', 1));
});

it('lists enrollments with learner, course, and amount for admins', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $learner = User::factory()->create();
    $course = Course::factory()->create(['status' => 'ready', 'title' => 'Intro to HTML']);
    $tier = PricingTier::create(['course_id' => $course->id, 'name' => 'Pro', 'price_inr_paise' => 599900, 'price_usd_cents' => 14900]);
    Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'pricing_tier_id' => $tier->id, 'amount_paise_charged' => 599900, 'enrolled_at' => now()]);

    $response = $this->actingAs($admin)->get('/admin/edtech/enrollments');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/EdTech/Enrollments')
        ->where('enrollments.data.0.course_title', 'Intro to HTML')
        ->where('enrollments.data.0.amount_paise', 599900));
});

it('re-imports a course on demand from the admin index', function () {
    Queue::fake();
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $course = Course::factory()->create(['status' => 'ready', 'source_path' => '/tmp/doesnotmatter']);

    // Re-import against a path that doesn't exist should fail cleanly, not crash —
    // confirms the endpoint at least routes and calls the service without a 500.
    $response = $this->actingAs($admin)->postJson("/admin/edtech/courses/{$course->id}/reimport");

    expect($response->status())->toBeIn([200, 422]);
});
