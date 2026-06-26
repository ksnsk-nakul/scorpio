<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

it('redirects unauthenticated users to login', function () {
    $response = $this->get('/admin/dashboard');
    $response->assertRedirect('/login');
});

it('google redirect route exists', function () {
    // Socialite redirects externally — mock it so we don't need real credentials
    \Laravel\Socialite\Facades\Socialite::shouldReceive('driver->redirect')
        ->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

    $response = $this->get('/auth/google');
    $response->assertRedirectContains('accounts.google.com');
});
