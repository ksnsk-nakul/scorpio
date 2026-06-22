# Portfolio CMS — Phase 3: Projects, Tasks & File Uploads

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Workspace and Project management (with GitHub repo linking), full Task/Subtask tracking with priority/status filters, polymorphic Comments with threading, and a MediaService that handles image/video uploads attached to any of the above.

**Architecture:** WorkspaceController → ProjectController → TaskController → CommentController are nested under admin middleware. MediaController is a standalone endpoint (`POST /admin/media`) returning a JSON media record. `MediaService` encapsulates upload logic. Uploads are stored on the configured disk (local by default, S3-swappable).

**Prerequisites:** Phase 1 + Phase 2 complete.

---

## File Map

| File | Purpose |
|---|---|
| `app/Services/MediaService.php` | Handles file validation, storage, and Media record creation |
| `app/Http/Controllers/Admin/MediaController.php` | `POST /admin/media` upload endpoint |
| `app/Http/Controllers/Admin/WorkspaceController.php` | Workspace CRUD |
| `app/Http/Controllers/Admin/ProjectController.php` | Project CRUD + GitHub linking |
| `app/Http/Controllers/Admin/TaskController.php` | Task + subtask CRUD, status/priority filters |
| `app/Http/Controllers/Admin/CommentController.php` | Create/delete comments on tasks |
| `resources/js/Components/Admin/MediaUploader.vue` | File picker with inline preview |
| `resources/js/Pages/Admin/Workspaces/Index.vue` | Workspace list |
| `resources/js/Pages/Admin/Projects/Index.vue` | Project list per workspace |
| `resources/js/Pages/Admin/Projects/Show.vue` | Project detail with tasks + media library |
| `resources/js/Pages/Admin/Tasks/Show.vue` | Task detail with subtasks, comments, attachments |

---

## Task 10: MediaService + MediaController

**Files:**
- Create: `app/Services/MediaService.php`
- Create: `app/Http/Controllers/Admin/MediaController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write failing test**

```bash
php artisan make:test MediaUploadTest
```
```php
// tests/Feature/MediaUploadTest.php
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('uploads an image and returns a media record', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

    $response = $this->actingAs($this->admin)
        ->postJson('/admin/media', ['file' => $file]);

    $response->assertOk()->assertJsonStructure(['id','filename','mime_type','url']);
    Storage::disk('local')->assertExists($response->json('path'));
});

it('rejects files over max size', function () {
    $file = UploadedFile::fake()->create('big.mp4', 60 * 1024); // 60 MB

    $this->actingAs($this->admin)
        ->postJson('/admin/media', ['file' => $file])
        ->assertStatus(422);
});

it('rejects disallowed mime types', function () {
    $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

    $this->actingAs($this->admin)
        ->postJson('/admin/media', ['file' => $file])
        ->assertStatus(422);
});
```

- [ ] **Step 2: Run to verify fail**

```bash
php artisan test tests/Feature/MediaUploadTest.php
```
Expected: FAIL.

- [ ] **Step 3: Create MediaService**

```php
<?php
// app/Services/MediaService.php
namespace App\Services;

