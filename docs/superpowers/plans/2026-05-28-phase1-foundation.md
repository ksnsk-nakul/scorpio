# Portfolio CMS — Phase 1: Foundation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fresh Laravel 13 app with all packages installed, all migrations run, all models defined with relationships, seeders seeded, Gmail OAuth working, admin shell (Inertia + Vue 3) rendering, and .env.example committed.

**Architecture:** Laravel 13 + Inertia.js + Vue 3. Spatie Laravel-Permission for RBAC. Laravel Socialite for Gmail OAuth. MySQL via Herd. Admin panel lives under the `/admin` route group protected by auth + role middleware.

**Tech Stack:** PHP 8.3+, Laravel 13, Inertia.js v2, Vue 3, Vite, Tailwind CSS v4, Spatie laravel-permission v6, laravel/socialite, Pest v3 for testing.

---

## File Map

| File | Purpose |
|---|---|
| `app/Models/User.php` | Auth user, google_id, HasRoles |
| `app/Models/Workspace.php` | Grouping of projects |
| `app/Models/Project.php` | Project linked to workspace + GitHub |
| `app/Models/Task.php` | Task/subtask (self-ref parent_id) |
| `app/Models/Comment.php` | Polymorphic comment on Task |
| `app/Models/Media.php` | Polymorphic file attachment |
| `app/Models/Page.php` | CMS page with blocks JSON |
| `app/Models/ServiceCard.php` | Service card |
| `app/Models/Setting.php` | App settings key/value/group |
| `app/Models/ThirdPartySetting.php` | OAuth/API credentials |
| `app/Http/Controllers/Auth/GoogleController.php` | OAuth redirect + callback |
| `app/Http/Middleware/EnsureRole.php` | Role gate middleware |
| `app/Http/Controllers/Admin/DashboardController.php` | Dashboard index |
| `resources/js/app.js` | Inertia bootstrap |
| `resources/js/Layouts/AdminLayout.vue` | Sidebar shell |
| `resources/js/Pages/Auth/Login.vue` | Google login page |
| `resources/js/Pages/Admin/Dashboard.vue` | Dashboard page |
| `routes/web.php` | All routes |
| `database/seeders/RoleSeeder.php` | admin/editor/viewer roles |
| `database/seeders/SettingSeeder.php` | Default settings |
| `database/seeders/UserSeeder.php` | First admin user |
| `.env.example` | All required env keys |

---

## Task 1: Install Laravel 13 & configure environment

**Files:**
- Create: `/Users/nakul/Herd/Portfolio` (via composer)
- Modify: `.env`, `.env.example`

- [ ] **Step 1: Create the Laravel 13 project**

```bash
cd /Users/nakul/Herd
composer create-project laravel/laravel portfolio --prefer-dist
cd portfolio
```

- [ ] **Step 2: Create the MySQL database**

```bash
mysql -u root -e "CREATE DATABASE portfolio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

- [ ] **Step 3: Configure .env**

Edit `.env`:
```env
APP_NAME=Portfolio
APP_URL=http://portfolio.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=portfolio
DB_USERNAME=root
DB_PASSWORD=

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"

GITHUB_TOKEN=
GITHUB_USERNAME=

FILESYSTEM_DISK=local
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
```

- [ ] **Step 4: Install PHP packages**

```bash
composer require inertiajs/inertia-laravel \
  tightenco/ziggy \
  laravel/socialite \
  spatie/laravel-permission
```

- [ ] **Step 5: Install JS packages**

```bash
npm install @inertiajs/vue3 vue @vitejs/plugin-vue \
  tailwindcss @tailwindcss/vite \
  ziggy-js \
  @heroicons/vue \
  axios
```

- [ ] **Step 6: Configure Vite**

Replace `vite.config.js`:
```js
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [
    laravel({ input: ['resources/js/app.js'], refresh: true }),
    vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
    tailwindcss(),
  ],
  resolve: { alias: { '@': '/resources/js' } },
})
```

- [ ] **Step 7: Configure Inertia middleware**

```bash
php artisan inertia:middleware
```

Add to `bootstrap/app.php` inside `withMiddleware`:
```php
$middleware->web(append: [
    \App\Http\Middleware\HandleInertiaRequests::class,
]);
```

- [ ] **Step 8: Publish Spatie config**

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

- [ ] **Step 9: Write .env.example**

```bash
cp .env .env.example
# Clear actual secrets in .env.example (leave keys, empty values)
```

- [ ] **Step 10: Commit**

```bash
git init
echo "vendor/\nnode_modules/\n.env\n.DS_Store\nstorage/*.key" > .gitignore
git add .
git commit -m "feat: initialise Laravel 13 portfolio CMS with all packages"
```

---

## Task 2: All database migrations

**Files:**
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php`
- Create: migrations for workspaces, projects, tasks, comments, media, pages, service_cards, settings, third_party_settings

