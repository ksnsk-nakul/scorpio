<?php

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('lists pages', function () {
    $this->actingAs($this->admin)
        ->get('/admin/pages')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Pages/Index'));
});

it('creates a page', function () {
    $this->actingAs($this->admin)
        ->post('/admin/pages', ['name' => 'About', 'template' => 'blank'])
        ->assertRedirect();
    expect(Page::where('name', 'About')->exists())->toBeTrue();
});

it('publishes a page', function () {
    $p = Page::create(['name' => 'Test', 'slug' => 'test', 'template' => 'blank', 'user_id' => $this->admin->id]);
    $this->actingAs($this->admin)
        ->patch("/admin/pages/{$p->id}/publish")
        ->assertRedirect();
    expect($p->fresh()->status)->toBe('published');
});
