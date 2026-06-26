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