- [ ] **Step 1: Update users migration to add google_id and avatar**

```php
// In create_users_table migration, add inside Schema::create:
$table->string('google_id')->nullable()->unique();
$table->string('avatar')->nullable();
// Remove $table->password() — OAuth only
```

- [ ] **Step 2: Create workspaces migration**

```bash
php artisan make:migration create_workspaces_table
```
```php
Schema::create('workspaces', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 3: Create projects migration**

```bash
php artisan make:migration create_projects_table
```
```php
Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->string('github_repo')->nullable();       // "owner/repo"
    $table->string('github_project_id')->nullable(); // GitHub Project number
    $table->string('cover_image')->nullable();
    $table->enum('status', ['active', 'archived'])->default('active');
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
});
```

- [ ] **Step 4: Create tasks migration**

```bash
php artisan make:migration create_tasks_table
```
```php
Schema::create('tasks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('parent_id')->nullable()->constrained('tasks')->cascadeOnDelete();
    $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('title');
    $table->longText('body')->nullable();
    $table->enum('status', ['open', 'in_progress', 'done', 'closed'])->default('open');
    $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
    $table->string('github_issue_id')->nullable();
    $table->string('github_issue_url')->nullable();
    $table->date('due_date')->nullable();
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
});
```

- [ ] **Step 5: Create comments migration**

```bash
php artisan make:migration create_comments_table
```
```php
Schema::create('comments', function (Blueprint $table) {
    $table->id();
    $table->morphs('commentable'); // commentable_type, commentable_id
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->longText('body');
    $table->timestamps();
});
```

- [ ] **Step 6: Create media migration**

```bash
php artisan make:migration create_media_table
```
```php
Schema::create('media', function (Blueprint $table) {
    $table->id();
    $table->morphs('mediable'); // mediable_type, mediable_id
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('disk')->default('local');
    $table->string('path');
    $table->string('filename');
    $table->string('mime_type');
    $table->unsignedBigInteger('size'); // bytes
    $table->string('alt_text')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 7: Create pages migration**

```bash
php artisan make:migration create_pages_table
```
```php
Schema::create('pages', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->string('template')->default('blank');
    $table->json('blocks')->nullable();
    $table->enum('status', ['draft', 'published'])->default('draft');
    $table->timestamp('published_at')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 8: Create service_cards migration**

```bash
php artisan make:migration create_service_cards_table
```
```php
Schema::create('service_cards', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('icon')->nullable();
    $table->string('image')->nullable();
    $table->json('tags')->nullable();
    $table->boolean('featured')->default(false);
    $table->unsignedInteger('sort_order')->default(0);
    $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
    $table->string('external_url')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 9: Create settings migration**

```bash
php artisan make:migration create_settings_table
```
```php
Schema::create('settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->text('value')->nullable();
    $table->string('group')->default('general');
    $table->timestamps();
});
```

- [ ] **Step 10: Create third_party_settings migration**

```bash
php artisan make:migration create_third_party_settings_table
```
```php
Schema::create('third_party_settings', function (Blueprint $table) {
    $table->id();
    $table->string('provider');
    $table->string('key');
    $table->text('value')->nullable();
    $table->string('group')->default('other');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->unique(['provider', 'key']);
});
```

- [ ] **Step 11: Run all migrations**

```bash
php artisan migrate
```
Expected: all tables created with no errors.

- [ ] **Step 12: Commit**

```bash
git add database/migrations/
git commit -m "feat: add all database migrations"
```

---

## Task 3: All models with relationships

**Files:** `app/Models/*.php`

- [ ] **Step 1: Update User model**

```php
<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Notifiable, HasRoles;

    protected $fillable = ['name', 'email', 'avatar', 'google_id', 'email_verified_at'];

    protected $hidden = ['remember_token'];

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
```

- [ ] **Step 2: Create Workspace model**

```bash
php artisan make:model Workspace
```
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Workspace extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    protected static function booted(): void
    {
        static::creating(fn ($w) => $w->slug ??= Str::slug($w->name));
    }

    public function projects(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Project::class);
    }
}
```

- [ ] **Step 3: Create Project model**

```bash
php artisan make:model Project
```
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'workspace_id','name','slug','description',
        'github_repo','github_project_id','cover_image','status','sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(fn ($p) => $p->slug ??= Str::slug($p->name));
    }

    public function workspace(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Task::class)->whereNull('parent_id')->orderBy('sort_order');
    }

    public function media(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
```

- [ ] **Step 4: Create Task model**

```bash
php artisan make:model Task
```
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'project_id','parent_id','assignee_id','title','body',
        'status','priority','github_issue_id','github_issue_url','due_date','sort_order',
    ];

    protected $casts = ['due_date' => 'date'];

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function subtasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('sort_order');
    }

    public function assignee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->latest();
    }

    public function media(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
```

- [ ] **Step 5: Create Comment model**

```bash
php artisan make:model Comment
```
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $fillable = ['user_id', 'body'];

    public function commentable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
```

- [ ] **Step 6: Create Media model**

```bash
php artisan make:model Media
```
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $fillable = ['user_id','disk','path','filename','mime_type','size','alt_text'];

    public function mediable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }
}
```

- [ ] **Step 7: Create Page model**

```bash
php artisan make:model Page
```
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    protected $fillable = ['name','slug','template','blocks','status','published_at'];

    protected $casts = ['blocks' => 'array', 'published_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn ($p) => $p->slug ??= Str::slug($p->name));
    }

    public function serviceCards(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ServiceCard::class);
    }
}
```

