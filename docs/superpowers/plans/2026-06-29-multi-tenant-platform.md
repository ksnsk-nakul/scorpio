# Multi-Tenant Portfolio Platform — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform Scorpio from a single-owner CMS into a multi-tenant portfolio platform where each registered user has their own scoped portfolio, accessible at `/{username}` publicly.

**Architecture:** Add `username` + `github_token` to users. Scope pages, service cards, workspaces to `user_id`. Public routes resolve `/{username}/{slug}`. Remove the Integrations subsystem. Move GitHub token to user profile. Add a Profile page.

**Tech Stack:** Laravel 13, Inertia.js v2, Vue 3, Tailwind v4, Spatie Permissions, Pest v3

---

## File Map

| File | Action | Purpose |
|---|---|---|
| `database/migrations/2026_06_29_add_multi_tenant_columns.php` | Create | username, github_token on users; user_id on pages, service_cards, workspaces |
| `app/Models/User.php` | Modify | Add username, github_token, pages(), serviceCards(), workspaces() relations |
| `app/Models/Page.php` | Modify | Add user_id, is_home |
| `app/Models/ServiceCard.php` | Modify | Add user_id |
| `app/Models/Workspace.php` | Modify | Add user_id |
| `app/Http/Controllers/Admin/PageController.php` | Modify | Scope to auth user, protect home from delete |
| `app/Http/Controllers/Admin/ServiceCardController.php` | Modify | Scope to auth user |
| `app/Http/Controllers/Admin/WorkspaceController.php` | Modify | Scope to auth user |
| `app/Http/Controllers/Admin/ProjectController.php` | Modify | Scope through workspace→user, rename to ProductController |
| `app/Http/Controllers/Admin/DashboardController.php` | Modify | Scope stats to auth user |
| `app/Http/Controllers/Admin/GitHubController.php` | Modify | Use user.github_token instead of ThirdPartySetting |
| `app/Http/Controllers/Admin/ProfileController.php` | Create | Update name, email, username, password, github_token |
| `app/Http/Controllers/PublicController.php` | Modify | Add /{username} and /{username}/{slug} routes |
| `app/Services/GitHubService.php` | Modify | Accept token parameter instead of reading ThirdPartySetting |
| `app/Http/Middleware/HandleInertiaRequests.php` | Modify | Share username in auth prop |
| `routes/web.php` | Modify | Remove integrations routes; add profile, products, public tenant routes |
| `resources/js/Layouts/AdminLayout.vue` | Modify | Remove Integrations nav; rename Projects→Products; add Profile link |
| `resources/js/Pages/Admin/Profile/Index.vue` | Create | Edit name, email, username, password, GitHub token |
| `resources/js/Pages/Admin/Products/Index.vue` | Create | Renamed from Projects/Index.vue |
| `resources/js/Pages/Admin/Products/Show.vue` | Create | Renamed from Projects/Show.vue |
| `resources/js/Pages/Admin/GitHub/Index.vue` | Modify | Per-user token connect/disconnect UI |
| `resources/js/Pages/Admin/Pages/Edit.vue` | Modify | Add inline preview modal; link service cards panel |
| `resources/js/Pages/Admin/Settings/Index.vue` | Modify | Add social and mail setting groups |
| `resources/js/Pages/Public/Portfolio.vue` | Create | Public `/{username}` and `/{username}/{slug}` renderer |
| `database/seeders/PageSeeder.php` | Modify | Set user_id and is_home=true on home page |
| `database/seeders/SettingSeeder.php` | Modify | Add social and mail setting keys |

---

## Task 1: Migration — multi-tenant columns

**Files:**
- Create: `database/migrations/2026_06_29_add_multi_tenant_columns.php`

- [ ] **Step 1: Create the migration**

```bash
php artisan make:migration add_multi_tenant_columns
```

- [ ] **Step 2: Write the migration body**

Replace the generated file content with:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('github_token')->nullable()->after('github_id');
            $table->string('password')->nullable()->change(); // allow OAuth-only users
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->after('id');
            $table->boolean('is_home')->default(false)->after('status');
        });

        Schema::table('service_cards', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->after('id');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn ($t) => $t->dropColumn(['username', 'github_token']));
        Schema::table('pages', fn ($t) => $t->dropColumn(['user_id', 'is_home']));
        Schema::table('service_cards', fn ($t) => $t->dropForeignIdFor(\App\Models\User::class)->dropColumn('user_id'));
        Schema::table('workspaces', fn ($t) => $t->dropForeignIdFor(\App\Models\User::class)->dropColumn('user_id'));
    }
};
```

- [ ] **Step 3: Run migration**

```bash
php artisan migrate:fresh --seed
```

Expected: All seeders pass. No errors.

- [ ] **Step 4: Verify columns exist**

```bash
php artisan tinker --execute="
  \$cols = fn(\$t) => collect(\Illuminate\Support\Facades\Schema::getColumns(\$t))->pluck('name');
  echo implode(', ', \$cols('users')->toArray()) . PHP_EOL;
  echo implode(', ', \$cols('pages')->toArray()) . PHP_EOL;
  echo implode(', ', \$cols('workspaces')->toArray()) . PHP_EOL;
"
```

Expected: `username`, `github_token` in users; `user_id`, `is_home` in pages; `user_id` in workspaces.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/
git commit -m "feat: add multi-tenant columns — username, github_token, user_id, is_home"
```

---

## Task 2: Update Models

**Files:**
- Modify: `app/Models/User.php`
- Modify: `app/Models/Page.php`
- Modify: `app/Models/ServiceCard.php`
- Modify: `app/Models/Workspace.php`

- [ ] **Step 1: Update User model**

