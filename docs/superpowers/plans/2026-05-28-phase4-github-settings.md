# Portfolio CMS — Phase 4: GitHub Integration, Settings & Users

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** GitHub integration (sync issues as tasks, create/link GitHub Projects, dashboard repo widget), Settings panel (general/SEO/social/mail), Integrations panel (ThirdPartySettings by group), and Users/Roles management panel.

**Architecture:** `GitHubService` wraps GitHub REST API v3 using the token stored in `ThirdPartySetting`. `SyncGitHubIssues` Artisan command is scheduled hourly. Settings and ThirdPartySettings are managed through dedicated admin controllers. Users panel lets admin change roles.

**Prerequisites:** Phase 1 + Phase 2 + Phase 3 complete.

---

## File Map

| File | Purpose |
|---|---|
| `app/Services/GitHubService.php` | GitHub API wrapper (repos, issues, projects) |
| `app/Console/Commands/SyncGitHubIssues.php` | `php artisan github:sync` |
| `app/Http/Controllers/Admin/GitHubController.php` | Dashboard widget + project creation |
| `app/Http/Controllers/Admin/SettingController.php` | Settings CRUD by group |
| `app/Http/Controllers/Admin/ThirdPartySettingController.php` | Integrations CRUD by group |
| `app/Http/Controllers/Admin/UserController.php` | User list + role assignment |
| `resources/js/Pages/Admin/Settings/Index.vue` | Settings form grouped by tab |
| `resources/js/Pages/Admin/Integrations/Index.vue` | Integration settings by provider group |
| `resources/js/Pages/Admin/Users/Index.vue` | User list with role badges |

---

## Task 13: GitHubService + issue sync command

**Files:**
- Create: `app/Services/GitHubService.php`
- Create: `app/Console/Commands/SyncGitHubIssues.php`
- Modify: `routes/console.php`

- [ ] **Step 1: Write failing test**