- [ ] **Step 8: Create ServiceCard model**

```bash
php artisan make:model ServiceCard
```
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCard extends Model
{
    protected $fillable = [
        'title','description','icon','image','tags',
        'featured','sort_order','page_id','external_url',
    ];

    protected $casts = ['tags' => 'array', 'featured' => 'boolean'];

    public function page(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
```

- [ ] **Step 9: Create Setting model**

```bash
php artisan make:model Setting
```
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }
}
```

- [ ] **Step 10: Create ThirdPartySetting model**

```bash
php artisan make:model ThirdPartySetting
```
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThirdPartySetting extends Model
{
    protected $fillable = ['provider', 'key', 'value', 'group', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public static function getValue(string $provider, string $key): ?string
    {
        return static::where('provider', $provider)
            ->where('key', $key)
            ->where('is_active', true)
            ->value('value');
    }
}
```

- [ ] **Step 11: Commit**

```bash
git add app/Models/
git commit -m "feat: define all Eloquent models with relationships"
```

---

## Task 4: Seeders

**Files:** `database/seeders/`

- [ ] **Step 1: Create RoleSeeder**

```bash
php artisan make:seeder RoleSeeder
```
```php
<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'manage users', 'manage roles',
            'manage pages', 'manage service-cards',
            'manage projects', 'manage tasks',
            'manage settings',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions);

        $editor = Role::firstOrCreate(['name' => 'editor']);
        $editor->syncPermissions([
            'manage pages','manage service-cards',
            'manage projects','manage tasks',
        ]);

        Role::firstOrCreate(['name' => 'viewer']);
    }
}
```

- [ ] **Step 2: Create SettingSeeder**

```bash
php artisan make:seeder SettingSeeder
```
```php
<?php
namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'site_name',        'value' => 'Portfolio',         'group' => 'general'],
            ['key' => 'site_tagline',      'value' => 'Full-Stack Dev',    'group' => 'general'],
            ['key' => 'meta_description',  'value' => '',                  'group' => 'seo'],
            ['key' => 'og_image',          'value' => '',                  'group' => 'seo'],
            ['key' => 'media_max_size_mb', 'value' => '50',               'group' => 'general'],
        ];

        foreach ($defaults as $row) {
            Setting::firstOrCreate(['key' => $row['key']], $row);
        }
    }
}
```

- [ ] **Step 3: Create UserSeeder**

```bash
php artisan make:seeder UserSeeder
```
```php
<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@portfolio.test'],
            ['name' => 'Admin', 'email_verified_at' => now()]
        );
        $admin->assignRole('admin');
    }
}
```

- [ ] **Step 4: Wire DatabaseSeeder**

```php
// database/seeders/DatabaseSeeder.php
public function run(): void
{
    $this->call([
        RoleSeeder::class,
        SettingSeeder::class,
        UserSeeder::class,
    ]);
}
```

- [ ] **Step 5: Run seeders**

```bash
php artisan db:seed
```
Expected: roles, permissions, default settings, admin user created.

- [ ] **Step 6: Write Pest test**

```bash
php artisan make:test SeedersTest
```
```php
// tests/Feature/SeedersTest.php
it('seeds roles and permissions', function () {
    expect(\Spatie\Permission\Models\Role::pluck('name')->toArray())
        ->toContain('admin', 'editor', 'viewer');
});

