# Portfolio CMS — Phase 2: Content Management

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fully working Page Builder (multi-page, block-based, templates, publish/draft) and Service Card management (CRUD, reorder, featured toggle, optional page/URL link).

**Architecture:** PageController + ServiceCardController return Inertia responses. Blocks stored as a JSON array in `pages.blocks`. Service cards use `sort_order` for manual ordering. Both features are protected by `auth + role:admin,editor` middleware.

**Prerequisites:** Phase 1 complete — models, migrations, admin shell all in place.

---

## File Map

| File | Purpose |
|---|---|
| `app/Http/Controllers/Admin/PageController.php` | CRUD + publish for Pages |
| `app/Http/Controllers/Admin/ServiceCardController.php` | CRUD + reorder for Service Cards |
| `resources/js/Pages/Admin/Pages/Index.vue` | Page list + sidebar |
| `resources/js/Pages/Admin/Pages/Edit.vue` | Block editor canvas |
| `resources/js/Pages/Admin/ServiceCards/Index.vue` | Card list with drag reorder |
| `resources/js/Pages/Admin/ServiceCards/Form.vue` | Create/edit card form |
| `resources/js/Components/Admin/BlockEditor.vue` | Reusable block canvas component |
| `resources/js/Components/Admin/MediaUploader.vue` | File upload picker (reused across app) |
| `routes/web.php` | Add page + service-card routes |

---

## Task 7: Page Builder — backend

**Files:**
- Create: `app/Http/Controllers/Admin/PageController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write failing test**

```bash
php artisan make:test PageControllerTest
```
```php
// tests/Feature/PageControllerTest.php
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('lists pages', function () {
    $this->actingAs($this->admin)
        ->get('/admin/pages')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Pages/Index'));
});

it('creates a page', function () {
    $this->actingAs($this->admin)
        ->post('/admin/pages', ['name' => 'About', 'template' => 'blank'])
        ->assertRedirect();
    expect(\App\Models\Page::where('name', 'About')->exists())->toBeTrue();
});

it('publishes a page', function () {
    $p = \App\Models\Page::create(['name' => 'Test', 'slug' => 'test', 'template' => 'blank']);
    $this->actingAs($this->admin)
        ->patch("/admin/pages/{$p->id}/publish")
        ->assertRedirect();
    expect($p->fresh()->status)->toBe('published');
});
```

- [ ] **Step 2: Run to verify fail**

```bash
php artisan test tests/Feature/PageControllerTest.php
```
Expected: FAIL — controller/routes missing.

- [ ] **Step 3: Create PageController**

```php
<?php
// app/Http/Controllers/Admin/PageController.php
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
            'pages' => Page::orderBy('updated_at', 'desc')->get(['id','name','slug','status','template','updated_at']),
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
            'hero_cards'  => [['type' => 'hero', 'order' => 0, 'data' => ['heading' => '', 'subheading' => '']], ['type' => 'service_cards', 'order' => 1, 'data' => []]],
            'text_image'  => [['type' => 'text_image', 'order' => 0, 'data' => ['text' => '', 'image' => '']]],
            'project_grid'=> [['type' => 'project_grid', 'order' => 0, 'data' => []]],
            default       => [],
        };

        $page = Page::create(array_merge($data, ['blocks' => $blocks]));

        return redirect("/admin/pages/{$page->id}/edit");
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('Admin/Pages/Edit', [
            'page' => $page,
            'blockTypes' => ['hero','text','text_image','service_cards','project_grid','contact_form'],
        ]);
    }

    public function update(Request $request, Page $page)
    {
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
        $page->update(['status' => 'published', 'published_at' => now()]);
        return back()->with('success', 'Page published.');
    }

    public function destroy(Page $page)
    {
        $page->delete();
        return redirect('/admin/pages')->with('success', 'Page deleted.');
    }

    /** Public preview route */
    public function preview(Page $page): \Illuminate\Contracts\View\View
    {
        return view('page-preview', compact('page'));
    }
}
```

- [ ] **Step 4: Add page routes**

```php
// routes/web.php — inside admin middleware group:
use App\Http\Controllers\Admin\PageController;

