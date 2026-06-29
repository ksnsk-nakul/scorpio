<?php

use App\Models\ServiceCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('lists service cards', function () {
    $this->actingAs($this->admin)
        ->get('/admin/service-cards')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Admin/ServiceCards/Index'));
});

it('creates a service card', function () {
    $this->actingAs($this->admin)
        ->post('/admin/service-cards', ['title' => 'Web Dev', 'description' => 'Laravel apps'])
        ->assertRedirect();
    expect(ServiceCard::where('title', 'Web Dev')->exists())->toBeTrue();
});

it('reorders service cards', function () {
    $a = ServiceCard::create(['title' => 'A', 'sort_order' => 0, 'user_id' => $this->admin->id]);
    $b = ServiceCard::create(['title' => 'B', 'sort_order' => 1, 'user_id' => $this->admin->id]);
    $this->actingAs($this->admin)
        ->post('/admin/service-cards/reorder', ['ids' => [$b->id, $a->id]])
        ->assertOk();
    expect($a->fresh()->sort_order)->toBe(1);
    expect($b->fresh()->sort_order)->toBe(0);
});
