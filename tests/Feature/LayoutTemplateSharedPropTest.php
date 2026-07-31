<?php

use App\Models\Setting;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RoleSeeder)->run();
    (new SettingSeeder)->run();
});

it('shares the active public layout template with every Inertia response', function () {
    Setting::where('key', 'layout_template_public')->update(['value' => 'animejs']);

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->where('layoutTemplates.public', 'animejs')
        ->where('layoutTemplates.admin', 'stripe')
    );
});