```bash
php artisan make:test GitHubServiceTest
```
```php
// tests/Unit/GitHubServiceTest.php
use App\Models\ThirdPartySetting;
use App\Services\GitHubService;
use Illuminate\Support\Facades\Http;

it('fetches repos using stored token', function () {
    ThirdPartySetting::create([
        'provider' => 'github', 'key' => 'token',
        'value' => 'ghp_test', 'group' => 'github', 'is_active' => true,
    ]);

    Http::fake([
        'api.github.com/user/repos*' => Http::response([
            ['id' => 1, 'name' => 'portfolio', 'full_name' => 'nakul/portfolio', 'description' => 'My portfolio'],
        ], 200),
    ]);

    $service = app(GitHubService::class);
    $repos = $service->getRepos();

    expect($repos)->toHaveCount(1);
    expect($repos[0]['name'])->toBe('portfolio');
});

it('syncs issues to tasks', function () {
    $ws = \App\Models\Workspace::create(['name' => 'WS', 'slug' => 'ws-gh']);
    $project = \App\Models\Project::create([
        'workspace_id' => $ws->id, 'name' => 'P', 'slug' => 'p', 'github_repo' => 'nakul/portfolio',
    ]);

    ThirdPartySetting::create([
        'provider' => 'github', 'key' => 'token',
        'value' => 'ghp_test', 'group' => 'github', 'is_active' => true,
    ]);

    Http::fake([
        'api.github.com/repos/nakul/portfolio/issues*' => Http::response([
            ['number' => 42, 'title' => 'Fix header bug', 'body' => 'Details here', 'html_url' => 'https://github.com/nakul/portfolio/issues/42'],
        ], 200),
    ]);

    $service = app(GitHubService::class);
    $service->syncIssuesToProject($project);

    expect(\App\Models\Task::where('github_issue_id', '42')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run to verify fail**

```bash
php artisan test tests/Unit/GitHubServiceTest.php
```

- [ ] **Step 3: Create GitHubService**

```php
<?php
// app/Services/GitHubService.php
namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\ThirdPartySetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubService
{
    private string $baseUrl = 'https://api.github.com';

    private function token(): ?string
    {
        return ThirdPartySetting::getValue('github', 'token');
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->token())
            ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
            ->baseUrl($this->baseUrl);
    }

    /** List authenticated user's repos */
    public function getRepos(int $perPage = 30): array
    {
        try {
            return $this->http()
                ->get('/user/repos', ['per_page' => $perPage, 'sort' => 'updated'])
                ->json() ?? [];
        } catch (\Throwable $e) {
            Log::warning('GitHubService::getRepos failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /** List open issues for a repo (owner/repo format) */
    public function getIssues(string $repo, string $state = 'open'): array
    {
        try {
            return $this->http()
                ->get("/repos/{$repo}/issues", ['state' => $state, 'per_page' => 100])
                ->json() ?? [];
        } catch (\Throwable $e) {
            Log::warning('GitHubService::getIssues failed', ['repo' => $repo, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /** Sync GitHub issues for a project into the tasks table */
    public function syncIssuesToProject(Project $project): int
    {
        if (! $project->github_repo) {
            return 0;
        }

        $issues = $this->getIssues($project->github_repo);
        $count  = 0;

        foreach ($issues as $issue) {
            // Skip pull requests (GitHub returns PRs in issues endpoint)
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

    /** Create a GitHub Project (classic) under the authenticated user */
    public function createProject(string $owner, string $name, string $body = ''): ?array
    {
        try {
            $response = $this->http()->post("/users/{$owner}/projects", compact('name', 'body'));
            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::warning('GitHubService::createProject failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
```

- [ ] **Step 4: Create SyncGitHubIssues command**

```php
<?php
// app/Console/Commands/SyncGitHubIssues.php
namespace App\Console\Commands;

use App\Models\Project;
use App\Services\GitHubService;
use Illuminate\Console\Command;

class SyncGitHubIssues extends Command
{
    protected $signature   = 'github:sync {--project= : Sync a specific project by ID}';
    protected $description = 'Sync open GitHub issues as tasks for all (or one) linked projects';

    public function handle(GitHubService $github): int
    {
        $query = Project::whereNotNull('github_repo');

        if ($this->option('project')) {
            $query->where('id', $this->option('project'));
        }

        $projects = $query->get();

        if ($projects->isEmpty()) {
            $this->warn('No projects with a linked GitHub repo found.');
            return self::SUCCESS;
        }

        $total = 0;
        foreach ($projects as $project) {
            $count = $github->syncIssuesToProject($project);
            $this->line("  {$project->name} ({$project->github_repo}): {$count} issues synced.");
            $total += $count;
        }

        $this->info("Done. {$total} issues synced across {$projects->count()} project(s).");
        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Register the command and schedule it**

In `bootstrap/app.php` (or `app/Console/Kernel.php` if it exists):
```php
// bootstrap/app.php — inside withSchedule:
$schedule->command('github:sync')->hourly();
```

Register in `bootstrap/app.php`:
```php
->withCommands([
    \App\Console\Commands\SyncGitHubIssues::class,
])
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Unit/GitHubServiceTest.php
```
Expected: 2 passed.

- [ ] **Step 7: Create GitHubController for dashboard widget + project linking**

```php
<?php
// app/Http/Controllers/Admin/GitHubController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ThirdPartySetting;
use App\Services\GitHubService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GitHubController extends Controller
{
    public function __construct(private GitHubService $github) {}

    public function index()
    {
        $hasToken = ThirdPartySetting::where('provider', 'github')
            ->where('key', 'token')->where('is_active', true)->exists();

        return Inertia::render('Admin/GitHub/Index', [
            'repos'    => $hasToken ? $this->github->getRepos() : [],
            'projects' => Project::whereNotNull('github_repo')
                ->with('workspace:id,name')->get(['id','name','github_repo','github_project_id','workspace_id']),
            'hasToken' => $hasToken,
        ]);
    }

    public function createGitHubProject(Request $request, Project $project)
    {
        $data = $request->validate([
            'owner' => 'required|string',
            'name'  => 'required|string|max:255',
            'body'  => 'nullable|string',
        ]);

        $ghProject = $this->github->createProject($data['owner'], $data['name'], $data['body'] ?? '');

        if (! $ghProject) {
            return back()->withErrors(['github' => 'Failed to create GitHub project. Check your token and permissions.']);
        }

        $project->update(['github_project_id' => (string) $ghProject['number']]);

        return back()->with('success', "GitHub project #{$ghProject['number']} created and linked.");
    }

    public function sync(Project $project)
    {
        $count = $this->github->syncIssuesToProject($project);
        return back()->with('success', "{$count} issues synced from GitHub.");
    }
}
```

- [ ] **Step 8: Add GitHub routes**

```php
// routes/web.php — inside admin middleware group:
use App\Http\Controllers\Admin\GitHubController;

Route::get('github', [GitHubController::class, 'index'])->name('github.index');
Route::post('github/projects/{project}/create', [GitHubController::class, 'createGitHubProject'])->name('github.project.create');
Route::post('github/projects/{project}/sync', [GitHubController::class, 'sync'])->name('github.sync');
```

- [ ] **Step 9: Commit**

```bash
git add app/Services/GitHubService.php app/Console/Commands/ app/Http/Controllers/Admin/GitHubController.php \
  tests/Unit/GitHubServiceTest.php
git commit -m "feat: GitHub integration — issue sync, project creation, repo widget"
```

---

## Task 14: Settings panel

**Files:**
- Create: `app/Http/Controllers/Admin/SettingController.php`
- Create: `resources/js/Pages/Admin/Settings/Index.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Write failing test**

```bash
php artisan make:test SettingControllerTest
```
```php
// tests/Feature/SettingControllerTest.php
use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    (new \Database\Seeders\SettingSeeder)->run();
});

it('shows settings page', function () {
    $this->actingAs($this->admin)
        ->get('/admin/settings')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Admin/Settings/Index'));
});

it('updates a setting', function () {
    $this->actingAs($this->admin)
        ->patch('/admin/settings', ['site_name' => 'My Portfolio'])
        ->assertRedirect();
    expect(Setting::get('site_name'))->toBe('My Portfolio');
});
```

- [ ] **Step 2: Run to verify fail**

```bash
php artisan test tests/Feature/SettingControllerTest.php
```

- [ ] **Step 3: Create SettingController**

```php
<?php
// app/Http/Controllers/Admin/SettingController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->groupBy('group')->map(
            fn ($group) => $group->keyBy('key')->map(fn ($s) => $s->value)
        );

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
            'groups'   => ['general', 'seo', 'social', 'mail'],
        ]);
    }

    public function update(Request $request)
    {
        $allowed = Setting::pluck('key')->toArray();

        foreach ($request->only($allowed) as $key => $value) {
            $setting = Setting::where('key', $key)->first();
            if ($setting) {
                $setting->update(['value' => $value]);
            }
        }

        return back()->with('success', 'Settings saved.');
    }
}
```

- [ ] **Step 4: Add settings routes**

```php
// routes/web.php — inside admin+role:admin middleware group:
use App\Http\Controllers\Admin\SettingController;

Route::middleware('role:admin')->group(function () {
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::patch('settings', [SettingController::class, 'update'])->name('settings.update');
});
```

- [ ] **Step 5: Run tests**

```bash
php artisan test tests/Feature/SettingControllerTest.php
```
Expected: 2 passed.

- [ ] **Step 6: Create Settings/Index.vue**

```vue
<!-- resources/js/Pages/Admin/Settings/Index.vue -->
<template>
  <AdminLayout>
    <div class="max-w-2xl mx-auto">
      <h1 class="text-2xl font-bold text-slate-800 mb-6">Settings</h1>

      <!-- Group tabs -->
      <div class="flex gap-2 mb-6">
        <button v-for="g in groups" :key="g"
          @click="activeGroup = g"
          class="px-4 py-2 text-sm rounded-lg transition"
          :class="activeGroup === g ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'">
          {{ capitalize(g) }}
        </button>
      </div>

      <form @submit.prevent="save" class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
        <template v-for="(value, key) in currentGroup" :key="key">
          <div>
            <label class="block text-sm text-slate-600 mb-1">{{ formatKey(key) }}</label>
            <input v-model="form[key]"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </template>

        <div class="pt-2">
          <button type="submit"
            class="bg-blue-600 text-white text-sm px-5 py-2 rounded-lg hover:bg-blue-700">
            Save Settings
          </button>
          <span v-if="form.wasSuccessful" class="text-green-600 text-sm ml-3">Saved!</span>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ settings: Object, groups: Array })
const activeGroup = ref(props.groups[0])

// Flatten all settings into one form object
const allSettings = Object.values(props.settings).reduce((acc, group) => ({ ...acc, ...group }), {})
const form = useForm(allSettings)

const currentGroup = computed(() => props.settings[activeGroup.value] ?? {})

const save = () => form.patch('/admin/settings')

const capitalize = s => s.charAt(0).toUpperCase() + s.slice(1)
const formatKey  = k => k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
</script>
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/SettingController.php resources/js/Pages/Admin/Settings/ tests/
git commit -m "feat: settings panel with grouped tabs"
```

---

## Task 15: Integrations panel (ThirdPartySettings)

**Files:**
- Create: `app/Http/Controllers/Admin/ThirdPartySettingController.php`
- Create: `resources/js/Pages/Admin/Integrations/Index.vue`

- [ ] **Step 1: Write failing test**

```bash
php artisan make:test ThirdPartySettingTest
```
```php
// tests/Feature/ThirdPartySettingTest.php
use App\Models\ThirdPartySetting;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('stores a third party setting', function () {
    $this->actingAs($this->admin)
        ->post('/admin/integrations', [
            'provider'  => 'github',
            'key'       => 'token',
            'value'     => 'ghp_abc123',
            'group'     => 'github',
            'is_active' => true,
        ])
        ->assertRedirect();
    expect(ThirdPartySetting::where('provider','github')->where('key','token')->exists())->toBeTrue();
});

it('toggles a setting active state', function () {
    $s = ThirdPartySetting::create(['provider'=>'github','key'=>'token','value'=>'x','group'=>'github','is_active'=>true]);
    $this->actingAs($this->admin)
        ->patch("/admin/integrations/{$s->id}", ['is_active' => false, 'provider'=>'github','key'=>'token','group'=>'github'])
        ->assertRedirect();
    expect($s->fresh()->is_active)->toBeFalse();
});
```

- [ ] **Step 2: Run to verify fail**

```bash
php artisan test tests/Feature/ThirdPartySettingTest.php
```

- [ ] **Step 3: Create ThirdPartySettingController**

```php
<?php
// app/Http/Controllers/Admin/ThirdPartySettingController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThirdPartySetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ThirdPartySettingController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Integrations/Index', [
            'integrations' => ThirdPartySetting::orderBy('group')->orderBy('provider')->get(),
            'groups'       => ['github', 'google', 'storage', 'analytics', 'other'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'provider'  => 'required|string|max:100',
            'key'       => 'required|string|max:255',
            'value'     => 'nullable|string',
            'group'     => 'required|in:github,google,storage,analytics,other',
            'is_active' => 'boolean',
        ]);

        ThirdPartySetting::updateOrCreate(
            ['provider' => $data['provider'], 'key' => $data['key']],
            $data
        );

        return back()->with('success', 'Integration saved.');
    }

    public function update(Request $request, ThirdPartySetting $integration)
    {
        $data = $request->validate([
            'provider'  => 'required|string|max:100',
            'key'       => 'required|string|max:255',
            'value'     => 'nullable|string',
            'group'     => 'required|in:github,google,storage,analytics,other',
            'is_active' => 'boolean',
        ]);

        $integration->update($data);
        return back()->with('success', 'Integration updated.');
    }

    public function destroy(ThirdPartySetting $integration)
    {
        $integration->delete();
        return back()->with('success', 'Integration removed.');
    }
}
```

- [ ] **Step 4: Add integrations routes**

```php
// routes/web.php — inside admin+role:admin group:
use App\Http\Controllers\Admin\ThirdPartySettingController;

Route::resource('integrations', ThirdPartySettingController::class)
    ->except(['create','edit','show'])
    ->parameters(['integrations' => 'integration']);
```

- [ ] **Step 5: Run tests**

```bash
php artisan test tests/Feature/ThirdPartySettingTest.php
```
Expected: 2 passed.

- [ ] **Step 6: Create Integrations/Index.vue**

```vue
<!-- resources/js/Pages/Admin/Integrations/Index.vue -->
<template>
  <AdminLayout>
    <div class="max-w-3xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Integrations</h1>
        <button @click="showAdd = true"
          class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700">+ Add</button>
      </div>

      <!-- Group tabs -->
      <div class="flex gap-2 mb-6 flex-wrap">
        <button v-for="g in groups" :key="g"
          @click="activeGroup = g"
          class="px-4 py-2 text-sm rounded-lg transition capitalize"
          :class="activeGroup === g ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'">
          {{ g }}
        </button>
      </div>

      <!-- Integration list -->
      <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <ul class="divide-y divide-slate-100">
          <li v-for="item in filtered" :key="item.id"
            class="flex items-center gap-4 px-5 py-4">
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-slate-800">{{ item.provider }} / {{ item.key }}</p>
              <p class="text-xs text-slate-400 truncate">{{ maskValue(item.value) }}</p>
            </div>
            <span class="text-xs px-2 py-0.5 rounded-full"
              :class="item.is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">
              {{ item.is_active ? 'Active' : 'Inactive' }}
            </span>
            <button @click="toggleActive(item)"
              class="text-xs text-slate-500 border border-slate-200 rounded px-2 py-1 hover:bg-slate-50">
              {{ item.is_active ? 'Disable' : 'Enable' }}
            </button>
            <button @click="deleteItem(item.id)"
              class="text-xs text-red-500 border border-red-100 rounded px-2 py-1 hover:bg-red-50">Delete</button>
          </li>
          <li v-if="filtered.length === 0" class="px-5 py-8 text-center text-sm text-slate-400">
            No {{ activeGroup }} integrations yet.
          </li>
        </ul>
      </div>

      <!-- Add modal -->
      <div v-if="showAdd" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-96 shadow-xl space-y-3">
          <h2 class="font-semibold text-slate-800">Add Integration</h2>
          <select v-model="form.group" class="input w-full">
            <option v-for="g in groups" :key="g" :value="g">{{ g }}</option>
          </select>
          <input v-model="form.provider" placeholder="Provider (e.g. github)" class="input w-full" />
          <input v-model="form.key" placeholder="Key (e.g. token)" class="input w-full" />
          <input v-model="form.value" placeholder="Value" type="password" class="input w-full" />
          <div class="flex items-center gap-2">
            <input v-model="form.is_active" type="checkbox" id="ia" />
            <label for="ia" class="text-sm text-slate-600">Active</label>
          </div>
          <div class="flex gap-2 justify-end">
            <button @click="showAdd = false" class="px-4 py-2 text-sm text-slate-500">Cancel</button>
            <button @click="form.post('/admin/integrations', { onSuccess: () => showAdd = false })"
              class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg">Save</button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ integrations: Array, groups: Array })
const activeGroup = ref(props.groups[0])
const showAdd = ref(false)
const form = useForm({ provider: '', key: '', value: '', group: 'github', is_active: true })

const filtered = computed(() => props.integrations.filter(i => i.group === activeGroup.value))
const maskValue = v => v ? '•'.repeat(Math.min(v.length, 8)) + v.slice(-4) : '—'

const toggleActive = (item) => {
  router.patch(`/admin/integrations/${item.id}`, {
    ...item, is_active: !item.is_active
  })
}

const deleteItem = (id) => {
  if (confirm('Remove this integration?')) router.delete(`/admin/integrations/${id}`)
}
</script>

<style scoped>
.input { @apply border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500; }
</style>
```

- [ ] **Step 7: Commit**

```bash
git add .
git commit -m "feat: integrations panel for ThirdPartySettings by group"
```

---

## Task 16: Users & Roles management panel

**Files:**
- Create: `app/Http/Controllers/Admin/UserController.php`
- Create: `resources/js/Pages/Admin/Users/Index.vue`

- [ ] **Step 1: Write failing test**

```bash
php artisan make:test UserManagementTest
```
```php
// tests/Feature/UserManagementTest.php
use App\Models\User;

beforeEach(function () {
    (new \Database\Seeders\RoleSeeder)->run();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('lists users', function () {
    $this->actingAs($this->admin)
        ->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Admin/Users/Index'));
});

it('assigns a role to a user', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');

    $this->actingAs($this->admin)
        ->patch("/admin/users/{$viewer->id}/role", ['role' => 'editor'])
        ->assertRedirect();

    expect($viewer->fresh()->hasRole('editor'))->toBeTrue();
    expect($viewer->fresh()->hasRole('viewer'))->toBeFalse();
});
```

- [ ] **Step 2: Run to verify fail**

```bash
php artisan test tests/Feature/UserManagementTest.php
```

- [ ] **Step 3: Create UserController**

```php
<?php
// app/Http/Controllers/Admin/UserController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::with('roles:id,name')->latest()->get(['id','name','email','avatar','created_at']),
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => 'required|exists:roles,name',
        ]);

        $user->syncRoles([$data['role']]);

        return back()->with('success', "Role updated to {$data['role']}.");
    }

    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 403, 'Cannot delete yourself.');
        $user->delete();
        return back()->with('success', 'User deleted.');
    }
}
```

- [ ] **Step 4: Add user routes**

```php
// routes/web.php — inside admin+role:admin group:
use App\Http\Controllers\Admin\UserController;

