<?php

use App\Models\ThirdPartySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\RoleSeeder)->run();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('stores a third party setting', function () {
    $this->actingAs($this->admin)
        ->post('/admin/integrations', [
            'provider'  => 'github',
            'key'       => 'token',
            'value'     => 'ghp_abc123',
            'group'     => 'github',
            'is_active' => true,
        ])
        ->assertRedirect();
    expect(ThirdPartySetting::where('provider', 'github')->where('key', 'token')->exists())->toBeTrue();
});

it('toggles a setting active state', function () {
    $s = ThirdPartySetting::create([
        'provider' => 'github', 'key' => 'token', 'value' => 'x', 'group' => 'github', 'is_active' => true,
    ]);
    $this->actingAs($this->admin)
        ->patch("/admin/integrations/{$s->id}", ['is_active' => false, 'provider' => 'github', 'key' => 'token', 'group' => 'github'])
        ->assertRedirect();
    expect($s->fresh()->is_active)->toBeFalse();
});