use App\Models\Media;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    /** Allowed MIME types */
    private const ALLOWED_MIMES = [
        'image/jpeg','image/png','image/gif','image/webp','image/svg+xml',
        'video/mp4','video/quicktime','video/webm',
    ];

    /**
     * Validate and store an uploaded file, returning a Media record.
     * Does NOT attach to a mediable — caller handles that.
     */
    public function store(UploadedFile $file, User $user): Media
    {
        $maxMb = (int) Setting::get('media_max_size_mb', 50);

        throw_if(
            ! in_array($file->getMimeType(), self::ALLOWED_MIMES),
            \Illuminate\Validation\ValidationException::withMessages(['file' => 'File type not allowed.'])
        );

        throw_if(
            $file->getSize() > $maxMb * 1024 * 1024,
            \Illuminate\Validation\ValidationException::withMessages(['file' => "Max file size is {$maxMb}MB."])
        );

        $disk = config('filesystems.default', 'local');
        $dir  = 'media/' . now()->format('Y/m');
        $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($dir, $name, $disk);

        return Media::create([
            'user_id'   => $user->id,
            'disk'      => $disk,
            'path'      => $path,
            'filename'  => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size'      => $file->getSize(),
        ]);
    }

    /**
     * Attach a list of media IDs to a mediable model.
     */
    public function attach(array $mediaIds, \Illuminate\Database\Eloquent\Model $mediable): void
    {
        Media::whereIn('id', $mediaIds)
            ->whereNull('mediable_type')
            ->each(fn ($m) => $m->update([
                'mediable_type' => get_class($mediable),
                'mediable_id'   => $mediable->id,
            ]));
    }
}
```

- [ ] **Step 4: Create MediaController**

```php
<?php
// app/Http/Controllers/Admin/MediaController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(private MediaService $media) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file']);

        $record = $this->media->store($request->file('file'), $request->user());

        return response()->json([
            'id'        => $record->id,
            'filename'  => $record->filename,
            'mime_type' => $record->mime_type,
            'size'      => $record->size,
            'path'      => $record->path,
            'url'       => $record->url,
            'is_image'  => $record->isImage(),
            'is_video'  => $record->isVideo(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $media = \App\Models\Media::findOrFail($id);
        \Illuminate\Support\Facades\Storage::disk($media->disk)->delete($media->path);
        $media->delete();
        return response()->json(['ok' => true]);
    }
}
```

- [ ] **Step 5: Add media route**

```php
// routes/web.php — inside admin middleware group:
use App\Http\Controllers\Admin\MediaController;

Route::post('media', [MediaController::class, 'store'])->name('media.store');
Route::delete('media/{id}', [MediaController::class, 'destroy'])->name('media.destroy');
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/MediaUploadTest.php
```
Expected: 3 passed.

- [ ] **Step 7: Create MediaUploader Vue component**

```vue
<!-- resources/js/Components/Admin/MediaUploader.vue -->
<template>
  <div>
    <div class="flex flex-wrap gap-3 mb-3">
      <div v-for="item in uploaded" :key="item.id"
        class="relative w-24 h-24 rounded-lg overflow-hidden border border-slate-200 bg-slate-50 group">
        <img v-if="item.is_image" :src="item.url" class="w-full h-full object-cover" :alt="item.filename" />
        <div v-else class="w-full h-full flex items-center justify-center text-xs text-slate-500 text-center p-1">
          🎬 {{ item.filename }}
        </div>
        <button @click="remove(item)"
          class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-5 h-5 text-xs hidden group-hover:flex items-center justify-center">
          ✕
        </button>
      </div>
    </div>

    <label class="flex items-center gap-2 cursor-pointer text-sm text-blue-600 hover:text-blue-800">
      <input type="file" class="hidden" multiple :accept="acceptedTypes" @change="handleFiles" />
      📎 Attach files (images or videos)
    </label>

    <p v-if="error" class="text-red-500 text-xs mt-1">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'

const props = defineProps({ modelValue: { type: Array, default: () => [] } })
const emit = defineEmits(['update:modelValue'])

const uploaded = ref([...props.modelValue])
const error = ref(null)
const acceptedTypes = 'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime'

const handleFiles = async (event) => {
  error.value = null
  const files = Array.from(event.target.files)
  for (const file of files) {
    try {
      const fd = new FormData()
      fd.append('file', file)
      const { data } = await axios.post('/admin/media', fd)
      uploaded.value.push(data)
      emit('update:modelValue', uploaded.value.map(u => u.id))
    } catch (e) {
      error.value = e.response?.data?.errors?.file?.[0] ?? 'Upload failed'
    }
  }
  event.target.value = ''
}

const remove = async (item) => {
  await axios.delete(`/admin/media/${item.id}`)
  uploaded.value = uploaded.value.filter(u => u.id !== item.id)
  emit('update:modelValue', uploaded.value.map(u => u.id))
}
</script>
```

- [ ] **Step 8: Commit**

```bash
git add app/Services/ app/Http/Controllers/Admin/MediaController.php \
  resources/js/Components/Admin/MediaUploader.vue tests/Feature/MediaUploadTest.php
git commit -m "feat: MediaService, MediaController, MediaUploader component"
```

---

## Task 11: Workspace & Project management

**Files:**
- Create: `app/Http/Controllers/Admin/WorkspaceController.php`
- Create: `app/Http/Controllers/Admin/ProjectController.php`
- Create: `resources/js/Pages/Admin/Workspaces/Index.vue`
- Create: `resources/js/Pages/Admin/Projects/Index.vue`
- Create: `resources/js/Pages/Admin/Projects/Show.vue`

- [ ] **Step 1: Write failing test**

```bash
php artisan make:test ProjectManagementTest
```
```php
// tests/Feature/ProjectManagementTest.php
use App\Models\User;
use App\Models\Workspace;
use App\Models\Project;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('creates a workspace', function () {
    $this->actingAs($this->admin)
        ->post('/admin/workspaces', ['name' => 'Open Source'])
        ->assertRedirect();
    expect(Workspace::where('name', 'Open Source')->exists())->toBeTrue();
});

it('creates a project inside a workspace', function () {
    $ws = Workspace::create(['name' => 'Personal', 'slug' => 'personal']);
    $this->actingAs($this->admin)
        ->post('/admin/projects', ['workspace_id' => $ws->id, 'name' => 'Portfolio'])
        ->assertRedirect();
    expect(Project::where('name', 'Portfolio')->exists())->toBeTrue();
});

it('links a github repo to a project', function () {
    $ws = Workspace::create(['name' => 'Test', 'slug' => 'test-ws']);
    $p  = Project::create(['workspace_id' => $ws->id, 'name' => 'TestProject', 'slug' => 'test-proj']);
    $this->actingAs($this->admin)
        ->patch("/admin/projects/{$p->id}", ['github_repo' => 'nakul/portfolio', 'name' => 'TestProject'])
        ->assertRedirect();
    expect($p->fresh()->github_repo)->toBe('nakul/portfolio');
});
```

- [ ] **Step 2: Run to verify fail**

```bash
php artisan test tests/Feature/ProjectManagementTest.php
```

- [ ] **Step 3: Create WorkspaceController**

```php
<?php
// app/Http/Controllers/Admin/WorkspaceController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Workspaces/Index', [
            'workspaces' => Workspace::withCount('projects')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        Workspace::create($data);
        return redirect('/admin/workspaces')->with('success', 'Workspace created.');
    }

    public function update(Request $request, Workspace $workspace)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        $workspace->update($data);
        return redirect('/admin/workspaces')->with('success', 'Workspace updated.');
    }

    public function destroy(Workspace $workspace)
    {
        $workspace->delete();
        return redirect('/admin/workspaces')->with('success', 'Workspace deleted.');
    }
}
```

- [ ] **Step 4: Create ProjectController**

```php
<?php
// app/Http/Controllers/Admin/ProjectController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Workspace;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function __construct(private MediaService $media) {}

    public function index()
    {
        return Inertia::render('Admin/Projects/Index', [
            'workspaces' => Workspace::with('projects:id,workspace_id,name,slug,status,github_repo,cover_image')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'workspace_id' => 'required|exists:workspaces,id',
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string',
            'status'       => 'in:active,archived',
        ]);
        $project = Project::create($data);
        return redirect("/admin/projects/{$project->id}")->with('success', 'Project created.');
    }

    public function show(Project $project)
    {
        return Inertia::render('Admin/Projects/Show', [
            'project' => $project->load('workspace:id,name'),
            'tasks'   => $project->tasks()->with('assignee:id,name,avatar')->limit(20)->get(),
            'media'   => $project->media()->latest()->get()->map(fn ($m) => [
                'id' => $m->id, 'filename' => $m->filename,
                'url' => $m->url, 'is_image' => $m->isImage(), 'is_video' => $m->isVideo(),
            ]),
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'github_repo'       => 'nullable|string|max:255',
            'github_project_id' => 'nullable|string|max:255',
            'status'            => 'in:active,archived',
            'media_ids'         => 'nullable|array',
        ]);

        $mediaIds = $data['media_ids'] ?? [];
        unset($data['media_ids']);

        $project->update($data);

        if ($mediaIds) {
            $this->media->attach($mediaIds, $project);
        }

        return redirect("/admin/projects/{$project->id}")->with('success', 'Project updated.');
    }

    public function destroy(Project $project)
    {
        $project->delete();
        return redirect('/admin/projects')->with('success', 'Project deleted.');
    }
}
```

- [ ] **Step 5: Add workspace + project routes**

```php
// routes/web.php — inside admin middleware group:
use App\Http\Controllers\Admin\WorkspaceController;
use App\Http\Controllers\Admin\ProjectController;

