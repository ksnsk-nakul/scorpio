<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\RoleSeeder)->run();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('lists users', function () {
    $this->actingAs($this->admin)
        ->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Admin/Users/Index'));
});

it('assigns a role to a user', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');

    $this->actingAs($this->admin)
        ->patch("/admin/users/{$viewer->id}/role", ['role' => 'editor'])
        ->assertRedirect();

    expect($viewer->fresh()->hasRole('editor'))->toBeTrue();
    expect($viewer->fresh()->hasRole('viewer'))->toBeFalse();
});
