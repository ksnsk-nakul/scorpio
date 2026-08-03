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