Route::resource('workspaces', WorkspaceController::class)->except(['create','edit','show']);
Route::resource('projects', ProjectController::class)->except(['create','edit']);
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/ProjectManagementTest.php
```
Expected: 3 passed.

- [ ] **Step 7: Create Workspaces/Index.vue**

```vue
<!-- resources/js/Pages/Admin/Workspaces/Index.vue -->
<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-slate-800">Workspaces</h1>
      <button @click="showCreate = true"
        class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700">+ New Workspace</button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div v-for="ws in workspaces" :key="ws.id"
        class="bg-white border border-slate-200 rounded-xl p-5">
        <h2 class="font-semibold text-slate-800">{{ ws.name }}</h2>
        <p class="text-xs text-slate-400 mt-1 mb-3">{{ ws.description }}</p>
        <p class="text-xs text-slate-500">{{ ws.projects_count }} projects</p>
        <div class="flex gap-2 mt-4">
          <Link :href="`/admin/projects?workspace=${ws.id}`"
            class="text-xs text-blue-600 hover:underline">View Projects →</Link>
        </div>
      </div>
    </div>

    <!-- Create modal -->
    <div v-if="showCreate" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl p-6 w-80 shadow-xl">
        <h2 class="font-semibold mb-4 text-slate-800">New Workspace</h2>
        <input v-model="form.name" placeholder="Name"
          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 outline-none" />
        <textarea v-model="form.description" placeholder="Description (optional)"
          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-4 outline-none resize-none min-h-16" />
        <div class="flex gap-2 justify-end">
          <button @click="showCreate = false" class="px-4 py-2 text-sm text-slate-500">Cancel</button>
          <button @click="form.post('/admin/workspaces', { onSuccess: () => showCreate = false })"
            class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg">Create</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ workspaces: Array })
