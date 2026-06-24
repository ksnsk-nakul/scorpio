<?php

use App\Models\Setting;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(fn () => $this->seed());

it('seeds the three roles', function () {
    expect(Role::pluck('name')->toArray())
        ->toContain('admin', 'editor', 'viewer');
});

it('seeds default settings', function () {
    expect(Setting::where('key', 'site_name')->exists())->toBeTrue();
});
