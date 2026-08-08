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
        Route::patch('/dashboard/contacts/{submission}/read', [DashboardController::class, 'markContactRead'])->name('dashboard.contact.read');
    });

Route::middleware(['auth', 'role:admin,editor,viewer'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('pages', [PageController::class, 'index'])->name('pages.index');
    });

Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('pages', PageController::class)->except(['index', 'show']);
        Route::patch('pages/{page}/publish', [PageController::class, 'publish'])->name('pages.publish');
    });

// Public preview (no auth needed):
Route::get('/preview/pages/{page}', [PageController::class, 'preview'])->name('pages.preview');

use App\Http\Controllers\Admin\ServiceCardController;

Route::middleware(['auth', 'role:admin,editor,viewer'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('service-cards', [ServiceCardController::class, 'index'])->name('service-cards.index');
    });

Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::post('service-cards/reorder', [ServiceCardController::class, 'reorder'])->name('service-cards.reorder');
        Route::resource('service-cards', ServiceCardController::class)->except(['index', 'show']);
    });

use App\Http\Controllers\Admin\MediaController;

Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::post('media', [MediaController::class, 'store'])->name('media.store');
        Route::delete('media/{id}', [MediaController::class, 'destroy'])->name('media.destroy');
    });

Route::middleware(['auth', 'role:admin,editor,viewer'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('media/{id}/status', [MediaController::class, 'status'])->name('media.status');
    });

use App\Http\Controllers\Admin\BookController;

Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::post('library/books', [BookController::class, 'store'])->name('library.books.store');
        Route::post('library/books/{book}/retry', [BookController::class, 'retry'])->name('library.books.retry');
        Route::post('library/books/{book}/mark-failed', [BookController::class, 'markFailed'])->name('library.books.mark-failed');
        Route::patch('library/books/{book}', [BookController::class, 'update'])->name('library.books.update');
        Route::delete('library/books/bulk', [BookController::class, 'bulkDestroy'])->name('library.books.bulk-destroy');
        Route::delete('library/books/{book}', [BookController::class, 'destroy'])->name('library.books.destroy');
    });

Route::middleware(['auth', 'role:admin,editor,viewer'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('library/books/{book}/status', [BookController::class, 'status'])->name('library.books.status');
        Route::get('library', [BookController::class, 'index'])->name('library.index');
        Route::get('library/upload', [BookController::class, 'uploadPage'])->name('library.upload');
        Route::get('library/my', [BookController::class, 'myLibrary'])->name('library.my');
    });

use App\Http\Controllers\Admin\EdTechController;

Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::post('edtech/courses/{course}/reimport', [EdTechController::class, 'reimport'])->name('edtech.reimport');
    });

Route::middleware(['auth', 'role:admin,editor,viewer'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('edtech', [EdTechController::class, 'index'])->name('edtech.index');
        Route::get('edtech/enrollments', [EdTechController::class, 'enrollments'])->name('edtech.enrollments');
    });

use App\Http\Controllers\Admin\LibraryChatController;

Route::middleware(['auth', 'role:admin,editor,viewer'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::post('library/chat', [LibraryChatController::class, 'store'])->middleware('throttle:20,1')->name('library.chat.store');
        Route::get('library/chat/history', [LibraryChatController::class, 'history'])->name('library.chat.history');
        Route::get('library/chat/threads', [LibraryChatController::class, 'index'])->name('library.chat.index');
        Route::get('library/chat/threads/{thread}', [LibraryChatController::class, 'show'])->name('library.chat.show');
        Route::delete('library/chat/{thread}', [LibraryChatController::class, 'destroy'])->name('library.chat.destroy');
    });

use App\Http\Controllers\Admin\WorkspaceController;
use App\Http\Controllers\Admin\ProjectController;

Route::middleware(['auth', 'role:admin,editor,viewer'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('products', [ProjectController::class, 'index'])->name('products.index');
        Route::get('products/{project}', [ProjectController::class, 'show'])->name('products.show');
    });

Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('workspaces', WorkspaceController::class)->except(['create','edit','show']);
        Route::resource('products', ProjectController::class, ['parameters' => ['products' => 'project']])->except(['create','edit','index','show']);
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
        Route::post('github/repos/link', [GitHubController::class, 'linkRepoAsProduct'])->name('github.repo.link');
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

use App\Http\Controllers\Admin\ContentController;