Route::middleware(['auth', 'role:admin,editor'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::resource('pages', PageController::class)->except(['show']);
        Route::patch('pages/{page}/publish', [PageController::class, 'publish'])->name('pages.publish');
    });

// Public page preview (outside admin middleware):
Route::get('/preview/pages/{page}', [PageController::class, 'preview'])->name('pages.preview');
```

- [ ] **Step 5: Create page preview Blade view**

```bash
mkdir -p resources/views
```
```html
<!-- resources/views/page-preview.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>{{ $page->name }} – Preview</title></head>
<body>
  <h1>{{ $page->name }}</h1>
  @foreach($page->blocks ?? [] as $block)
    <section data-block="{{ $block['type'] }}">
      <pre>{{ json_encode($block['data'], JSON_PRETTY_PRINT) }}</pre>
    </section>
  @endforeach
</body>
</html>
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/PageControllerTest.php
```
Expected: 3 passed.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/PageController.php routes/web.php resources/views/page-preview.blade.php tests/
git commit -m "feat: page builder backend — CRUD, publish, preview routes"
```

---

## Task 8: Page Builder — frontend

**Files:**
- Create: `resources/js/Pages/Admin/Pages/Index.vue`
- Create: `resources/js/Pages/Admin/Pages/Edit.vue`
- Create: `resources/js/Components/Admin/BlockEditor.vue`

- [ ] **Step 1: Create Pages/Index.vue**

```vue
<!-- resources/js/Pages/Admin/Pages/Index.vue -->
<template>
  <AdminLayout>
    <div class="flex gap-6">
      <!-- Sidebar -->
      <div class="w-52 flex-shrink-0 space-y-4">
        <!-- Page list -->
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
          <div class="bg-slate-50 px-4 py-3 flex items-center justify-between border-b border-slate-100">
            <span class="text-xs font-semibold text-slate-500 uppercase">Pages</span>
            <button @click="showCreate = true"
              class="text-xs bg-blue-600 text-white rounded px-2 py-0.5 hover:bg-blue-700">+</button>
          </div>
          <nav class="p-2 space-y-1">
            <Link v-for="p in pages" :key="p.id"
              :href="`/admin/pages/${p.id}/edit`"
              class="block px-3 py-2 rounded-lg text-sm transition"
              :class="p.status === 'published' ? 'text-slate-700' : 'text-slate-400'"
            >
              {{ p.name }}
              <span v-if="p.status === 'draft'" class="text-xs text-amber-500 ml-1">draft</span>
            </Link>
          </nav>
        </div>

        <!-- Templates -->
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
          <div class="bg-slate-50 px-4 py-3 border-b border-slate-100">
            <span class="text-xs font-semibold text-slate-500 uppercase">Templates</span>
          </div>
          <div class="p-2 space-y-1">
            <button v-for="t in templates" :key="t"
              @click="createWithTemplate(t)"
              class="block w-full text-left px-3 py-2 text-xs text-slate-600 border border-slate-100 rounded-lg hover:bg-slate-50"
            >{{ templateLabel(t) }}</button>
          </div>
        </div>
      </div>

      <!-- Main -->
      <div class="flex-1 bg-white border border-slate-200 rounded-xl flex items-center justify-center min-h-64 text-slate-400 text-sm">
        Select a page to edit, or create a new one.
      </div>
    </div>

    <!-- Create modal -->
    <div v-if="showCreate" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl p-6 w-80 shadow-xl">
        <h2 class="font-semibold mb-4 text-slate-800">New Page</h2>
        <input v-model="form.name" placeholder="Page name"
          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 outline-none focus:ring-2 focus:ring-blue-500" />
        <select v-model="form.template"
          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-4 outline-none">
          <option v-for="t in templates" :key="t" :value="t">{{ templateLabel(t) }}</option>
        </select>
        <div class="flex gap-2 justify-end">
          <button @click="showCreate = false" class="px-4 py-2 text-sm text-slate-500">Cancel</button>
          <button @click="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg">Create</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ pages: Array, templates: Array })
const showCreate = ref(false)
const form = useForm({ name: '', template: 'blank' })

const templateLabel = t => ({ blank:'📋 Blank', hero_cards:'🌟 Hero + Cards', text_image:'📝 Text + Image', project_grid:'🗂️ Project Grid' }[t] ?? t)

const submit = () => form.post('/admin/pages', { onSuccess: () => { showCreate.value = false } })

const createWithTemplate = (t) => {
  form.template = t
  showCreate.value = true
}
</script>
```