const showCreate = ref(false)
const form = useForm({ name: '', description: '' })
</script>
```

- [ ] **Step 8: Create Projects/Show.vue**

```vue
<!-- resources/js/Pages/Admin/Projects/Show.vue -->
<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs text-slate-400">{{ project.workspace.name }}</p>
          <h1 class="text-2xl font-bold text-slate-800">{{ project.name }}</h1>
          <p v-if="project.github_repo" class="text-xs text-slate-500 mt-1">
            🐙 <a :href="`https://github.com/${project.github_repo}`" target="_blank"
              class="hover:underline">{{ project.github_repo }}</a>
          </p>
        </div>
        <div class="flex gap-2">
          <button @click="showEdit = true"
            class="text-sm border border-slate-200 px-4 py-2 rounded-lg hover:bg-slate-50">Edit</button>
        </div>
      </div>

      <!-- Tasks -->
      <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="font-semibold text-slate-800 text-sm">Tasks</h2>
          <Link :href="`/admin/tasks/create?project=${project.id}`"
            class="text-xs bg-blue-600 text-white rounded px-3 py-1.5 hover:bg-blue-700">+ Task</Link>
        </div>
        <ul class="divide-y divide-slate-100">
          <li v-for="task in tasks" :key="task.id"
            class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
            <span :class="statusBadge(task.status)"
              class="text-xs px-2 py-0.5 rounded-full font-medium">{{ task.status }}</span>
            <Link :href="`/admin/tasks/${task.id}`" class="flex-1 text-sm text-slate-700 hover:underline">
              {{ task.title }}
            </Link>
            <span v-if="task.assignee" class="text-xs text-slate-400">{{ task.assignee.name }}</span>
          </li>
          <li v-if="tasks.length === 0" class="px-5 py-4 text-sm text-slate-400">No tasks yet.</li>
        </ul>
      </div>

      <!-- Media library -->
      <div class="bg-white border border-slate-200 rounded-xl p-5">
        <h2 class="font-semibold text-slate-800 text-sm mb-4">Media Library</h2>
        <MediaUploader v-model="mediaIds" />
        <div class="flex flex-wrap gap-3 mt-4">
          <div v-for="m in media" :key="m.id"
            class="w-24 h-24 rounded-lg overflow-hidden border border-slate-200 bg-slate-50">
            <img v-if="m.is_image" :src="m.url" class="w-full h-full object-cover" />
            <div v-else class="flex items-center justify-center h-full text-xs text-slate-400 text-center p-1">
              🎬 {{ m.filename }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import MediaUploader from '@/Components/Admin/MediaUploader.vue'

const props = defineProps({ project: Object, tasks: Array, media: Array })
const mediaIds = ref([])
const showEdit = ref(false)

const statusBadge = s => ({
  open: 'bg-amber-100 text-amber-700',
  in_progress: 'bg-blue-100 text-blue-700',
  done: 'bg-green-100 text-green-700',
  closed: 'bg-slate-100 text-slate-500',
}[s] ?? 'bg-slate-100 text-slate-500')
</script>
```

- [ ] **Step 9: Commit**

```bash
git add .
git commit -m "feat: workspace and project management with media library"
```

---

## Task 12: Task, Subtask & Comment management

**Files:**
- Create: `app/Http/Controllers/Admin/TaskController.php`
- Create: `app/Http/Controllers/Admin/CommentController.php`
- Create: `resources/js/Pages/Admin/Tasks/Index.vue`
- Create: `resources/js/Pages/Admin/Tasks/Show.vue`

- [ ] **Step 1: Write failing test**

```bash
php artisan make:test TaskManagementTest
```
```php
// tests/Feature/TaskManagementTest.php
use App\Models\User;
use App\Models\Workspace;
use App\Models\Project;
use App\Models\Task;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $ws = Workspace::create(['name' => 'WS', 'slug' => 'ws']);
    $this->project = Project::create(['workspace_id' => $ws->id, 'name' => 'Proj', 'slug' => 'proj']);
});