it('seeds default settings', function () {
    expect(\App\Models\Setting::where('key', 'site_name')->exists())->toBeTrue();
});
```

- [ ] **Step 7: Run tests**

```bash
php artisan test tests/Feature/SeedersTest.php
```
Expected: 2 passed.

- [ ] **Step 8: Commit**

```bash
git add database/seeders/ tests/Feature/SeedersTest.php
git commit -m "feat: add role, setting, and user seeders"
```

---

## Task 5: Gmail OAuth authentication

**Files:**
- Create: `app/Http/Controllers/Auth/GoogleController.php`
- Modify: `config/services.php`, `routes/web.php`
- Create: `resources/js/Pages/Auth/Login.vue`

- [ ] **Step 1: Write failing test**

```bash
php artisan make:test GoogleAuthTest
```
```php
// tests/Feature/GoogleAuthTest.php
it('redirects unauthenticated users to login', function () {
    $response = $this->get('/admin/dashboard');
    $response->assertRedirect('/login');
});

it('google redirect route exists', function () {
    $response = $this->get('/auth/google');
    $response->assertRedirectContains('accounts.google.com');
});
```

- [ ] **Step 2: Run to verify fail**

```bash
php artisan test tests/Feature/GoogleAuthTest.php
```
Expected: FAIL — routes don't exist yet.

- [ ] **Step 3: Add Google to services config**

```php
// config/services.php — add:
'google' => [
    'client_id'     => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect'      => env('GOOGLE_REDIRECT_URI'),
],
```

- [ ] **Step 4: Create GoogleController**

```php
<?php
// app/Http/Controllers/Auth/GoogleController.php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect('/login')->withErrors(['oauth' => 'Google login failed.']);
        }

        $user = User::updateOrCreate(
            ['google_id' => $googleUser->getId()],
            [
                'name'               => $googleUser->getName(),
                'email'              => $googleUser->getEmail(),
                'avatar'             => $googleUser->getAvatar(),
                'email_verified_at'  => now(),
            ]
        );

        if (! $user->hasAnyRole(['admin', 'editor', 'viewer'])) {
            $user->assignRole('viewer');
        }

        Auth::login($user);

        return redirect()->intended('/admin/dashboard');
    }
}
```

- [ ] **Step 5: Create Login.vue**

```vue
<!-- resources/js/Pages/Auth/Login.vue -->
<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-900">
    <div class="bg-white rounded-xl shadow-lg p-10 w-full max-w-sm text-center">
      <h1 class="text-2xl font-bold text-slate-800 mb-2">Portfolio CMS</h1>
      <p class="text-slate-500 text-sm mb-8">Sign in to manage your portfolio</p>
      <a
        href="/auth/google"
        class="flex items-center justify-center gap-3 w-full border border-slate-200 rounded-lg px-4 py-3 text-slate-700 font-medium hover:bg-slate-50 transition"
      >
        <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="w-5 h-5" alt="Google" />
        Continue with Google
      </a>
      <p v-if="$page.props.errors?.oauth" class="mt-4 text-red-500 text-sm">
        {{ $page.props.errors.oauth }}
      </p>
    </div>
  </div>
</template>
```

- [ ] **Step 6: Add routes**

```php
// routes/web.php
use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/login', fn () => Inertia::render('Auth/Login'))->name('login');
Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);
Route::post('/logout', function () {
    auth()->logout();
    return redirect('/login');
})->name('logout');
```

- [ ] **Step 7: Run tests**

```bash
php artisan test tests/Feature/GoogleAuthTest.php
```
Expected: 1 passes (redirect to login). The Google redirect test needs real credentials — skip with `--exclude-group=oauth` or mock Socialite in CI.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Auth/ config/services.php routes/web.php resources/js/Pages/Auth/
git commit -m "feat: add Gmail OAuth authentication via Socialite"
```