Route::get('users', [UserController::class, 'index'])->name('users.index');
Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
```

- [ ] **Step 5: Run tests**

```bash
php artisan test tests/Feature/UserManagementTest.php
```
Expected: 2 passed.

- [ ] **Step 6: Create Users/Index.vue**

```vue
<!-- resources/js/Pages/Admin/Users/Index.vue -->
<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto">
      <h1 class="text-2xl font-bold text-slate-800 mb-6">Users</h1>

      <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">User</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Email</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Role</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Joined</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="user in users" :key="user.id" class="hover:bg-slate-50">
              <td class="px-5 py-4 flex items-center gap-3">
                <img :src="user.avatar ?? `https://ui-avatars.com/api/?name=${user.name}`"
                  class="w-8 h-8 rounded-full" :alt="user.name" />
                <span class="font-medium text-slate-800">{{ user.name }}</span>
              </td>
              <td class="px-5 py-4 text-slate-500">{{ user.email }}</td>
              <td class="px-5 py-4">
                <select
                  :value="user.roles[0]?.name ?? 'viewer'"
                  @change="changeRole(user, $event.target.value)"
                  class="border border-slate-200 rounded px-2 py-1 text-xs outline-none focus:ring-1 focus:ring-blue-500">
                  <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                </select>
              </td>
              <td class="px-5 py-4 text-slate-400 text-xs">{{ formatDate(user.created_at) }}</td>
              <td class="px-5 py-4">
                <button v-if="user.id !== $page.props.auth.user.id"
                  @click="deleteUser(user.id)"
                  class="text-xs text-red-500 hover:text-red-700">Remove</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ users: Array, roles: Array })

