<?php

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\RoleSeeder)->run();
    (new \Database\Seeders\SettingSeeder)->run();
});

it('shares the active public layout template with every Inertia response', function () {
    Setting::where('key', 'layout_template_public')->update(['value' => 'animejs']);

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->where('layoutTemplates.public', 'animejs')
        ->where('layoutTemplates.admin', 'stripe')
    );
});