---

## Task 6: Admin shell — layout, middleware, dashboard

**Files:**
- Create: `app/Http/Middleware/EnsureRole.php`
- Create: `app/Http/Controllers/Admin/DashboardController.php`
- Create: `resources/js/Layouts/AdminLayout.vue`
- Create: `resources/js/Pages/Admin/Dashboard.vue`
- Create: `resources/js/app.js`
- Modify: `routes/web.php`

- [ ] **Step 1: Bootstrap Inertia in app.js**

```js
// resources/js/app.js
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from 'ziggy-js'

createInertiaApp({
  title: title => `${title} – Portfolio CMS`,
  resolve: name => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(ZiggyVue)
      .mount(el)
  },
  progress: { color: '#3b82f6' },
})
```

- [ ] **Step 2: Update root Blade view**

```php
<!-- resources/views/app.blade.php -->
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body class="antialiased bg-slate-50">
    @inertia
</body>
</html>
```

- [ ] **Step 3: Create EnsureRole middleware**

```php
<?php
// app/Http/Middleware/EnsureRole.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (! $request->user() || ! $request->user()->hasAnyRole($roles)) {
            abort(403, 'Forbidden.');
        }
        return $next($request);
    }
}
```

Register in `bootstrap/app.php`:
```php
$middleware->alias(['role' => \App\Http\Middleware\EnsureRole::class]);
```

- [ ] **Step 4: Create DashboardController**

```php
<?php
// app/Http/Controllers/Admin/DashboardController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\ServiceCard;
use App\Models\Task;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'pages'        => Page::count(),
                'serviceCards' => ServiceCard::count(),
                'openTasks'    => Task::whereNull('parent_id')->where('status', 'open')->count(),
                'users'        => User::count(),
            ],
            'recentTasks' => Task::whereNull('parent_id')
                ->with('project:id,name', 'assignee:id,name,avatar')
                ->latest()
                ->limit(5)
                ->get(['id','title','status','priority','project_id','assignee_id']),
        ]);
    }
}
```

- [ ] **Step 5: Add admin routes**

Append to `routes/web.php`:
```php
use App\Http\Controllers\Admin\DashboardController;

Route::middleware(['auth', 'role:admin,editor,viewer'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    });
```

- [ ] **Step 6: Create AdminLayout.vue**

```vue
<!-- resources/js/Layouts/AdminLayout.vue -->
<template>
  <div class="flex h-screen overflow-hidden bg-slate-50">
    <!-- Sidebar -->
    <aside class="w-56 bg-slate-900 flex flex-col flex-shrink-0">
      <div class="px-4 py-5 text-xs font-semibold text-slate-500 uppercase tracking-widest">
        Portfolio CMS
      </div>
      <nav class="flex-1 px-3 space-y-1">
        <Link
          v-for="item in nav"
          :key="item.href"
          :href="item.href"
          class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium transition"
          :class="isActive(item.href)
            ? 'bg-blue-600 text-white'
            : 'text-slate-400 hover:text-white hover:bg-slate-800'"
        >
          <span>{{ item.icon }}</span>
          {{ item.label }}
        </Link>
      </nav>
      <div class="px-4 py-4 border-t border-slate-800 flex items-center justify-between">
        <span class="text-xs text-slate-500">{{ $page.props.auth.user.name }}</span>
        <Link href="/logout" method="post" as="button" class="text-xs text-red-400 hover:text-red-300">
          Logout
        </Link>
      </div>
    </aside>

    <!-- Main -->
    <main class="flex-1 overflow-y-auto">
      <div class="p-6">
        <slot />
      </div>
    </main>
  </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { useRoute } from 'ziggy-js'

const page = usePage()
const nav = [
  { label: 'Dashboard',     icon: '⬛', href: '/admin/dashboard' },
  { label: 'Pages',         icon: '📄', href: '/admin/pages' },
  { label: 'Service Cards', icon: '🎴', href: '/admin/service-cards' },
  { label: 'Tasks',         icon: '✅', href: '/admin/tasks' },
  { label: 'Projects',      icon: '📁', href: '/admin/projects' },
  { label: 'GitHub',        icon: '🐙', href: '/admin/github' },
  { label: 'Users',         icon: '👥', href: '/admin/users' },
  { label: 'Settings',      icon: '⚙️', href: '/admin/settings' },
  { label: 'Integrations',  icon: '🔌', href: '/admin/integrations' },
]

const isActive = (href) => page.url.startsWith(href)
</script>
```