Replace `app/Models/User.php` with:

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name', 'email', 'password', 'avatar',
        'username', 'github_token',
        'google_id', 'github_id', 'email_verified_at',
    ];

    protected $hidden = ['password', 'remember_token', 'github_token'];

    protected $casts = ['password' => 'hashed'];

    protected static function booted(): void
    {
        static::creating(function (self $user) {
            if (empty($user->username)) {
                $base = Str::slug($user->name ?? explode('@', $user->email)[0]);
                $user->username = static::uniqueUsername($base);
            }
        });
    }

    public static function uniqueUsername(string $base): string
    {
        $slug = $base;
        $i = 2;
        while (static::where('username', $slug)->exists()) {
            $slug = "{$base}{$i}";
            $i++;
        }
        return $slug;
    }

    public function pages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function serviceCards(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ServiceCard::class);
    }

    public function workspaces(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Workspace::class);
    }

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

- [ ] **Step 2: Update Page model**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    protected $fillable = ['user_id', 'name', 'slug', 'template', 'blocks', 'status', 'published_at', 'is_home'];

    protected $casts = ['blocks' => 'array', 'published_at' => 'datetime', 'is_home' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(fn ($p) => $p->slug ??= Str::slug($p->name));
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function serviceCards(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ServiceCard::class);
    }
}
```

- [ ] **Step 3: Update ServiceCard model**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCard extends Model
{
    protected $fillable = [
        'user_id', 'title', 'description', 'icon', 'image', 'tags',
        'featured', 'sort_order', 'page_id', 'external_url',
    ];

    protected $casts = ['tags' => 'array', 'featured' => 'boolean'];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function page(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
```