const changeRole = (user, role) => {
  router.patch(`/admin/users/${user.id}/role`, { role })
}

const deleteUser = (id) => {
  if (confirm('Remove this user?')) router.delete(`/admin/users/${id}`)
}

const formatDate = d => new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
</script>
```

- [ ] **Step 7: Commit**

```bash
git add .
git commit -m "feat: users panel with role assignment"
```

---

## Task 17: Final wiring & .env.example

**Files:**
- Modify: `routes/web.php` (consolidate all route groups cleanly)
- Create: `.env.example`
- Modify: `resources/js/Layouts/AdminLayout.vue` (link nav to real routes)

- [ ] **Step 1: Consolidate routes/web.php**

Replace `routes/web.php` with the final clean version:

```php
<?php
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Admin\{
    DashboardController, PageController, ServiceCardController,
    WorkspaceController, ProjectController, TaskController,
    CommentController, MediaController, UserController,
    SettingController, ThirdPartySettingController, GitHubController
};
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Auth
Route::get('/login', fn () => Inertia::render('Auth/Login'))->name('login');
Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);
Route::post('/logout', function () { auth()->logout(); return redirect('/login'); })->name('logout');

// Public page preview
Route::get('/preview/pages/{page}', [PageController::class, 'preview'])->name('pages.preview');