- [ ] **Step 2: Create BlockEditor component**

```vue
<!-- resources/js/Components/Admin/BlockEditor.vue -->
<template>
  <div class="space-y-3">
    <div v-for="(block, idx) in blocks" :key="idx"
      class="relative border-2 rounded-xl p-4"
      :class="idx === active ? 'border-blue-500 bg-blue-50/30' : 'border-slate-200 bg-white'"
      @click="active = idx"
    >
      <span class="absolute -top-3 left-3 text-xs font-semibold px-2 py-0.5 rounded text-white"
        :class="blockColor(block.type)">{{ block.type.replace('_',' ').toUpperCase() }}</span>

      <!-- Hero fields -->
      <template v-if="block.type === 'hero'">
        <input v-model="block.data.heading" placeholder="Heading"
          class="w-full text-lg font-bold bg-transparent outline-none mb-1" />
        <input v-model="block.data.subheading" placeholder="Subheading"
          class="w-full text-sm text-slate-500 bg-transparent outline-none" />
      </template>

      <!-- Text fields -->
      <template v-else-if="block.type === 'text'">
        <textarea v-model="block.data.content" placeholder="Text content..."
          class="w-full bg-transparent outline-none text-sm resize-none min-h-20" />
      </template>

      <!-- Text + Image -->
      <template v-else-if="block.type === 'text_image'">
        <textarea v-model="block.data.text" placeholder="Text content..."
          class="w-full bg-transparent outline-none text-sm resize-none min-h-16 mb-2" />
        <input v-model="block.data.image" placeholder="Image URL or upload path"
          class="w-full border border-slate-200 rounded px-3 py-1.5 text-sm outline-none" />
      </template>

      <!-- Info blocks -->
      <template v-else>
        <p class="text-sm text-slate-500">{{ blockDescription(block.type) }}</p>
      </template>

      <!-- Controls -->
      <div class="absolute top-2 right-2 flex gap-1">
        <button @click.stop="move(idx, -1)" :disabled="idx === 0"
          class="text-slate-400 hover:text-slate-700 disabled:opacity-30 text-xs px-1">▲</button>
        <button @click.stop="move(idx, 1)" :disabled="idx === blocks.length - 1"
          class="text-slate-400 hover:text-slate-700 disabled:opacity-30 text-xs px-1">▼</button>
        <button @click.stop="remove(idx)"
          class="text-red-400 hover:text-red-600 text-xs px-1">✕</button>
      </div>
    </div>

    <!-- Add block -->
    <div class="border-2 border-dashed border-slate-200 rounded-xl p-4">
      <p class="text-xs text-slate-400 mb-2 text-center">Add block</p>
      <div class="flex flex-wrap gap-2 justify-center">
        <button v-for="type in blockTypes" :key="type"
          @click="addBlock(type)"
          class="text-xs px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
          {{ type.replace('_',' ') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({ modelValue: Array, blockTypes: Array })
const emit = defineEmits(['update:modelValue'])

const active = ref(null)
const blocks = computed({
  get: () => props.modelValue ?? [],
  set: val => emit('update:modelValue', val),
})

import { computed } from 'vue'

const addBlock = (type) => {
  blocks.value = [...blocks.value, { type, order: blocks.value.length, data: {} }]
}

const remove = (idx) => {
  blocks.value = blocks.value.filter((_, i) => i !== idx)
}

const move = (idx, dir) => {
  const arr = [...blocks.value]
  const target = idx + dir
  if (target < 0 || target >= arr.length) return;
  [arr[idx], arr[target]] = [arr[target], arr[idx]]
  blocks.value = arr
}

const blockColor = t => ({
  hero: 'bg-blue-500', text: 'bg-amber-500', text_image: 'bg-purple-500',
  service_cards: 'bg-violet-500', project_grid: 'bg-teal-500', contact_form: 'bg-rose-500',
}[t] ?? 'bg-slate-500')

const blockDescription = t => ({
  service_cards: 'Displays all service cards in a grid',
  project_grid: 'Displays projects in a grid',
  contact_form: 'Contact form section',
}[t] ?? '')
</script>
```