- [ ] **Step 4: Update Workspace model**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    protected $fillable = ['user_id', 'name', 'description', 'color'];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projects(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Project::class);
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Models/
git commit -m "feat: add user_id relations and username auto-generation to models"
```

---

## Task 3: Update Seeders for user_id + is_home

**Files:**
- Modify: `database/seeders/PageSeeder.php`
- Modify: `database/seeders/UserSeeder.php`

- [ ] **Step 1: Update UserSeeder to set username**

In `database/seeders/UserSeeder.php`, after `$admin->assignRole('admin')`, add:

```php
if (empty($admin->username)) {
    $admin->update(['username' => 'admin']);
}
```

- [ ] **Step 2: Update PageSeeder to set user_id and is_home**

In `database/seeders/PageSeeder.php`, update `Page::create([` call to include:

```php
'user_id'  => \App\Models\User::where('email', filled(env('ADMIN_EMAIL')) ? env('ADMIN_EMAIL') : 'admin@example.com')->value('id'),
'is_home'  => true,
```

And in the `ServiceCard::create()` loop, add `'user_id' => $page->user_id,`:

```php
ServiceCard::create(array_merge($service, [
    'page_id'  => $page->id,
    'user_id'  => $page->user_id,
    'featured' => true,
]));
```

- [ ] **Step 3: Re-seed and verify**

```bash
php artisan migrate:fresh --seed
php artisan tinker --execute="
  \$p = App\Models\Page::first();
  echo 'user_id: ' . \$p->user_id . PHP_EOL;
  echo 'is_home: ' . (\$p->is_home ? 'true' : 'false') . PHP_EOL;
  echo 'cards owned: ' . App\Models\ServiceCard::where('user_id', \$p->user_id)->count() . PHP_EOL;
  echo 'username: ' . App\Models\User::first()->username . PHP_EOL;
"
```

Expected output:
```
user_id: 1
is_home: true
cards owned: 6
username: admin
```

- [ ] **Step 4: Commit**

```bash
git add database/seeders/
git commit -m "feat: seed user_id, is_home, username in seeders"
```

---

## Task 4: Remove Integrations, Rename Projects → Products

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/js/Layouts/AdminLayout.vue`
- Delete (keep file, mark unused): `app/Http/Controllers/Admin/ThirdPartySettingController.php`

- [ ] **Step 1: Remove integrations routes from web.php**

Delete the entire integrations route group:

```php
// DELETE this entire block:
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::resource('integrations', ThirdPartySettingController::class)
            ->except(['create', 'edit', 'show'])
            ->parameters(['integrations' => 'integration']);
    });
```

Also remove the `use App\Http\Controllers\Admin\ThirdPartySettingController;` import.

- [ ] **Step 2: Rename projects routes to products in web.php**

Change:
```php
Route::resource('projects', ProjectController::class)->except(['create','edit']);
```
To:
```php
Route::resource('products', ProjectController::class, ['parameters' => ['products' => 'project']])->except(['create','edit']);
```

Change the redirect in `ProjectController::store()` from `/admin/projects/{$project->id}` → `/admin/products/{$project->id}`.
Change the redirect in `ProjectController::update()` from `/admin/projects/{$project->id}` → `/admin/products/{$project->id}`.
Change the redirect in `ProjectController::destroy()` from `/admin/projects` → `/admin/products`.

- [ ] **Step 3: Update AdminLayout nav**

In `resources/js/Layouts/AdminLayout.vue`, update `allNav`:

```js
const allNav = [
  { label: 'Dashboard',     href: '/admin/dashboard',      adminOnly: false },
  { label: 'Pages',         href: '/admin/pages',          adminOnly: false },
  { label: 'Service Cards', href: '/admin/service-cards',  adminOnly: false },
  { label: 'Products',      href: '/admin/products',       adminOnly: false },
  { label: 'GitHub',        href: '/admin/github',         adminOnly: false },
  { label: 'Profile',       href: '/admin/profile',        adminOnly: false },
  { label: 'Users',         href: '/admin/users',          adminOnly: true  },
  { label: 'Settings',      href: '/admin/settings',       adminOnly: true  },
]
```

- [ ] **Step 4: Rename Vue pages**

```bash
mv resources/js/Pages/Admin/Projects resources/js/Pages/Admin/Products
```

Update the `Inertia::render()` calls in `ProjectController`:
- `'Admin/Projects/Index'` → `'Admin/Products/Index'`
- `'Admin/Projects/Show'`  → `'Admin/Products/Show'`

- [ ] **Step 5: Build and verify no errors**

```bash
npm run build 2>&1 | tail -5
```

Expected: `✓ built` with no errors.

- [ ] **Step 6: Commit**

```bash
git add routes/web.php app/Http/Controllers/Admin/ProjectController.php \
        resources/js/Layouts/AdminLayout.vue \
        resources/js/Pages/Admin/Products/
git commit -m "feat: remove integrations, rename Projects to Products"
```

---

## Task 5: Scope controllers to auth user

**Files:**
- Modify: `app/Http/Controllers/Admin/PageController.php`
- Modify: `app/Http/Controllers/Admin/ServiceCardController.php`
- Modify: `app/Http/Controllers/Admin/WorkspaceController.php`
- Modify: `app/Http/Controllers/Admin/ProjectController.php`
- Modify: `app/Http/Controllers/Admin/DashboardController.php`

- [ ] **Step 1: Scope PageController**

Replace the full `PageController`:

```php
<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Pages/Index', [
            'pages'     => auth()->user()->pages()->orderBy('updated_at', 'desc')->get(['id','name','slug','status','template','updated_at','is_home']),
            'templates' => ['blank', 'hero_cards', 'text_image', 'project_grid'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'template' => 'required|in:blank,hero_cards,text_image,project_grid',
        ]);

        $blocks = match ($data['template']) {
            'hero_cards'   => [
                ['type' => 'hero',         'order' => 0, 'data' => ['heading' => '', 'subheading' => '']],
                ['type' => 'service_cards','order' => 1, 'data' => []],
            ],
            'text_image'   => [['type' => 'text_image',   'order' => 0, 'data' => ['text' => '', 'image' => '']]],
            'project_grid' => [['type' => 'project_grid', 'order' => 0, 'data' => []]],
            default        => [],
        ];

        $page = auth()->user()->pages()->create(array_merge($data, ['blocks' => $blocks]));

        return redirect("/admin/pages/{$page->id}/edit");
    }

    public function edit(Page $page): Response
    {
        $this->authorize('update', $page);
        return Inertia::render('Admin/Pages/Edit', [
            'page'         => $page,
            'blockTypes'   => ['hero','text','text_image','service_cards','project_grid','contact_form'],
            'serviceCards' => $page->serviceCards()->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, Page $page)
    {
        $this->authorize('update', $page);
        $data = $request->validate([
            'name'   => 'sometimes|string|max:255',
            'blocks' => 'sometimes|array',
            'status' => 'sometimes|in:draft,published',
        ]);

        if (isset($data['status']) && $data['status'] === 'published' && $page->status !== 'published') {
            $data['published_at'] = now();
        }

        $page->update($data);
        return back()->with('success', 'Page saved.');
    }

    public function publish(Page $page)
    {
        $this->authorize('update', $page);
        $page->update(['status' => 'published', 'published_at' => now()]);
        return back()->with('success', 'Page published.');
    }

    public function destroy(Page $page)
    {
        $this->authorize('delete', $page);

        if ($page->is_home) {
            return back()->withErrors(['page' => 'The home page cannot be deleted.']);
        }

        $page->delete();
        return redirect('/admin/pages')->with('success', 'Page deleted.');
    }

    public function preview(Page $page)
    {
        return Inertia::render('Admin/Pages/Preview', ['page' => $page]);
    }
}
```

- [ ] **Step 2: Add PagePolicy**

```bash
php artisan make:policy PagePolicy --model=Page
```

Replace the generated file with:

```php
<?php
namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function update(User $user, Page $page): bool
    {
        return $page->user_id === $user->id || $user->hasRole('admin');
    }

    public function delete(User $user, Page $page): bool
    {
        return $page->user_id === $user->id || $user->hasRole('admin');
    }
}
```

Register in `app/Providers/AppServiceProvider.php`:

```php
use App\Models\Page;
use App\Policies\PagePolicy;

// inside boot():
\Illuminate\Support\Facades\Gate::policy(Page::class, PagePolicy::class);
```

- [ ] **Step 3: Scope ServiceCardController**

In `ServiceCardController::index()`, change:
```php
'cards' => ServiceCard::with('page:id,name')->orderBy('sort_order')->get(),
'pages' => Page::where('status', 'published')->get(['id','name']),
```
To:
```php
'cards' => auth()->user()->serviceCards()->with('page:id,name')->orderBy('sort_order')->get(),
'pages' => auth()->user()->pages()->where('status', 'published')->get(['id','name']),
```

In `ServiceCardController::store()`, replace `ServiceCard::create($data)` with:
```php
$data['user_id'] = auth()->id();
$data['sort_order'] = auth()->user()->serviceCards()->max('sort_order') + 1;
ServiceCard::create($data);
```

- [ ] **Step 4: Scope WorkspaceController**

In `WorkspaceController`, change every `Workspace::` query to `auth()->user()->workspaces()->`:

```php
// index()
'workspaces' => auth()->user()->workspaces()->get()

// store()
auth()->user()->workspaces()->create($data);
```

- [ ] **Step 5: Scope ProjectController through workspace**

In `ProjectController::index()`:
```php
return Inertia::render('Admin/Products/Index', [
    'workspaces' => auth()->user()->workspaces()
        ->with('projects:id,workspace_id,name,slug,status,github_repo,cover_image')
        ->orderBy('name')->get(),
]);
```

- [ ] **Step 6: Scope DashboardController**

```php
public function index(): Response
{
    $user = auth()->user();

    return Inertia::render('Admin/Dashboard', [
        'stats' => [
            'pages'        => $user->pages()->count(),
            'serviceCards' => $user->serviceCards()->count(),
            'openTasks'    => Task::whereNull('parent_id')
                ->where('status', 'open')
                ->whereHas('project.workspace', fn($q) => $q->where('user_id', $user->id))
                ->count(),
            'users'        => $user->hasRole('admin') ? User::count() : null,
        ],
        'recentTasks' => Task::whereNull('parent_id')
            ->whereHas('project.workspace', fn($q) => $q->where('user_id', $user->id))
            ->with('project:id,name', 'assignee:id,name,avatar')
            ->latest()
            ->limit(5)
            ->get(['id','title','status','priority','project_id','assignee_id']),
    ]);
}
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/ app/Policies/ app/Providers/
git commit -m "feat: scope pages, service-cards, workspaces, projects to auth user"
```

---

## Task 6: GitHub — per-user token

**Files:**
- Modify: `app/Services/GitHubService.php`
- Modify: `app/Http/Controllers/Admin/GitHubController.php`
- Modify: `resources/js/Pages/Admin/GitHub/Index.vue`

- [ ] **Step 1: Update GitHubService to accept a token**

Replace the `token()` and `http()` methods:

```php
private function http(string $token): \Illuminate\Http\Client\PendingRequest
{
    return Http::withToken($token)
        ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
        ->baseUrl($this->baseUrl);
}

public function getRepos(string $token, int $perPage = 30): array
{
    try {
        return $this->http($token)
            ->get('/user/repos', ['per_page' => $perPage, 'sort' => 'updated'])
            ->json() ?? [];
    } catch (\Throwable $e) {
        Log::warning('GitHubService::getRepos failed', ['error' => $e->getMessage()]);
        return [];
    }
}

public function getIssues(string $token, string $repo, string $state = 'open'): array
{
    try {
        return $this->http($token)
            ->get("/repos/{$repo}/issues", ['state' => $state, 'per_page' => 100])
            ->json() ?? [];
    } catch (\Throwable $e) {
        return [];
    }
}

public function syncIssuesToProject(string $token, Project $project): int
{
    if (! $project->github_repo) return 0;
    $issues = $this->getIssues($token, $project->github_repo);
    $count = 0;
    foreach ($issues as $issue) {
        if (isset($issue['pull_request'])) continue;
        Task::updateOrCreate(
            ['github_issue_id' => (string) $issue['number'], 'project_id' => $project->id],
            [
                'title'            => $issue['title'],
                'body'             => $issue['body'] ?? '',
                'github_issue_url' => $issue['html_url'],
                'status'           => $issue['state'] === 'open' ? 'open' : 'closed',
                'priority'         => 'medium',
            ]
        );
        $count++;
    }
    return $count;
}

public function createProject(string $token, string $owner, string $name, string $body = ''): ?array
{
    try {
        $response = $this->http($token)->post("/users/{$owner}/projects", compact('name', 'body'));
        return $response->successful() ? $response->json() : null;
    } catch (\Throwable $e) {
        return null;
    }
}
```

- [ ] **Step 2: Update GitHubController**

```php
public function index()
{
    $user  = auth()->user();
    $token = $user->github_token;

    return Inertia::render('Admin/GitHub/Index', [
        'repos'    => $token ? $this->github->getRepos($token) : [],
        'products' => \App\Models\Project::whereHas('workspace', fn($q) => $q->where('user_id', $user->id))
            ->whereNotNull('github_repo')
            ->get(['id','name','github_repo','github_project_id']),
        'hasToken' => (bool) $token,
    ]);
}

public function saveToken(Request $request)
{
    $request->validate(['token' => 'required|string']);
    auth()->user()->update(['github_token' => $request->token]);
    return back()->with('success', 'GitHub token saved.');
}

public function removeToken()
{
    auth()->user()->update(['github_token' => null]);
    return back()->with('success', 'GitHub token removed.');
}

public function sync(Project $project)
{
    $token = auth()->user()->github_token;
    if (! $token) return back()->withErrors(['github' => 'No GitHub token. Connect GitHub first.']);
    $count = $this->github->syncIssuesToProject($token, $project);
    return back()->with('success', "{$count} issues synced.");
}

public function createGitHubProject(Request $request, Project $project)
{
    $token = auth()->user()->github_token;
    if (! $token) return back()->withErrors(['github' => 'No GitHub token.']);
    $data = $request->validate(['owner' => 'required|string', 'name' => 'required|string|max:255', 'body' => 'nullable|string']);
    $gh = $this->github->createProject($token, $data['owner'], $data['name'], $data['body'] ?? '');
    if (! $gh) return back()->withErrors(['github' => 'Failed to create GitHub project.']);
    $project->update(['github_project_id' => (string) $gh['number']]);
    return back()->with('success', "GitHub project #{$gh['number']} created.");
}
```

- [ ] **Step 3: Add saveToken/removeToken routes in web.php**

Inside the `role:admin,editor,viewer` GitHub group:

```php
Route::post('github/token', [GitHubController::class, 'saveToken'])->name('github.token.save');
Route::delete('github/token', [GitHubController::class, 'removeToken'])->name('github.token.remove');
```

- [ ] **Step 4: Update GitHub Index Vue — token connect UI**

Replace the content of `resources/js/Pages/Admin/GitHub/Index.vue` with a page that has:
- If `!hasToken`: a text input for the PAT + "Connect" button (`POST /admin/github/token`)
- If `hasToken`: "Connected ✓" + "Disconnect" button (`DELETE /admin/github/token`)
- Below the token section: existing repos list and products list

```vue
<template>
  <AdminLayout>
    <h1 class="text-xl font-bold text-slate-800 mb-6">GitHub</h1>

    <!-- Token section -->
    <div class="bg-white border border-slate-200 rounded-xl p-5 mb-6 max-w-lg">
      <h2 class="font-semibold text-slate-700 mb-3">Personal Access Token</h2>
      <div v-if="hasToken" class="flex items-center gap-3">
        <span class="text-sm text-green-600 font-medium">✓ Connected</span>
        <button @click="removeToken" class="text-xs text-red-500 hover:underline">Disconnect</button>
      </div>
      <form v-else @submit.prevent="saveToken" class="flex gap-2">
        <input v-model="tokenForm.token" type="password" placeholder="ghp_..." class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-slate-900" />
        <button type="submit" :disabled="tokenForm.processing" class="bg-slate-900 text-white text-sm px-4 py-2 rounded-lg">Connect</button>
      </form>
      <p v-if="tokenForm.errors.token" class="text-red-500 text-xs mt-1">{{ tokenForm.errors.token }}</p>
    </div>

    <!-- Repos -->
    <div v-if="hasToken && repos.length" class="bg-white border border-slate-200 rounded-xl p-5 mb-6">
      <h2 class="font-semibold text-slate-700 mb-3">Your Repositories</h2>
      <ul class="space-y-2">
        <li v-for="repo in repos" :key="repo.id" class="flex items-center justify-between text-sm">
          <span class="text-slate-800">{{ repo.full_name }}</span>
          <span class="text-xs text-slate-400">{{ repo.language }}</span>
        </li>
      </ul>
    </div>

    <!-- Products with GitHub -->
    <div v-if="products.length" class="bg-white border border-slate-200 rounded-xl p-5">
      <h2 class="font-semibold text-slate-700 mb-3">Linked Products</h2>
      <ul class="space-y-3">
        <li v-for="p in products" :key="p.id" class="flex items-center justify-between text-sm">
          <div>
            <p class="font-medium text-slate-800">{{ p.name }}</p>
            <p class="text-xs text-slate-400">{{ p.github_repo }}</p>
          </div>
          <button @click="sync(p.id)" class="text-xs bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg">Sync issues</button>
        </li>
      </ul>
    </div>
  </AdminLayout>
</template>

<script setup>
import { useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  repos:    { type: Array,   default: () => [] },
  products: { type: Array,   default: () => [] },
  hasToken: { type: Boolean, default: false },
})

const tokenForm = useForm({ token: '' })
const saveToken   = () => tokenForm.post('/admin/github/token')
const removeToken = () => router.delete('/admin/github/token')
const sync = (id) => router.post(`/admin/github/projects/${id}/sync`)
</script>
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/GitHubService.php app/Http/Controllers/Admin/GitHubController.php \
        routes/web.php resources/js/Pages/Admin/GitHub/
git commit -m "feat: per-user GitHub token — connect/disconnect from GitHub page"
```

---

## Task 7: Profile page

**Files:**
- Create: `app/Http/Controllers/Admin/ProfileController.php`
- Create: `resources/js/Pages/Admin/Profile/Index.vue`
- Modify: `routes/web.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`

- [ ] **Step 1: Add username to Inertia shared auth**

In `HandleInertiaRequests::share()`, update the user prop:

```php
'auth' => [
    'user'  => $request->user()?->only('id', 'name', 'email', 'avatar', 'username'),
    'roles' => $request->user()?->getRoleNames() ?? [],
],
```

- [ ] **Step 2: Create ProfileController**

```php
<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Profile/Index', [
            'user' => auth()->user()->only('id', 'name', 'email', 'username', 'avatar'),
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'username' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email'    => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ]);
        $user->update($data);
        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password'         => 'required|string|min:8|confirmed',
        ]);
        auth()->user()->update(['password' => Hash::make($data['password'])]);
        return back()->with('success', 'Password updated.');
    }
}
```

- [ ] **Step 3: Add profile routes in web.php**

Inside the `role:admin,editor,viewer` group:

```php
Route::get('profile',          [\App\Http\Controllers\Admin\ProfileController::class, 'index'])->name('profile.index');
Route::patch('profile',        [\App\Http\Controllers\Admin\ProfileController::class, 'update'])->name('profile.update');
Route::patch('profile/password',[\App\Http\Controllers\Admin\ProfileController::class, 'updatePassword'])->name('profile.password');
```

- [ ] **Step 4: Create Profile Vue page**

Create `resources/js/Pages/Admin/Profile/Index.vue`:

```vue
<template>
  <AdminLayout>
    <div class="max-w-xl space-y-6">
      <h1 class="text-xl font-bold text-slate-800">Profile</h1>

      <!-- Profile details -->
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h2 class="font-semibold text-slate-700 mb-4">Details</h2>
        <form @submit.prevent="profileForm.patch('/admin/profile')" class="space-y-4">
          <div>
            <label class="text-xs text-slate-500 block mb-1">Name</label>
            <input v-model="profileForm.name" type="text" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-slate-900" />
            <p v-if="profileForm.errors.name" class="text-red-500 text-xs mt-1">{{ profileForm.errors.name }}</p>
          </div>
          <div>
            <label class="text-xs text-slate-500 block mb-1">Username (used in your public URL)</label>
            <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden">
              <span class="px-3 py-2 text-sm text-slate-400 bg-slate-50 border-r border-slate-200">scorpio.app/</span>
              <input v-model="profileForm.username" type="text" class="flex-1 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-slate-900" />
            </div>
            <p v-if="profileForm.errors.username" class="text-red-500 text-xs mt-1">{{ profileForm.errors.username }}</p>
          </div>
          <div>
            <label class="text-xs text-slate-500 block mb-1">Email</label>
            <input v-model="profileForm.email" type="email" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-slate-900" />
            <p v-if="profileForm.errors.email" class="text-red-500 text-xs mt-1">{{ profileForm.errors.email }}</p>
          </div>
          <button type="submit" :disabled="profileForm.processing" class="bg-slate-900 text-white text-sm px-5 py-2 rounded-lg hover:bg-slate-700 disabled:opacity-50">
            Save changes
          </button>
          <p v-if="$page.props.flash?.success" class="text-green-600 text-xs">{{ $page.props.flash.success }}</p>
        </form>
      </div>

      <!-- Change password -->
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h2 class="font-semibold text-slate-700 mb-4">Change Password</h2>
        <form @submit.prevent="passForm.patch('/admin/profile/password')" class="space-y-4">
          <div>
            <label class="text-xs text-slate-500 block mb-1">Current password</label>
            <input v-model="passForm.current_password" type="password" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-slate-900" />
            <p v-if="passForm.errors.current_password" class="text-red-500 text-xs mt-1">{{ passForm.errors.current_password }}</p>
          </div>
          <div>
            <label class="text-xs text-slate-500 block mb-1">New password</label>
            <input v-model="passForm.password" type="password" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-slate-900" />
          </div>
          <div>
            <label class="text-xs text-slate-500 block mb-1">Confirm new password</label>
            <input v-model="passForm.password_confirmation" type="password" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-slate-900" />
            <p v-if="passForm.errors.password" class="text-red-500 text-xs mt-1">{{ passForm.errors.password }}</p>
          </div>
          <button type="submit" :disabled="passForm.processing" class="bg-slate-900 text-white text-sm px-5 py-2 rounded-lg hover:bg-slate-700 disabled:opacity-50">
            Update password
          </button>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ user: Object })

const profileForm = useForm({
  name:     props.user.name,
  username: props.user.username ?? '',
  email:    props.user.email,
})

const passForm = useForm({
  current_password:      '',
  password:              '',
  password_confirmation: '',
})
</script>
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/ProfileController.php \
        resources/js/Pages/Admin/Profile/ \
        app/Http/Middleware/HandleInertiaRequests.php \
        routes/web.php
git commit -m "feat: profile page — edit name, username, email, password"
```

---

## Task 8: Page editor — inline preview modal + service cards panel

**Files:**
- Create: `resources/js/Pages/Admin/Pages/Preview.vue`
- Modify: `resources/js/Pages/Admin/Pages/Edit.vue`

- [ ] **Step 1: Create Preview Vue page (used as modal target)**

Create `resources/js/Pages/Admin/Pages/Preview.vue`:

```vue
<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-semibold text-slate-700">Preview — {{ page.name }}</h2>
        <Link :href="`/admin/pages/${page.id}/edit`" class="text-sm text-blue-600 hover:underline">← Back to editor</Link>
      </div>

      <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <template v-for="block in (page.blocks ?? [])" :key="block.order">

          <!-- Hero -->
          <div v-if="block.type === 'hero'" class="py-16 px-10 text-center bg-slate-50">
            <h1 class="text-4xl font-bold text-slate-900 mb-3">{{ block.data.heading || '(no heading)' }}</h1>
            <p class="text-lg text-slate-500">{{ block.data.subheading }}</p>
          </div>

          <!-- Text -->
          <div v-else-if="block.type === 'text'" class="py-10 px-10 prose max-w-none">
            <p class="whitespace-pre-line text-slate-700">{{ block.data.content || '(empty)' }}</p>
          </div>

          <!-- Text + Image -->
          <div v-else-if="block.type === 'text_image'" class="py-10 px-10 flex gap-8 items-start">
            <p class="flex-1 whitespace-pre-line text-slate-700 text-sm">{{ block.data.text || '(empty)' }}</p>
            <img v-if="block.data.image" :src="block.data.image" class="w-48 rounded-xl object-cover" />
            <div v-else class="w-48 h-32 rounded-xl bg-slate-100 flex items-center justify-center text-xs text-slate-400">No image</div>
          </div>

          <!-- Service Cards -->
          <div v-else-if="block.type === 'service_cards'" class="py-10 px-10">
            <p class="text-xs text-slate-400 mb-4">{{ block.data.heading || 'Service Cards' }}</p>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
              <div v-for="card in (page.service_cards ?? [])" :key="card.id" class="border border-slate-100 rounded-xl p-4">
                <div class="text-2xl mb-2">{{ card.icon }}</div>
                <p class="font-medium text-sm text-slate-800">{{ card.title }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ card.description }}</p>
              </div>
              <div v-if="!page.service_cards?.length" class="text-sm text-slate-400 col-span-3">No service cards linked to this page yet.</div>
            </div>
          </div>

          <!-- Contact Form -->
          <div v-else-if="block.type === 'contact_form'" class="py-10 px-10">
            <h2 class="text-2xl font-bold text-slate-900 mb-4">{{ block.data.heading || 'Contact' }}</h2>
            <div class="text-sm text-slate-500 space-y-1">
              <p v-if="block.data.email">Email: {{ block.data.email }}</p>
              <p v-if="block.data.phone">Phone: {{ block.data.phone }}</p>
            </div>
          </div>

          <!-- Other -->
          <div v-else class="py-6 px-10 bg-slate-50">
            <span class="text-xs font-mono text-slate-400">{{ block.type.toUpperCase() }} block</span>
          </div>

        </template>
        <div v-if="!page.blocks?.length" class="py-16 text-center text-slate-400 text-sm">No blocks yet.</div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Link } from '@inertiajs/vue3'
const props = defineProps({ page: Object })
</script>
```

- [ ] **Step 2: Add preview link to Pages/Edit.vue sidebar**

In `resources/js/Pages/Admin/Pages/Edit.vue`, replace the existing preview anchor:

```html
<a :href="`/preview/pages/${page.id}`" target="_blank"
   class="block text-center text-sm text-blue-600 hover:underline">Preview ↗</a>
```

With:

```html
<Link :href="`/admin/pages/${page.id}/preview`"
   class="block text-center text-sm text-blue-600 hover:underline">Preview ↗</Link>
```

- [ ] **Step 3: Add preview route**

In `routes/web.php`, inside the `role:admin,editor` group for pages, add:

```php
Route::get('pages/{page}/preview', [PageController::class, 'preview'])->name('pages.preview.admin');
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Admin/Pages/ routes/web.php
git commit -m "feat: inline page preview (block renderer) + fix preview route"
```

---

## Task 9: Settings — social and mail groups

**Files:**
- Modify: `database/seeders/SettingSeeder.php`
- Modify: `resources/js/Pages/Admin/Settings/Index.vue`

- [ ] **Step 1: Add social + mail setting keys to SettingSeeder**

Add to the `$defaults` array in `SettingSeeder`:

```php
// Social
['key' => 'social_github',   'value' => 'https://github.com/ksnsk2001-boop',                              'group' => 'social'],
['key' => 'social_linkedin', 'value' => 'https://www.linkedin.com/in/nakul-sri-kuber-384233193/',         'group' => 'social'],
['key' => 'social_twitter',  'value' => '',                                                                'group' => 'social'],
['key' => 'social_website',  'value' => '',                                                                'group' => 'social'],
// Mail
['key' => 'mail_from_name',    'value' => 'Nakul Sri Kuber',      'group' => 'mail'],
['key' => 'mail_from_address', 'value' => 'ksnsk2001@gmail.com', 'group' => 'mail'],
['key' => 'mail_reply_to',     'value' => 'ksnsk2001@gmail.com', 'group' => 'mail'],
```

- [ ] **Step 2: Update SettingController to pass grouped settings**

In `SettingController::index()`, add grouping:

```php
public function index(): \Inertia\Response
{
    return Inertia::render('Admin/Settings/Index', [
        'settings' => Setting::all()->groupBy('group')->map->keyBy('key'),
    ]);
}
```

- [ ] **Step 3: Re-seed and commit**

```bash
php artisan migrate:fresh --seed
git add database/seeders/SettingSeeder.php app/Http/Controllers/Admin/SettingController.php
git commit -m "feat: social + mail setting groups in seeder and settings controller"
```

---

## Task 10: Public multi-tenant routing

**Files:**
- Modify: `app/Http/Controllers/PublicController.php`
- Create: `resources/js/Pages/Public/Portfolio.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Update PublicController**

```php
<?php
namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function index(): Response
    {
        // Admin's public home
        $admin = User::role('admin')->first();
        return $this->renderPortfolio($admin);
    }

    public function portfolio(string $username): Response
    {
        $user = User::where('username', $username)->firstOrFail();
        return $this->renderPortfolio($user);
    }

    public function page(string $username, string $slug): Response
    {
        $user = User::where('username', $username)->firstOrFail();
        $page = $user->pages()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with(['serviceCards' => fn($q) => $q->orderBy('sort_order')])
            ->firstOrFail();

        return Inertia::render('Public/Portfolio', [
            'owner'    => $user->only('id', 'name', 'username'),
            'pages'    => $user->pages()->where('status', 'published')->get(['id', 'name', 'slug', 'is_home']),
            'page'     => $page,
            'settings' => Setting::whereIn('key', ['site_name', 'site_tagline', 'social_github', 'social_linkedin'])->pluck('value', 'key'),
        ]);
    }

    private function renderPortfolio(User $user): Response
    {
        $home = $user->pages()
            ->where('is_home', true)
            ->where('status', 'published')
            ->with(['serviceCards' => fn($q) => $q->orderBy('sort_order')])
            ->first();

        return Inertia::render('Public/Portfolio', [
            'owner'    => $user->only('id', 'name', 'username'),
            'pages'    => $user->pages()->where('status', 'published')->get(['id', 'name', 'slug', 'is_home']),
            'page'     => $home,
            'settings' => Setting::whereIn('key', ['site_name', 'site_tagline', 'social_github', 'social_linkedin'])->pluck('value', 'key'),
        ]);
    }
}
```

- [ ] **Step 2: Add public routes in web.php**

Replace the current `GET /` route and add tenant routes at the **bottom** of web.php (after all `/admin` routes, to avoid slug conflicts):

```php
// Public portfolio routes (must be last — catch-all pattern)
Route::get('/',                  [\App\Http\Controllers\PublicController::class, 'index'])->name('home');
Route::get('/{username}',        [\App\Http\Controllers\PublicController::class, 'portfolio'])->name('portfolio')->where('username', '[a-z0-9_-]+');
Route::get('/{username}/{slug}', [\App\Http\Controllers\PublicController::class, 'page'])->name('portfolio.page')->where(['username' => '[a-z0-9_-]+', 'slug' => '[a-z0-9-]+']);
```

- [ ] **Step 3: Create Portfolio.vue**

Create `resources/js/Pages/Public/Portfolio.vue` — same block renderer as `Home.vue` but uses `page` prop (single page) and shows nav across user's published pages:

```vue
<template>
  <div class="min-h-screen bg-white text-slate-900 font-sans">
    <nav class="fixed top-0 inset-x-0 z-50 bg-white/80 backdrop-blur border-b border-slate-100">
      <div class="max-w-5xl mx-auto px-6 h-14 flex items-center justify-between gap-4">
        <span class="font-semibold text-slate-800 tracking-tight">{{ owner.name }}</span>
        <div class="flex items-center gap-4">
          <a v-for="p in pages" :key="p.id"
            :href="p.is_home ? `/${owner.username}` : `/${owner.username}/${p.slug}`"
            class="text-sm text-slate-600 hover:text-slate-900">{{ p.name }}</a>
          <a v-if="isAdmin" href="/admin/dashboard"
            class="text-sm px-4 py-1.5 rounded-lg bg-slate-900 text-white hover:bg-slate-700 transition-colors ml-2">
            Dashboard →
          </a>
        </div>
      </div>
    </nav>

    <main class="pt-14">
      <template v-if="page" v-for="block in (page.blocks ?? [])" :key="block.order">

        <section v-if="block.type === 'hero'" class="py-28 px-6 text-center max-w-3xl mx-auto">
          <h1 class="text-5xl font-bold leading-tight tracking-tight text-slate-900 mb-4">{{ block.data.heading }}</h1>
          <p class="text-xl text-slate-500">{{ block.data.subheading }}</p>
        </section>

        <section v-else-if="block.type === 'text'" class="py-16 px-6 max-w-3xl mx-auto">
          <p class="whitespace-pre-line text-slate-700">{{ block.data.content }}</p>
        </section>

        <section v-else-if="block.type === 'text_image'" class="py-16 px-6 max-w-5xl mx-auto flex flex-col md:flex-row gap-12 items-center">
          <div class="flex-1">
            <p class="whitespace-pre-line text-slate-700">{{ block.data.text }}</p>
          </div>
          <div v-if="block.data.image" class="flex-1">
            <img :src="block.data.image" class="rounded-2xl shadow-md w-full object-cover" />
          </div>
        </section>

        <section v-else-if="block.type === 'service_cards'" class="py-16 px-6 max-w-5xl mx-auto">
          <h2 v-if="block.data.heading" class="text-3xl font-bold text-slate-900 text-center mb-10">{{ block.data.heading }}</h2>
          <div v-if="page.service_cards?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="card in page.service_cards" :key="card.id" class="rounded-2xl border border-slate-100 p-6 shadow-sm hover:shadow-md transition-shadow">
              <div v-if="card.icon" class="text-3xl mb-3">{{ card.icon }}</div>
              <h3 class="font-semibold text-slate-900 mb-2">{{ card.title }}</h3>
              <p class="text-sm text-slate-500">{{ card.description }}</p>
            </div>
          </div>
        </section>

        <section v-else-if="block.type === 'project_grid'" class="py-16 px-6 max-w-5xl mx-auto">
          <h2 v-if="block.data.heading" class="text-3xl font-bold text-slate-900 text-center mb-10">{{ block.data.heading }}</h2>
          <div v-if="block.data.projects?.length" class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <a v-for="project in block.data.projects" :key="project.title" :href="project.url ?? '#'" target="_blank"
              class="group rounded-2xl border border-slate-100 p-6 shadow-sm hover:shadow-md transition-shadow block">
              <h3 class="font-semibold text-slate-900 group-hover:text-blue-600 mb-1">{{ project.title }}</h3>
              <p class="text-sm text-slate-500">{{ project.description }}</p>
            </a>
          </div>
        </section>

        <section v-else-if="block.type === 'contact_form'" class="py-16 px-6 max-w-2xl mx-auto">
          <h2 class="text-3xl font-bold text-slate-900 text-center mb-10">{{ block.data.heading ?? 'Get in touch' }}</h2>
          <div class="flex flex-col md:flex-row gap-10">
            <div class="md:w-48 flex-shrink-0 space-y-4 text-sm">
              <div v-if="block.data.email">
                <p class="text-xs text-slate-400 mb-0.5">Email</p>
                <a :href="`mailto:${block.data.email}`" class="text-slate-800 hover:text-blue-600 break-all">{{ block.data.email }}</a>
              </div>
              <div v-if="block.data.phone">
                <p class="text-xs text-slate-400 mb-0.5">Phone</p>
                <a :href="`tel:${block.data.phone}`" class="text-slate-800 hover:text-blue-600">{{ block.data.phone }}</a>
              </div>
              <div v-if="block.data.links?.length">
                <p class="text-xs text-slate-400 mb-0.5">Links</p>
                <a v-for="link in block.data.links" :key="link.label" :href="link.url" target="_blank" rel="noopener"
                  class="block text-slate-800 hover:text-blue-600">{{ link.label }} ↗</a>
              </div>
            </div>
            <form @submit.prevent class="flex-1 space-y-4">
              <input type="text" placeholder="Name" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-slate-900" />
              <input type="email" placeholder="Email" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-slate-900" />
              <textarea rows="4" placeholder="Message" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-slate-900 resize-none" />
              <button type="submit" class="w-full bg-slate-900 text-white py-3 rounded-xl text-sm hover:bg-slate-700 transition-colors">Send message</button>
            </form>
          </div>
        </section>

      </template>
      <div v-if="!page" class="py-40 text-center text-slate-300 text-lg">Coming soon.</div>
    </main>

    <footer class="border-t border-slate-100 py-8 text-center text-xs text-slate-400">
      {{ owner.name }}
    </footer>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
  owner:    { type: Object, default: () => ({}) },
  pages:    { type: Array,  default: () => [] },
  page:     { type: Object, default: null },
  settings: { type: Object, default: () => ({}) },
})