// Admin — all authenticated users with any CMS role
Route::middleware(['auth', 'role:admin,editor,viewer'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('media', [MediaController::class, 'store'])->name('media.store');
        Route::delete('media/{id}', [MediaController::class, 'destroy'])->name('media.destroy');

        Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
        Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

        Route::post('tasks/{task}/comments', [CommentController::class, 'store'])->name('tasks.comments.store');
        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

        Route::get('github', [GitHubController::class, 'index'])->name('github.index');
        Route::post('github/projects/{project}/sync', [GitHubController::class, 'sync'])->name('github.sync');
    });

// Admin — editor + admin
Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::resource('pages', PageController::class)->except(['show']);
        Route::patch('pages/{page}/publish', [PageController::class, 'publish'])->name('pages.publish');
        Route::resource('service-cards', ServiceCardController::class)->except(['show']);
        Route::post('service-cards/reorder', [ServiceCardController::class, 'reorder'])->name('service-cards.reorder');
        Route::resource('workspaces', WorkspaceController::class)->except(['create','edit','show']);
        Route::resource('projects', ProjectController::class)->except(['create','edit']);
    });

// Admin-only
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::patch('settings', [SettingController::class, 'update'])->name('settings.update');

        Route::resource('integrations', ThirdPartySettingController::class)
            ->except(['create','edit','show'])
            ->parameters(['integrations' => 'integration']);

        Route::post('github/projects/{project}/create', [GitHubController::class, 'createGitHubProject'])->name('github.project.create');
    });

