<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('is publicly accessible without authentication', function () {
    $this->get('/pricing')->assertOk();
});

it('lists all configured plans with their prices and features', function () {
    $response = $this->get('/pricing');

    $response->assertInertia(fn ($page) => $page
        ->component('Public/Pricing')
        ->has('plans', count(config('billing.plans')))
        ->where('plans.1.key', 'pro')
        ->where('plans.1.price', 49900)
        ->where('plans.1.features', fn ($features) => collect($features)->contains('E-Library access'))
    );
});
