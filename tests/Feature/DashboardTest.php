<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

it('redirects guest from dashboard', function () {
    $this->get('/admin/dashboard')->assertRedirect('/login');
});

it('shows dashboard to authenticated user with role', function () {
    $user = User::factory()->create();
    $user->assignRole('viewer');
    $this->actingAs($user)->get('/admin/dashboard')->assertOk();
});