it('creates a task', function () {
    $this->actingAs($this->admin)
        ->post('/admin/tasks', [
            'project_id' => $this->project->id,
            'title' => 'Fix bug',
            'status' => 'open',
            'priority' => 'high',
        ])
        ->assertRedirect();
    expect(Task::where('title', 'Fix bug')->exists())->toBeTrue();
});

it('creates a subtask under a parent', function () {
    $parent = Task::create(['project_id' => $this->project->id, 'title' => 'Parent', 'status' => 'open', 'priority' => 'medium']);
    $this->actingAs($this->admin)
        ->post('/admin/tasks', [
            'project_id' => $this->project->id,
            'parent_id'  => $parent->id,
            'title'      => 'Subtask',
            'status'     => 'open',
            'priority'   => 'low',
        ])
        ->assertRedirect();
    expect($parent->subtasks()->count())->toBe(1);
});

it('adds a comment to a task', function () {
    $task = Task::create(['project_id' => $this->project->id, 'title' => 'Task', 'status' => 'open', 'priority' => 'low']);
    $this->actingAs($this->admin)
        ->post("/admin/tasks/{$task->id}/comments", ['body' => 'Good progress'])
        ->assertRedirect();
    expect($task->comments()->count())->toBe(1);
});
```

- [ ] **Step 2: Run to verify fail**

```bash
php artisan test tests/Feature/TaskManagementTest.php
```

- [ ] **Step 3: Create TaskController**

```php
<?php
// app/Http/Controllers/Admin/TaskController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaskController extends Controller
{
    public function __construct(private MediaService $media) {}

    public function index(Request $request)
    {
        $query = Task::query()
            ->whereNull('parent_id')
            ->with('project:id,name', 'assignee:id,name,avatar')
            ->latest();

        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('priority')) $query->where('priority', $request->priority);
        if ($request->filled('project'))  $query->where('project_id', $request->project);

        return Inertia::render('Admin/Tasks/Index', [
            'tasks'    => $query->paginate(20)->withQueryString(),
            'projects' => Project::orderBy('name')->get(['id','name']),
            'filters'  => $request->only('status','priority','project'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id'  => 'required|exists:projects,id',
            'parent_id'   => 'nullable|exists:tasks,id',
            'title'       => 'required|string|max:255',
            'body'        => 'nullable|string',
            'status'      => 'required|in:open,in_progress,done,closed',
            'priority'    => 'required|in:low,medium,high',
            'assignee_id' => 'nullable|exists:users,id',
            'due_date'    => 'nullable|date',
            'media_ids'   => 'nullable|array',
        ]);

        $mediaIds = $data['media_ids'] ?? [];
        unset($data['media_ids']);

        $task = Task::create($data);

        if ($mediaIds) {
            $this->media->attach($mediaIds, $task);
        }

        $redirect = $data['parent_id']
            ? "/admin/tasks/{$data['parent_id']}"
            : "/admin/tasks/{$task->id}";

        return redirect($redirect)->with('success', 'Task created.');
    }

    public function show(Task $task)
    {
        return Inertia::render('Admin/Tasks/Show', [
            'task'     => $task->load([
                'project:id,name',
                'assignee:id,name,avatar',
                'parent:id,title',
                'subtasks.assignee:id,name,avatar',
                'subtasks.media',
                'comments.user:id,name,avatar',
                'comments.media',
                'media',
            ]),
            'users'    => User::orderBy('name')->get(['id','name','avatar']),
            'projects' => Project::orderBy('name')->get(['id','name']),
        ]);
    }

    public function update(Request $request, Task $task)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'body'        => 'nullable|string',
            'status'      => 'required|in:open,in_progress,done,closed',
            'priority'    => 'required|in:low,medium,high',
            'assignee_id' => 'nullable|exists:users,id',
            'due_date'    => 'nullable|date',
            'media_ids'   => 'nullable|array',
        ]);

        $mediaIds = $data['media_ids'] ?? [];
        unset($data['media_ids']);

        $task->update($data);

        if ($mediaIds) {
            $this->media->attach($mediaIds, $task);
        }

        return back()->with('success', 'Task updated.');
    }

    public function destroy(Task $task)
    {
        $redirect = $task->parent_id ? "/admin/tasks/{$task->parent_id}" : '/admin/tasks';
        $task->delete();
        return redirect($redirect)->with('success', 'Task deleted.');
    }
}
```

- [ ] **Step 4: Create CommentController**

```php
<?php
// app/Http/Controllers/Admin/CommentController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Task;
use App\Services\MediaService;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function __construct(private MediaService $media) {}

    public function store(Request $request, Task $task)
    {
        $data = $request->validate([
            'body'      => 'required|string',
            'media_ids' => 'nullable|array',
        ]);

        $mediaIds = $data['media_ids'] ?? [];
        unset($data['media_ids']);

        $comment = $task->comments()->create([
            'user_id' => $request->user()->id,
            'body'    => $data['body'],
        ]);

        if ($mediaIds) {
            $this->media->attach($mediaIds, $comment);
        }

        return back()->with('success', 'Comment added.');
    }

    public function destroy(Comment $comment)
    {
        $this->authorize('delete', $comment);
        $comment->delete();
        return back()->with('success', 'Comment deleted.');
    }
}
```

- [ ] **Step 5: Add CommentPolicy**

```bash
php artisan make:policy CommentPolicy --model=Comment
```
```php
// app/Policies/CommentPolicy.php
public function delete(User $user, Comment $comment): bool
{
    return $user->id === $comment->user_id || $user->hasRole('admin');
}
```

Register in `AppServiceProvider::boot()`:
```php
\Illuminate\Support\Facades\Gate::policy(\App\Models\Comment::class, \App\Policies\CommentPolicy::class);
```

- [ ] **Step 6: Add task + comment routes**

```php
// routes/web.php — inside admin middleware group:
use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\Admin\CommentController;