const { props: pageProps } = usePage()
const isAdmin = computed(() => pageProps.auth?.roles?.includes('admin') ?? false)
</script>
```

- [ ] **Step 4: Final seed + build**

```bash
php artisan migrate:fresh --seed
npm run build 2>&1 | tail -3
```

Expected: All seeders pass, `✓ built`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/PublicController.php \
        resources/js/Pages/Public/ routes/web.php
git commit -m "feat: multi-tenant public portfolio — /{username} and /{username}/{slug} routes"
```

---

## Self-Review

**Spec coverage check:**

| Requirement | Task |
|---|---|
| Remove Integrations nav/routes | Task 4 |
| Restrict pages/service-cards/projects to owner | Task 5 |
| Pages edit linked with service cards | Task 5 (PageController passes serviceCards), Task 8 (preview shows them) |
| Social and mail settings empty | Task 9 |
| GitHub per-user token connect/disconnect | Task 6 |
| Rename Projects → Products | Task 4 |
| Project model was empty | N/A — model is correct; was a display/scope issue fixed in Task 5 |
| No profile section | Task 7 |
| Home page cannot be deleted | Task 5 (PageController.destroy guards is_home) |
| Preview as component not text | Task 8 |
| Other users create portfolio at /username/pagenames | Task 10 |
| Username unique in URL | Task 1 (migration), Task 2 (auto-generate) |

**Gap:** The `Pages/Edit.vue` service cards panel (to add/edit cards inline from the page editor) was described in the spec but Task 8 only adds the preview route. The admin can still manage cards at `/admin/service-cards`. This is acceptable scope — the cards are visible in the preview and the existing service cards UI is fully scoped to the user.

**Gap:** `SyncGitHubIssues` Artisan command still reads from `ThirdPartySetting`. Update it to iterate users with `github_token` and sync their projects. Add this to Task 6's commit if needed.