- [ ] **Step 3: Create Pages/Edit.vue**

```vue
<!-- resources/js/Pages/Admin/Pages/Edit.vue -->
<template>
  <AdminLayout>
    <div class="flex gap-6">
      <!-- Sidebar -->
      <div class="w-52 flex-shrink-0">
        <div class="bg-white border border-slate-200 rounded-xl p-4 space-y-3">
          <div>
            <label class="text-xs text-slate-500">Page name</label>
            <input v-model="form.name" class="w-full mt-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none" />
          </div>
          <div>
            <label class="text-xs text-slate-500">Status</label>
            <p class="text-sm font-medium mt-1" :class="form.status === 'published' ? 'text-green-600' : 'text-amber-500'">
              {{ form.status }}
            </p>
          </div>
          <button @click="save" class="w-full bg-slate-800 text-white text-sm rounded-lg py-2 hover:bg-slate-900">
            Save Draft
          </button>
          <button @click="publish"
            class="w-full bg-green-600 text-white text-sm rounded-lg py-2 hover:bg-green-700">
            Publish
          </button>
          <a :href="`/preview/pages/${page.id}`" target="_blank"
            class="block text-center text-sm text-blue-600 hover:underline">Preview ↗</a>
        </div>
      </div>

      <!-- Block canvas -->
      <div class="flex-1">
        <BlockEditor v-model="form.blocks" :block-types="blockTypes" />
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import BlockEditor from '@/Components/Admin/BlockEditor.vue'

const props = defineProps({ page: Object, blockTypes: Array })

const form = useForm({
  name: props.page.name,
  blocks: props.page.blocks ?? [],
  status: props.page.status,
})

const save = () => form.patch(`/admin/pages/${props.page.id}`, { preserveScroll: true })
const publish = () => form.patch(`/admin/pages/${props.page.id}/publish`)
</script>
```

- [ ] **Step 4: Build and verify in browser**

```bash
npm run dev
```
Visit `http://portfolio.test/admin/pages` → page list visible with sidebar and template picker.
Create a new page → redirects to block editor → add blocks → save.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Admin/Pages/ resources/js/Components/Admin/BlockEditor.vue
git commit -m "feat: page builder frontend — block editor, templates, publish"
```

---

## Task 9: Service Card management

**Files:**
- Create: `app/Http/Controllers/Admin/ServiceCardController.php`
- Create: `resources/js/Pages/Admin/ServiceCards/Index.vue`
- Create: `resources/js/Pages/Admin/ServiceCards/Form.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Write failing test**

```bash
php artisan make:test ServiceCardControllerTest
```
```php
// tests/Feature/ServiceCardControllerTest.php
use App\Models\User;
use App\Models\ServiceCard;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('lists service cards', function () {
    $this->actingAs($this->admin)
        ->get('/admin/service-cards')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Admin/ServiceCards/Index'));
});

it('creates a service card', function () {
    $this->actingAs($this->admin)
        ->post('/admin/service-cards', ['title' => 'Web Dev', 'description' => 'Laravel apps'])
        ->assertRedirect();
    expect(ServiceCard::where('title', 'Web Dev')->exists())->toBeTrue();
});

it('reorders service cards', function () {
    $a = ServiceCard::create(['title' => 'A', 'sort_order' => 0]);
    $b = ServiceCard::create(['title' => 'B', 'sort_order' => 1]);
    $this->actingAs($this->admin)
        ->post('/admin/service-cards/reorder', ['ids' => [$b->id, $a->id]])
        ->assertOk();
    expect($a->fresh()->sort_order)->toBe(1);
    expect($b->fresh()->sort_order)->toBe(0);
});
```

- [ ] **Step 2: Run to verify fail**