Route::resource('tasks', TaskController::class)->except(['create','edit']);
Route::post('tasks/{task}/comments', [CommentController::class, 'store'])->name('tasks.comments.store');
Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
```

- [ ] **Step 7: Run tests**

```bash
php artisan test tests/Feature/TaskManagementTest.php
```
Expected: 3 passed.

- [ ] **Step 8: Create Tasks/Show.vue**

```vue
<!-- resources/js/Pages/Admin/Tasks/Show.vue -->
<template>
  <AdminLayout>
    <div class="max-w-3xl mx-auto space-y-6">

      <!-- Task header -->
      <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-start justify-between mb-4">
          <div class="flex-1">
            <p v-if="task.parent" class="text-xs text-slate-400 mb-1">
              Subtask of
              <Link :href="`/admin/tasks/${task.parent.id}`" class="hover:underline">{{ task.parent.title }}</Link>
            </p>
            <h1 v-if="!editing" class="text-xl font-bold text-slate-800 cursor-pointer" @click="editing = true">
              {{ form.title }}
            </h1>
            <input v-else v-model="form.title" @blur="editing = false" @keydown.enter="editing = false"
              class="text-xl font-bold text-slate-800 w-full outline-none border-b border-blue-500 pb-1 mb-1" />
          </div>
          <div class="flex gap-2 ml-4">
            <select v-model="form.status" class="text-xs border border-slate-200 rounded px-2 py-1 outline-none">
              <option v-for="s in ['open','in_progress','done','closed']" :key="s" :value="s">{{ s }}</option>
            </select>
            <select v-model="form.priority" class="text-xs border border-slate-200 rounded px-2 py-1 outline-none">
              <option v-for="p in ['low','medium','high']" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
        </div>

        <textarea v-model="form.body" placeholder="Description..."
          class="w-full text-sm text-slate-600 bg-transparent outline-none resize-none min-h-24 mb-4" />

        <!-- Attachments -->
        <div class="mb-4">
          <p class="text-xs font-medium text-slate-500 mb-2">Attachments</p>
          <MediaUploader v-model="form.media_ids" />
        </div>

        <div class="flex gap-2">
          <button @click="form.patch(`/admin/tasks/${task.id}`)"
            class="text-xs bg-blue-600 text-white rounded px-3 py-1.5 hover:bg-blue-700">Save</button>
          <button @click="router.delete(`/admin/tasks/${task.id}`)"
            class="text-xs text-red-500 border border-red-200 rounded px-3 py-1.5 hover:bg-red-50">Delete Task</button>
        </div>
      </div>

      <!-- Subtasks -->
      <div class="bg-white border border-slate-200 rounded-xl p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold text-slate-700">Subtasks ({{ task.subtasks.length }})</h2>
          <button @click="showSubtask = true"
            class="text-xs bg-slate-100 text-slate-600 rounded px-3 py-1.5 hover:bg-slate-200">+ Subtask</button>
        </div>
        <ul class="space-y-2">
          <li v-for="sub in task.subtasks" :key="sub.id"
            class="flex items-center gap-3 text-sm">
            <span :class="statusBadge(sub.status)" class="text-xs px-2 py-0.5 rounded-full">{{ sub.status }}</span>
            <Link :href="`/admin/tasks/${sub.id}`" class="flex-1 hover:underline text-slate-700">{{ sub.title }}</Link>
          </li>
          <li v-if="task.subtasks.length === 0" class="text-xs text-slate-400">No subtasks yet.</li>
        </ul>

        <!-- Quick subtask create -->
        <div v-if="showSubtask" class="mt-4 flex gap-2">
          <input v-model="subtaskForm.title" placeholder="Subtask title"
            class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none" />
          <button @click="createSubtask"
            class="text-xs bg-blue-600 text-white rounded px-3 py-2 hover:bg-blue-700">Add</button>
          <button @click="showSubtask = false" class="text-xs text-slate-400 px-2">Cancel</button>
        </div>
      </div>

      <!-- Comments -->
      <div class="bg-white border border-slate-200 rounded-xl p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">Comments ({{ task.comments.length }})</h2>

        <div v-for="comment in task.comments" :key="comment.id" class="mb-4 last:mb-0">
          <div class="flex items-start gap-3">
            <img :src="comment.user.avatar ?? 'https://ui-avatars.com/api/?name=' + comment.user.name"
              class="w-7 h-7 rounded-full flex-shrink-0" :alt="comment.user.name" />
            <div class="flex-1 bg-slate-50 rounded-xl px-4 py-3">
              <p class="text-xs font-medium text-slate-600 mb-1">{{ comment.user.name }}</p>
              <p class="text-sm text-slate-700">{{ comment.body }}</p>
              <div class="flex gap-2 mt-2">
                <div v-for="m in comment.media" :key="m.id" class="w-12 h-12 rounded overflow-hidden">
                  <img v-if="m.is_image" :src="m.url" class="w-full h-full object-cover" />
                </div>
              </div>
            </div>
            <Link :href="`/admin/comments/${comment.id}`" method="delete" as="button"
              class="text-xs text-slate-300 hover:text-red-400 mt-2">✕</Link>
          </div>
        </div>

        <!-- Add comment -->
        <div class="mt-4 border-t border-slate-100 pt-4">
          <textarea v-model="commentForm.body" placeholder="Add a comment..."
            class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none resize-none min-h-16 mb-2" />
          <MediaUploader v-model="commentForm.media_ids" />
          <button @click="commentForm.post(`/admin/tasks/${task.id}/comments`)"
            class="mt-2 text-xs bg-blue-600 text-white rounded px-4 py-2 hover:bg-blue-700">Post Comment</button>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import MediaUploader from '@/Components/Admin/MediaUploader.vue'