Route::middleware(['auth', 'role:admin,editor,viewer'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('content', [ContentController::class, 'index'])->name('content.index');
    });

Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::post('content/skills',                  [ContentController::class, 'storeSkill'])->name('content.skills.store');
        Route::patch('content/skills/{skill}',         [ContentController::class, 'updateSkill'])->name('content.skills.update');
        Route::delete('content/skills/{skill}',        [ContentController::class, 'destroySkill'])->name('content.skills.destroy');
        Route::post('content/skills/reorder',          [ContentController::class, 'reorderSkills'])->name('content.skills.reorder');

        Route::patch('content/about',                  [ContentController::class, 'updateAbout'])->name('content.about.update');

        Route::post('content/experience',              [ContentController::class, 'storeExperience'])->name('content.experience.store');
        Route::patch('content/experience/{experience}',[ContentController::class, 'updateExperience'])->name('content.experience.update');
        Route::delete('content/experience/{experience}',[ContentController::class, 'destroyExperience'])->name('content.experience.destroy');
        Route::post('content/experience/reorder',      [ContentController::class, 'reorderExperience'])->name('content.experience.reorder');
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
        Route::post('users/{user}/wallet-adjust', [UserController::class, 'walletAdjust'])->name('users.wallet-adjust');
    });

use App\Http\Controllers\Admin\AnnouncementController;

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('announcements',                   [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('announcements',                  [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::patch('announcements/{announcement}',  [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    });

use App\Http\Controllers\Admin\OrganizationController;

Route::middleware(['auth', 'role:admin,editor,viewer'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('organizations',                [OrganizationController::class, 'index'])->name('organizations.index');
        Route::get('organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');
    });

Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::post('organizations',                   [OrganizationController::class, 'store'])->name('organizations.store');
        Route::patch('organizations/{organization}',   [OrganizationController::class, 'update'])->name('organizations.update');
        Route::delete('organizations/{organization}',  [OrganizationController::class, 'destroy'])->name('organizations.destroy');
        Route::post('organizations/{organization}/members',             [OrganizationController::class, 'addMember'])->name('organizations.members.add');
        Route::delete('organizations/{organization}/members/{member}',  [OrganizationController::class, 'removeMember'])->name('organizations.members.remove');
        Route::post('organizations/{organization}/achievements',                    [OrganizationController::class, 'addAchievement'])->name('organizations.achievements.add');
        Route::delete('organizations/{organization}/achievements/{achievement}',    [OrganizationController::class, 'removeAchievement'])->name('organizations.achievements.remove');
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

use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\Admin\PaymentMethodController;

Route::middleware(['auth'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        // Wallet dashboard
        Route::get('wallet', [WalletController::class, 'index'])->name('wallet.index');

        // Pay subscription from wallet
        Route::post('billing/pay-wallet', [BillingController::class, 'payWithWallet'])->name('billing.pay-wallet');

        // Payment methods
        Route::get('payment-methods',                    [PaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::post('payment-methods/upi',               [PaymentMethodController::class, 'storeUpi'])->name('payment-methods.store-upi');
        Route::post('payment-methods/card/order',        [PaymentMethodController::class, 'createCardOrder'])->name('payment-methods.card.order');
        Route::post('payment-methods/card/verify',       [PaymentMethodController::class, 'verifyCard'])->name('payment-methods.card.verify');
        Route::patch('payment-methods/{method}/default', [PaymentMethodController::class, 'setDefault'])->name('payment-methods.default');
        Route::delete('payment-methods/{method}',        [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
    });

use App\Http\Controllers\Admin\OrgUpgradeController;

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('org-upgrade', function () {
        $plans = config('billing.plans');
        $orgPlans = collect($plans)
            ->filter(fn ($p) => $p['type'] === 'org')
            ->map(fn ($p, $key) => [...$p, 'key' => $key])
            ->values();
        return \Inertia\Inertia::render('Admin/Organizations/Upgrade', [
            'orgPlans' => $orgPlans,
        ]);
    })->name('org-upgrade.index');
    Route::post('org-upgrade/order',  [OrgUpgradeController::class, 'createOrder'])->name('org-upgrade.order');
    Route::post('org-upgrade/verify', [OrgUpgradeController::class, 'verify'])->name('org-upgrade.verify');
});

use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\IntegrationsController;

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        // Integrations wizard (Payment + Mail + SMS)
        Route::get('integrations',          [IntegrationsController::class, 'index'])->name('integrations.index');
        Route::post('integrations/payment', [IntegrationsController::class, 'savePayment'])->name('integrations.payment');
        Route::post('integrations/mail',      [IntegrationsController::class, 'saveMail'])->name('integrations.mail');
        Route::post('integrations/mail/test',[IntegrationsController::class, 'testMail'])->name('integrations.mail.test')->middleware('throttle:5,1');
        Route::post('integrations/sms',     [IntegrationsController::class, 'saveSms'])->name('integrations.sms');

        // Keep old payment GET as redirect; POST removed (saves go to integrations/*)
        Route::get('payment', fn () => redirect()->route('admin.integrations.index'))->name('payment.index');
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

// Public pay-to-wallet (no auth required)
Route::get('/pay/{username}/wallet',         [\App\Http\Controllers\WalletTopUpController::class, 'show'])->name('wallet.pay.show');
Route::post('/pay/{username}/wallet/order',  [\App\Http\Controllers\WalletTopUpController::class, 'createOrder'])->name('wallet.pay.order')->middleware('throttle:10,1');
Route::post('/pay/{username}/wallet/verify', [\App\Http\Controllers\WalletTopUpController::class, 'verify'])->name('wallet.pay.verify')->middleware('throttle:10,1');
Route::get('/pay/{username}/wallet/done',    [\App\Http\Controllers\WalletTopUpController::class, 'done'])->name('wallet.pay.done');

// Donation — public, no auth required
use App\Http\Controllers\DonationController;
Route::get('/donate', [DonationController::class, 'show'])->name('donate');

// Contact form submission (public)
use App\Http\Controllers\ContactController;
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store')->middleware('throttle:5,1');

// Legal pages (required for Google OAuth)
Route::get('/terms', [\App\Http\Controllers\PublicController::class, 'terms'])->name('terms');
Route::get('/privacy', [\App\Http\Controllers\PublicController::class, 'privacy'])->name('privacy');
Route::get('/refund', [\App\Http\Controllers\PublicController::class, 'refund'])->name('refund');
Route::get('/pricing', [\App\Http\Controllers\PublicController::class, 'pricing'])->name('pricing');

// Public portfolio
Route::get('/', [\App\Http\Controllers\PublicController::class, 'index'])->name('home');

// Multi-tenant portfolio routes
Route::get('/portfolio/{username}', [\App\Http\Controllers\PublicController::class, 'portfolio'])
    ->name('portfolio')
    ->where('username', '[a-z0-9_\-]+');

Route::get('/portfolio/{username}/{slug}', [\App\Http\Controllers\PublicController::class, 'portfolioPage'])
    ->name('portfolio.page')
    ->where('username', '[a-z0-9_\-]+')
    ->where('slug', '[a-z0-9\-]+');

Route::get('/portfolio/{username}/projects/{projectSlug}', [\App\Http\Controllers\PublicController::class, 'projectDetail'])
    ->name('portfolio.project')
    ->where('username', '[a-z0-9_\-]+')
    ->where('projectSlug', '[a-z0-9\-]+');

// Organization invitation acceptance (public, no auth; throttled against enumeration)
Route::get('/org-invite/{token}', [\App\Http\Controllers\Admin\OrganizationController::class, 'acceptInvitation'])
    ->name('org.invite.accept')
    ->middleware('throttle:20,1');

// Organization public page
Route::get('/org/{slug}', [\App\Http\Controllers\PublicController::class, 'orgPage'])
    ->name('org.page')
    ->where('slug', '[a-z0-9\-]+');

use App\Http\Controllers\LibraryController;

Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
Route::get('/library/books/{slug}', [LibraryController::class, 'show'])
    ->name('library.book')
    ->where('slug', '[a-z0-9\-]+');
Route::get('/library/books/{slug}/chapters/{sortOrder}', [LibraryController::class, 'chapter'])
    ->name('library.chapter')
    ->where(['slug' => '[a-z0-9\-]+', 'sortOrder' => '[0-9]+']);
Route::get('/library/authors/{slug}', [LibraryController::class, 'author'])
    ->name('library.author')
    ->where('slug', '[a-z0-9\-]+');
Route::get('/library/series/{slug}', [LibraryController::class, 'series'])->name('library.series');

use App\Http\Controllers\LibraryEntryController;

Route::middleware(['auth'])
    ->prefix('library')
    ->name('library.')
    ->group(function () {
        Route::post('books/{slug}/follow', [LibraryEntryController::class, 'follow'])->name('entries.follow');
        Route::patch('books/{slug}/status', [LibraryEntryController::class, 'updateStatus'])->name('entries.status');
        Route::delete('books/{slug}/follow', [LibraryEntryController::class, 'unfollow'])->name('entries.unfollow');
        Route::post('books/{slug}/progress', [LibraryEntryController::class, 'recordProgress'])->name('entries.progress');
    });

use App\Http\Controllers\CourseController;

Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');

Route::get('/courses/{slug}', [CourseController::class, 'show'])
    ->name('courses.show')
    ->where('slug', '[a-z0-9\-]+');

Route::get('/courses/{slug}/topics/{topicSlug}', [CourseController::class, 'topic'])
    ->name('courses.topic')
    ->where(['slug' => '[a-z0-9\-]+', 'topicSlug' => '[a-z0-9\-]+']);
Route::get('/courses/{slug}/topics/{topicSlug}/materials/{material}/download', [CourseController::class, 'downloadMaterial'])
    ->name('courses.materials.download')
    ->where(['slug' => '[a-z0-9\-]+', 'topicSlug' => '[a-z0-9\-]+']);

use App\Http\Controllers\CourseEnrollmentController;

Route::middleware(['auth'])->group(function () {
    Route::post('/courses/{slug}/enroll', [CourseEnrollmentController::class, 'store'])
        ->name('courses.enroll')
        ->where('slug', '[a-z0-9\-]+');
});

// Admin portfolio inner pages at root (must be last to avoid shadowing other routes)
Route::get('/{slug}', [\App\Http\Controllers\PublicController::class, 'adminPage'])
    ->name('admin.page')
    ->where('slug', '[a-z0-9\-]+');