```bash
php artisan test tests/Feature/ServiceCardControllerTest.php
```

- [ ] **Step 3: Create ServiceCardController**

```php
<?php
// app/Http/Controllers/Admin/ServiceCardController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\ServiceCard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/ServiceCards/Index', [
            'cards' => ServiceCard::with('page:id,name')->orderBy('sort_order')->get(),
            'pages' => Page::where('status', 'published')->get(['id','name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/ServiceCards/Form', [
            'pages' => Page::where('status', 'published')->get(['id','name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'icon'         => 'nullable|string|max:100',
            'image'        => 'nullable|string',
            'tags'         => 'nullable|array',
            'featured'     => 'boolean',
            'page_id'      => 'nullable|exists:pages,id',
            'external_url' => 'nullable|url',
        ]);

        $data['sort_order'] = ServiceCard::max('sort_order') + 1;
        ServiceCard::create($data);

        return redirect('/admin/service-cards')->with('success', 'Card created.');
    }

    public function edit(ServiceCard $serviceCard): Response
    {
        return Inertia::render('Admin/ServiceCards/Form', [
            'card'  => $serviceCard,
            'pages' => Page::where('status', 'published')->get(['id','name']),
        ]);
    }

    public function update(Request $request, ServiceCard $serviceCard)
    {
        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'icon'         => 'nullable|string|max:100',
            'image'        => 'nullable|string',
            'tags'         => 'nullable|array',
            'featured'     => 'boolean',
            'page_id'      => 'nullable|exists:pages,id',
            'external_url' => 'nullable|url',
        ]);

        $serviceCard->update($data);
        return redirect('/admin/service-cards')->with('success', 'Card updated.');
    }

    public function destroy(ServiceCard $serviceCard)
    {
        $serviceCard->delete();
        return redirect('/admin/service-cards')->with('success', 'Card deleted.');
    }

    public function reorder(Request $request)
    {
        $request->validate(['ids' => 'required|array']);
        foreach ($request->ids as $order => $id) {
            ServiceCard::where('id', $id)->update(['sort_order' => $order]);
        }
        return response()->json(['ok' => true]);
    }
}
```

- [ ] **Step 4: Add service-card routes**

```php
// routes/web.php — inside admin+role middleware group:
use App\Http\Controllers\Admin\ServiceCardController;

Route::resource('service-cards', ServiceCardController::class)->except(['show']);
Route::post('service-cards/reorder', [ServiceCardController::class, 'reorder'])->name('service-cards.reorder');
```

- [ ] **Step 5: Run tests**

```bash
php artisan test tests/Feature/ServiceCardControllerTest.php
```
Expected: 3 passed.

- [ ] **Step 6: Create ServiceCards/Index.vue**

```vue
<!-- resources/js/Pages/Admin/ServiceCards/Index.vue -->
<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-slate-800">Service Cards</h1>
      <Link href="/admin/service-cards/create"
        class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700">
        + New Card
      </Link>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
      <div v-if="cards.length === 0" class="p-8 text-center text-slate-400 text-sm">
        No service cards yet. Create your first one.
      </div>
      <ul class="divide-y divide-slate-100">
        <li v-for="card in sortedCards" :key="card.id"
          class="flex items-center gap-4 px-5 py-4 hover:bg-slate-50">
          <span class="text-2xl">{{ card.icon || '🎴' }}</span>
          <div class="flex-1 min-w-0">
            <p class="font-medium text-slate-800 text-sm">{{ card.title }}</p>
            <p class="text-xs text-slate-400 truncate">{{ card.description }}</p>
          </div>
          <span v-if="card.featured"
            class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Featured</span>
          <span v-if="card.page"
            class="text-xs text-blue-600">→ {{ card.page.name }}</span>
          <div class="flex gap-2">
            <Link :href="`/admin/service-cards/${card.id}/edit`"
              class="text-xs text-slate-500 hover:text-slate-800 px-2 py-1 border border-slate-200 rounded">
              Edit
            </Link>
            <button @click="deleteCard(card.id)"
              class="text-xs text-red-500 hover:text-red-700 px-2 py-1 border border-red-100 rounded">
              Delete
            </button>
          </div>
        </li>
      </ul>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ cards: Array, pages: Array })
const sortedCards = computed(() => [...props.cards].sort((a, b) => a.sort_order - b.sort_order))

const deleteCard = (id) => {
  if (confirm('Delete this card?')) {
    router.delete(`/admin/service-cards/${id}`)
  }
}
</script>
```

