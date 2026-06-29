<?php

use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/login', fn () => Inertia::render('Auth/Login'))->name('login');
Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
Route::post('/logout', function (\Illuminate\Http\Request $request) {
    auth()->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/login');
})->name('logout');

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PageController;

Route::middleware(['auth', 'role:admin,editor,viewer'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    });

Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('pages', PageController::class)->except(['show']);
        Route::patch('pages/{page}/publish', [PageController::class, 'publish'])->name('pages.publish');
    });

// Public preview (no auth needed):
Route::get('/preview/pages/{page}', [PageController::class, 'preview'])->name('pages.preview');

use App\Http\Controllers\Admin\ServiceCardController;

Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::post('service-cards/reorder', [ServiceCardController::class, 'reorder'])->name('service-cards.reorder');
        Route::resource('service-cards', ServiceCardController::class)->except(['show']);
    });

use App\Http\Controllers\Admin\MediaController;

Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::post('media', [MediaController::class, 'store'])->name('media.store');
        Route::delete('media/{id}', [MediaController::class, 'destroy'])->name('media.destroy');
    });

use App\Http\Controllers\Admin\WorkspaceController;
use App\Http\Controllers\Admin\ProjectController;

Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('workspaces', WorkspaceController::class)->except(['create','edit','show']);
        Route::resource('projects', ProjectController::class)->except(['create','edit']);
    });

use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\Admin\CommentController;

Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('tasks', TaskController::class)->except(['create','edit']);
        Route::post('tasks/{task}/comments', [CommentController::class, 'store'])->name('tasks.comments.store');
        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    });

use App\Http\Controllers\Admin\GitHubController;

Route::middleware(['auth', 'role:admin,editor,viewer'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('github', [GitHubController::class, 'index'])->name('github.index');
        Route::post('github/projects/{project}/sync', [GitHubController::class, 'sync'])->name('github.sync');
    });

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::post('github/projects/{project}/create', [GitHubController::class, 'createGitHubProject'])->name('github.project.create');
    });

use App\Http\Controllers\Admin\SettingController;

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::patch('settings', [SettingController::class, 'update'])->name('settings.update');
    });

use App\Http\Controllers\Admin\ThirdPartySettingController;

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::resource('integrations', ThirdPartySettingController::class)
            ->except(['create', 'edit', 'show'])
            ->parameters(['integrations' => 'integration']);
    });

use App\Http\Controllers\Admin\UserController;

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

// GitHub OAuth
use App\Http\Controllers\Auth\GitHubController as GitHubOAuthController;

Route::get('/auth/github', [GitHubOAuthController::class, 'redirect'])->name('auth.github');
Route::get('/auth/github/callback', [GitHubOAuthController::class, 'callback'])->name('auth.github.callback');

// Email + Password
use App\Http\Controllers\Auth\PasswordAuthController;

Route::get('/register', [PasswordAuthController::class, 'showRegister'])->name('register');
Route::post('/register', [PasswordAuthController::class, 'register'])->name('register.store');
Route::post('/login/password', [PasswordAuthController::class, 'login'])->name('login.password');
Route::get('/forgot-password', [PasswordAuthController::class, 'showForgot'])->name('password.request');
Route::post('/forgot-password', [PasswordAuthController::class, 'sendReset'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordAuthController::class, 'showReset'])->name('password.reset');
Route::post('/reset-password', [PasswordAuthController::class, 'reset'])->name('password.update');

// Email OTP
use App\Http\Controllers\Auth\OtpAuthController;

Route::get('/login/otp', [OtpAuthController::class, 'show'])->name('login.otp');
Route::post('/login/otp/send', [OtpAuthController::class, 'send'])->name('login.otp.send');
Route::post('/login/otp/verify', [OtpAuthController::class, 'verify'])->name('login.otp.verify');

// Public portfolio
Route::get('/', [\App\Http\Controllers\PublicController::class, 'index'])->name('home');

