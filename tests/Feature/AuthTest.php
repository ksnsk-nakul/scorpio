<?php

use App\Models\User;
use App\Models\LoginToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\RoleSeeder)->run();
});

it('registers a new user with email and password', function () {
    $this->post('/register', [
        'name'                  => 'Test User',
        'email'                 => 'test@example.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect('/admin/dashboard');

    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
});

it('logs in an existing user with email and password', function () {
    $user = User::create([
        'name'     => 'Test',
        'email'    => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);
    $user->assignRole('viewer');

    $this->post('/login/password', [
        'email'    => 'test@example.com',
        'password' => 'password123',
    ])->assertRedirect();

    $this->assertAuthenticated();
});

it('rejects wrong password', function () {
    $user = User::create([
        'name'     => 'Test',
        'email'    => 'test@example.com',
        'password' => Hash::make('correct'),
    ]);
    $user->assignRole('viewer');

    $this->post('/login/password', [
        'email'    => 'test@example.com',
        'password' => 'wrong',
    ])->assertSessionHasErrors('email');
});

it('sends and verifies an OTP', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $email = 'otp@example.com';

    $this->post('/login/otp/send', ['email' => $email])->assertRedirect();

    $token = LoginToken::where('email', $email)->first();
    expect($token)->not->toBeNull();

    $this->post('/login/otp/verify', ['email' => $email, 'otp' => $token->token])
        ->assertRedirect('/admin/dashboard');

    $this->assertAuthenticated();
});

it('rejects expired OTP', function () {
    LoginToken::create([
        'email'      => 'test@example.com',
        'token'      => '123456',
        'expires_at' => now()->subMinutes(1),
    ]);

    $this->post('/login/otp/verify', ['email' => 'test@example.com', 'otp' => '123456'])
        ->assertSessionHasErrors('otp');
});
