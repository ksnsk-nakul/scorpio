<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\RoleSeeder)->run();
    (new \Database\Seeders\SettingSeeder)->run();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('shows the settings page', function () {
    $this->actingAs($this->admin)
        ->get('/admin/settings')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Admin/Settings/Index'));
});

it('updates a setting value', function () {
    $this->actingAs($this->admin)
        ->patch('/admin/settings', ['site_name' => 'My Portfolio'])
        ->assertRedirect();
    expect(Setting::get('site_name'))->toBe('My Portfolio');
});
