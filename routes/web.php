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
        Route::resource('products', ProjectController::class, ['parameters' => ['products' => 'project']])->except(['create','edit']);
        Route::post('products/reorder', [ProjectController::class, 'reorder'])->name('products.reorder');
    });

use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\Admin\CommentController;

Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('tasks', TaskController::class)->except(['create','edit']);
        Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
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

Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::post('github/projects/{project}/webhook', [GitHubController::class, 'webhookCredentials'])->name('github.webhook.credentials');
    });

// GitHub webhook receiver — no auth (GitHub calls this directly), verified
// via HMAC signature instead. Excluded from CSRF in bootstrap/app.php.
use App\Http\Controllers\GitHubWebhookController;
Route::post('/webhooks/github/{project}', [GitHubWebhookController::class, 'handle'])->name('webhooks.github');

Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::post('github/token', [GitHubController::class, 'connectToken'])->name('github.token.connect');
        Route::delete('github/token', [GitHubController::class, 'disconnectToken'])->name('github.token.disconnect');
    });

use App\Http\Controllers\Admin\SettingController;

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::patch('settings', [SettingController::class, 'update'])->name('settings.update');
    });


use App\Http\Controllers\Admin\UserController;

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

use App\Http\Controllers\Admin\BillingController;

Route::middleware(['auth'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
        Route::post('billing/order', [BillingController::class, 'createOrder'])->name('billing.order');
        Route::post('billing/verify', [BillingController::class, 'verify'])->name('billing.verify');
        Route::post('billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
    });

use App\Http\Controllers\Admin\ProfileController;

Route::middleware(['auth'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('profile', [ProfileController::class, 'index'])->name('profile.index');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    });

// GitHub OAuth
use App\Http\Controllers\Auth\GitHubController as GitHubOAuthController;

Route::get('/auth/github', [GitHubOAuthController::class, 'redirect'])->name('auth.github');
Route::get('/auth/github/callback', [GitHubOAuthController::class, 'callback'])->name('auth.github.callback');

// Email + Password
use App\Http\Controllers\Auth\PasswordAuthController;

Route::get('/register', [PasswordAuthController::class, 'showRegister'])->name('register');
Route::post('/register', [PasswordAuthController::class, 'register'])->name('register.store');
Route::post('/login/password', [PasswordAuthController::class, 'login'])->name('login.password')->middleware('throttle:10,1');
Route::get('/forgot-password', [PasswordAuthController::class, 'showForgot'])->name('password.request');
Route::post('/forgot-password', [PasswordAuthController::class, 'sendReset'])->name('password.email')->middleware('throttle:5,1');
Route::get('/reset-password/{token}', [PasswordAuthController::class, 'showReset'])->name('password.reset');
Route::post('/reset-password', [PasswordAuthController::class, 'reset'])->name('password.update');

// Email OTP
use App\Http\Controllers\Auth\OtpAuthController;

// The OTP flow is handled inline on the Login page (tab switcher); no
// standalone Auth/Otp Vue page exists, so redirect rather than 404.
Route::get('/login/otp', fn () => redirect('/login'))->name('login.otp');
Route::post('/login/otp/send', [OtpAuthController::class, 'send'])->name('login.otp.send')->middleware('throttle:5,1');
Route::post('/login/otp/verify', [OtpAuthController::class, 'verify'])->name('login.otp.verify')->middleware('throttle:10,1');

// Donation — public, no auth required
use App\Http\Controllers\DonationController;
Route::get('/donate', [DonationController::class, 'show'])->name('donate');
Route::post('/donate/order', [DonationController::class, 'createOrder'])->name('donate.order');
Route::post('/donate/verify', [DonationController::class, 'verify'])->name('donate.verify');

// Public portfolio
Route::get('/', [\App\Http\Controllers\PublicController::class, 'index'])->name('home');

// Multi-tenant portfolio routes — must be last
Route::get('/{username}', [\App\Http\Controllers\PublicController::class, 'portfolio'])
    ->name('portfolio')
    ->where('username', '[a-z0-9_\-]+');

Route::get('/{username}/{slug}', [\App\Http\Controllers\PublicController::class, 'portfolioPage'])
    ->name('portfolio.page')
    ->where('username', '[a-z0-9_\-]+')
    ->where('slug', '[a-z0-9\-]+');