- [ ] **Step 7: Share auth user via HandleInertiaRequests**

```php
// app/Http/Middleware/HandleInertiaRequests.php — share() method:
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'auth' => [
            'user' => $request->user()?->only('id','name','email','avatar'),
            'roles' => $request->user()?->getRoleNames() ?? [],
        ],
    ]);
}
```

- [ ] **Step 8: Create Dashboard.vue**

```vue
<!-- resources/js/Pages/Admin/Dashboard.vue -->
<template>
  <AdminLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Dashboard</h1>
        <p class="text-sm text-slate-500">{{ today }}</p>
      </div>

      <!-- Stat cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Pages"         :value="stats.pages"        />
        <StatCard label="Service Cards" :value="stats.serviceCards"  />
        <StatCard label="Open Tasks"    :value="stats.openTasks"    color="amber" />
        <StatCard label="Users"         :value="stats.users"        />
      </div>

      <!-- Recent tasks -->
      <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">Recent Tasks</h2>
        <div v-if="recentTasks.length === 0" class="text-sm text-slate-400">No tasks yet.</div>
        <ul class="space-y-2">
          <li v-for="task in recentTasks" :key="task.id" class="flex items-center gap-3 text-sm">
            <span :class="statusColor(task.status)" class="w-2 h-2 rounded-full flex-shrink-0"></span>
            <span class="flex-1 text-slate-700">{{ task.title }}</span>
            <span class="text-xs text-slate-400">{{ task.project?.name }}</span>
          </li>
        </ul>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatCard from '@/Components/Admin/StatCard.vue'

defineProps({ stats: Object, recentTasks: Array })

const today = new Date().toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' })

const statusColor = s => ({
  open: 'bg-amber-400', in_progress: 'bg-blue-400', done: 'bg-green-400', closed: 'bg-slate-300'
}[s] ?? 'bg-slate-300')
</script>
```

- [ ] **Step 9: Create StatCard component**

```bash
mkdir -p resources/js/Components/Admin
```
```vue
<!-- resources/js/Components/Admin/StatCard.vue -->
<template>
  <div class="bg-white border border-slate-200 rounded-xl p-5">
    <p class="text-xs text-slate-400 uppercase tracking-wide">{{ label }}</p>
    <p class="text-3xl font-bold mt-1" :class="colorClass">{{ value }}</p>
  </div>
</template>

<script setup>
const props = defineProps({ label: String, value: Number, color: { type: String, default: 'slate' } })
const colorClass = { slate: 'text-slate-800', amber: 'text-amber-500', blue: 'text-blue-600' }[props.color]
</script>
```

- [ ] **Step 10: Build assets and verify**

```bash
npm run build
php artisan serve
```
Visit `http://portfolio.test/login` → Google login button visible.  
Visit `http://portfolio.test/admin/dashboard` → redirects to `/login` (unauthenticated).

- [ ] **Step 11: Write test**

```php
// tests/Feature/DashboardTest.php
it('redirects guest from dashboard', function () {
    $this->get('/admin/dashboard')->assertRedirect('/login');
});

it('shows dashboard to authenticated user', function () {
    $user = \App\Models\User::factory()->create();
    $user->assignRole('viewer');
    $this->actingAs($user)->get('/admin/dashboard')->assertOk();
});
```

- [ ] **Step 12: Run tests**

```bash
php artisan test tests/Feature/DashboardTest.php
```
Expected: 2 passed.

- [ ] **Step 13: Commit**

```bash
git add .
git commit -m "feat: admin shell with Inertia + Vue 3, dashboard, role middleware"
```

---

## Phase 1 Complete ✅

At this point:
- Laravel 13 installed at `/Users/nakul/Herd/Portfolio`
- All migrations run, all models defined
- Seeders create roles, settings, admin user
- Gmail OAuth login flow works
- Admin dashboard renders with stat cards
- Role middleware protecting `/admin/*`

**Next:** Phase 2 — Content Management (Page Builder + Service Cards)