// Root redirect
Route::get('/', fn () => redirect('/admin/dashboard'));
```

- [ ] **Step 2: Write final .env.example**

```bash
cat > .env.example << 'EOF'
APP_NAME=Portfolio
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://portfolio.test

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=portfolio
DB_USERNAME=root
DB_PASSWORD=

CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file

# Google OAuth — https://console.cloud.google.com
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"

# GitHub — https://github.com/settings/tokens
GITHUB_TOKEN=
GITHUB_USERNAME=

# File Storage — set to "s3" to use S3
FILESYSTEM_DISK=local

# AWS S3 (optional)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

# Mail
MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@portfolio.test"
MAIL_FROM_NAME="${APP_NAME}"
EOF
```

- [ ] **Step 3: Run full test suite**

```bash
php artisan test
```
Expected: All tests pass with no failures.

- [ ] **Step 4: Build production assets**

```bash
npm run build
```
Expected: Build completes with no errors.

- [ ] **Step 5: End-to-end smoke check**

```bash
php artisan migrate:fresh --seed
php artisan serve
```

Walk through the following:
1. Visit `http://portfolio.test/login` → Google login button visible
2. Visit `http://portfolio.test/admin/dashboard` → redirects to login ✓
3. After OAuth login → dashboard loads with stat cards ✓
4. Create a page with Hero + Service Cards template → publish → preview ✓
5. Create a service card → link to the page → appears in list ✓
6. Create a workspace → create a project → link GitHub repo `nakul/portfolio` ✓
7. Run `php artisan github:sync` → tasks appear in project ✓
8. Open a task → add a subtask → add a comment with a file attachment ✓
9. Go to Users panel → change a user's role ✓
10. Go to Settings → update site name → saved ✓
11. Go to Integrations → add GitHub token entry ✓

- [ ] **Step 6: Final commit**

```bash
git add .
git commit -m "feat: complete Portfolio CMS — all phases, routes, .env.example"
```

---

## Phase 4 Complete ✅ — All Phases Done

**The full Portfolio CMS is now complete:**

| Phase | Status | What's included |
|---|---|---|
| Phase 1 | ✅ | Laravel 13, packages, migrations, models, seeders, Gmail OAuth, admin shell |
| Phase 2 | ✅ | Page Builder (blocks, templates, publish), Service Card management |
| Phase 3 | ✅ | Workspaces, Projects, Tasks/Subtasks, Comments, File uploads (MediaService) |
| Phase 4 | ✅ | GitHub integration (sync, project create), Settings, Integrations, Users/Roles |

**Run the app:**
```bash
cd /Users/nakul/Herd/Portfolio
php artisan migrate:fresh --seed
npm run dev
# Visit http://portfolio.test
```