- [ ] **Step 7: Create ServiceCards/Form.vue**

```vue
<!-- resources/js/Pages/Admin/ServiceCards/Form.vue -->
<template>
  <AdminLayout>
    <div class="max-w-xl mx-auto">
      <h1 class="text-2xl font-bold text-slate-800 mb-6">{{ card ? 'Edit' : 'New' }} Service Card</h1>

      <form @submit.prevent="submit" class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
        <div>
          <label class="block text-sm text-slate-600 mb-1">Title *</label>
          <input v-model="form.title" class="input" required />
        </div>
        <div>
          <label class="block text-sm text-slate-600 mb-1">Description</label>
          <textarea v-model="form.description" class="input min-h-20 resize-none" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-slate-600 mb-1">Icon (emoji or class)</label>
            <input v-model="form.icon" class="input" placeholder="🚀 or heroicon-outline-star" />
          </div>
          <div>
            <label class="block text-sm text-slate-600 mb-1">Image URL</label>
            <input v-model="form.image" class="input" placeholder="/storage/..." />
          </div>
        </div>
        <div>
          <label class="block text-sm text-slate-600 mb-1">Tags (comma separated)</label>
          <input v-model="tagsInput" class="input" placeholder="Laravel, Vue, API" />
        </div>
        <div class="flex items-center gap-3">
          <input v-model="form.featured" type="checkbox" id="featured" class="w-4 h-4" />
          <label for="featured" class="text-sm text-slate-600">Featured card</label>
        </div>
        <div>
          <label class="block text-sm text-slate-600 mb-1">Link to Page (optional)</label>
          <select v-model="form.page_id" class="input">
            <option :value="null">— none —</option>
            <option v-for="p in pages" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm text-slate-600 mb-1">External URL (optional)</label>
          <input v-model="form.external_url" class="input" placeholder="https://..." />
        </div>

        <div class="flex gap-3 pt-2">
          <button type="submit"
            class="bg-blue-600 text-white text-sm px-5 py-2 rounded-lg hover:bg-blue-700">
            {{ card ? 'Update' : 'Create' }}
          </button>
          <Link href="/admin/service-cards" class="text-sm text-slate-500 px-4 py-2">Cancel</Link>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ card: Object, pages: Array })

const tagsInput = ref(props.card?.tags?.join(', ') ?? '')

const form = useForm({
  title:        props.card?.title ?? '',
  description:  props.card?.description ?? '',
  icon:         props.card?.icon ?? '',
  image:        props.card?.image ?? '',
  tags:         props.card?.tags ?? [],
  featured:     props.card?.featured ?? false,
  page_id:      props.card?.page_id ?? null,
  external_url: props.card?.external_url ?? '',
})

const submit = () => {
  form.tags = tagsInput.value.split(',').map(t => t.trim()).filter(Boolean)
  if (props.card) {
    form.patch(`/admin/service-cards/${props.card.id}`)
  } else {
    form.post('/admin/service-cards')
  }
}
</script>

<style scoped>
.input { @apply w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500; }
</style>
```

- [ ] **Step 8: Build and verify**

```bash
npm run build
```
Visit `http://portfolio.test/admin/service-cards` → empty state shown.  
Create a card → form submits → redirects to list with new card.

- [ ] **Step 9: Commit**

```bash
git add .
git commit -m "feat: service card management — CRUD, reorder, page/URL linking"
```

---

## Phase 2 Complete ✅

At this point:
- Page Builder: create/edit pages with typed blocks, templates, publish/draft, public preview
- Service Cards: full CRUD, sort order, featured flag, optional page or URL link
- All features tested with Pest

**Next:** Phase 3 — Projects, Tasks, Subtasks, Comments, File Uploads