const props = defineProps({ task: Object, users: Array, projects: Array })

const editing = ref(false)
const showSubtask = ref(false)

const form = useForm({
  title: props.task.title,
  body: props.task.body ?? '',
  status: props.task.status,
  priority: props.task.priority,
  media_ids: [],
})

const commentForm = useForm({ body: '', media_ids: [] })

const subtaskForm = useForm({
  project_id: props.task.project_id,
  parent_id: props.task.id,
  title: '',
  status: 'open',
  priority: 'medium',
})

const createSubtask = () => {
  subtaskForm.post('/admin/tasks', { onSuccess: () => { showSubtask.value = false; subtaskForm.reset('title') } })
}

const statusBadge = s => ({
  open: 'bg-amber-100 text-amber-700',
  in_progress: 'bg-blue-100 text-blue-700',
  done: 'bg-green-100 text-green-700',
  closed: 'bg-slate-100 text-slate-500',
}[s])
</script>
```

- [ ] **Step 9: Build and verify**

```bash
npm run build
```
Visit `http://portfolio.test/admin/tasks` → task list.  
Create task → shows task detail with subtask and comment sections.  
Upload file in comment → appears in comment media list.

- [ ] **Step 10: Commit**

```bash
git add .
git commit -m "feat: task/subtask management, comments, file attachments on tasks and comments"
```

---

## Phase 3 Complete ✅

At this point:
- MediaService handles image/video uploads to configurable disk
- MediaUploader Vue component works inline in any form
- Workspaces and Projects with GitHub repo linking + media library
- Tasks and subtasks (self-referential parent_id)
- Comments with file attachments on tasks/subtasks
- All features tested with Pest

**Next:** Phase 4 — GitHub Integration + Settings + Users panel
