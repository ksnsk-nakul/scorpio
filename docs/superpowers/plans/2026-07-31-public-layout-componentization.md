# Public Layout & Nav Componentization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `Library` link to public site navigation by extracting the genuinely shared nav/footer pieces into reusable components, per `docs/superpowers/specs/2026-07-31-public-layout-componentization-design.md`.

**Architecture:** Three small components (`AuthNavCta`, `PublicNav`, `PublicFooter`) compose into a `PublicLayout` wrapper. `Home.vue`, the three `Library/*.vue` pages, and `ProjectDetail.vue` adopt `PublicLayout` directly. `OrgPage.vue` uses `PublicNav`/`PublicFooter` conditionally (skipped when white-labeled). `Portfolio.vue` keeps its own bespoke anchor-nav but reuses `AuthNavCta` and `PublicFooter` for dedup, plus gains one added `Library` link.

**Tech Stack:** Vue 3 Composition API, Inertia.js v2, Tailwind v4, Vitest + @vue/test-utils, Pest (one small backend change).

**Implementation note (resolved during planning):** the spec assumed zero backend changes, but `PublicNav`/`PublicFooter` need the site's brand name (`site_name`), and today that's fetched per-controller via a private `tenantSettings($user)` helper in `PublicController` — it is not available to `LibraryController`'s pages at all, and `Library/*.vue` don't receive a `settings` prop. Rather than duplicate that fetch into `LibraryController` (and thread a `siteName` prop through every page and every `<PublicLayout>` call site), Task 1 promotes `site_name` to a globally-shared Inertia prop (mirroring the already-shared `auth` prop in the same file), read directly via `usePage()` inside `PublicNav.vue`/`PublicFooter.vue` — no page-level prop threading needed anywhere.

---

### Task 1: Share `settings.site_name` globally via Inertia

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php:38-85`
- Test: `tests/Feature/PublicLibraryTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/PublicLibraryTest.php`:

```php
it('shares the site name on public library pages', function () {
    \App\Models\User::factory()->create(['site_name' => 'My Test Site'])->assignRole('admin');

    $response = $this->get('/library');

    $response->assertInertia(fn ($page) => $page
        ->where('settings.site_name', 'My Test Site'));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter="shares the site name on public library pages"`
Expected: FAIL — `settings` prop not found on the page.

- [ ] **Step 3: Add the shared prop**

In `app/Http/Middleware/HandleInertiaRequests.php`, add `use App\Models\Setting;` and `use App\Models\User;` to the top imports, then add a `'settings'` entry to the array returned by `share()` (insert it right after the existing `'auth' => ...` entry, before `'flash' => ...`):

```php
            'settings' => fn () => [
                'site_name' => User::role('admin')->value('site_name') ?? Setting::get('site_name', 'Portfolio'),
            ],
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter="shares the site name on public library pages"`
Expected: PASS

- [ ] **Step 5: Run the full test suite to check for regressions**

Run: `php artisan test`
Expected: all tests pass (this is an additive shared prop; no existing page reads `usePage().props.settings`, so nothing can break from it being newly present).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php tests/Feature/PublicLibraryTest.php
git commit -m "feat: share site_name globally via Inertia for public nav/footer"
```

---

### Task 2: `AuthNavCta` component

**Files:**
- Create: `resources/js/Components/Public/AuthNavCta.vue`
- Test: `tests/js/AuthNavCta.test.js`

- [ ] **Step 1: Write the failing tests**

```js
// tests/js/AuthNavCta.test.js
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'

const pageProps = { auth: null }
vi.mock('@inertiajs/vue3', () => ({
  usePage: () => ({ props: pageProps }),
}))

beforeEach(() => {
  pageProps.auth = null
})

describe('AuthNavCta', () => {
  it('shows Login and Sign up when logged out', async () => {
    const { default: AuthNavCta } = await import('@/Components/Public/AuthNavCta.vue')
    const wrapper = mount(AuthNavCta)
    expect(wrapper.text()).toContain('Login')
    expect(wrapper.text()).toContain('Sign up')
    expect(wrapper.text()).not.toContain('Dashboard')
  })

  it('shows nothing when logged in as a non-admin', async () => {
    pageProps.auth = { user: { id: 1 }, roles: ['viewer'] }
    const { default: AuthNavCta } = await import('@/Components/Public/AuthNavCta.vue')
    const wrapper = mount(AuthNavCta)
    expect(wrapper.text().trim()).toBe('')
  })

  it('shows a Dashboard link when logged in as admin', async () => {
    pageProps.auth = { user: { id: 1 }, roles: ['admin'] }
    const { default: AuthNavCta } = await import('@/Components/Public/AuthNavCta.vue')
    const wrapper = mount(AuthNavCta)
    expect(wrapper.text()).toContain('Dashboard')
    expect(wrapper.find('a[href="/admin/dashboard"]').exists()).toBe(true)
  })
})
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npm run test:unit -- AuthNavCta`
Expected: FAIL — `Cannot find module '@/Components/Public/AuthNavCta.vue'`

- [ ] **Step 3: Implement the component**

```vue
<!-- resources/js/Components/Public/AuthNavCta.vue -->
<template>
  <div class="flex items-center gap-3">
    <a v-if="isAdmin" href="/admin/dashboard"
      class="text-sm px-4 py-1.5 rounded-lg bg-slate-900 text-white hover:bg-slate-700 transition-colors">
      Dashboard →
    </a>
    <template v-else-if="!isLoggedIn">
      <a href="/login" class="text-sm px-4 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">Login</a>
      <a href="/register" class="text-sm px-4 py-1.5 rounded-lg bg-orange-500 text-white hover:bg-orange-600 transition-colors">Sign up</a>
    </template>
  </div>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const { props: pageProps } = usePage()
const isLoggedIn = computed(() => !!pageProps.auth?.user)
const isAdmin = computed(() => pageProps.auth?.roles?.includes('admin') ?? false)
</script>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `npm run test:unit -- AuthNavCta`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/Public/AuthNavCta.vue tests/js/AuthNavCta.test.js
git commit -m "feat: extract AuthNavCta component"
```

---

### Task 3: `PublicNav` component

**Files:**
- Create: `resources/js/Components/Public/PublicNav.vue`
- Test: `tests/js/PublicNav.test.js`

- [ ] **Step 1: Write the failing tests**

```js
// tests/js/PublicNav.test.js
import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('@inertiajs/vue3', () => ({
  usePage: () => ({ props: { auth: null, settings: { site_name: 'Test Site' } } }),
}))

describe('PublicNav', () => {
  it('renders the site name from shared settings and a Library link', async () => {
    const { default: PublicNav } = await import('@/Components/Public/PublicNav.vue')
    const wrapper = mount(PublicNav)
    expect(wrapper.text()).toContain('Test Site')
    expect(wrapper.find('a[href="/library"]').exists()).toBe(true)
  })

  it('falls back to "Portfolio" when settings.site_name is missing', async () => {
    vi.resetModules()
    vi.doMock('@inertiajs/vue3', () => ({
      usePage: () => ({ props: { auth: null, settings: {} } }),
    }))
    const { default: PublicNav } = await import('@/Components/Public/PublicNav.vue')
    const wrapper = mount(PublicNav)
    expect(wrapper.text()).toContain('Portfolio')
  })
})
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npm run test:unit -- PublicNav`
Expected: FAIL — `Cannot find module '@/Components/Public/PublicNav.vue'`

- [ ] **Step 3: Implement the component**

```vue
<!-- resources/js/Components/Public/PublicNav.vue -->
<template>
  <nav class="fixed top-0 inset-x-0 z-50 bg-white/80 backdrop-blur border-b border-slate-100">
    <div class="max-w-5xl mx-auto px-6 h-14 flex items-center justify-between">
      <a href="/" class="font-semibold text-slate-800 tracking-tight">{{ siteName }}</a>
      <div class="flex items-center gap-5">
        <a href="/library" class="text-sm text-slate-600 hover:text-orange-500 transition-colors">Library</a>
        <AuthNavCta />
      </div>
    </div>
  </nav>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import AuthNavCta from '@/Components/Public/AuthNavCta.vue'

const { props: pageProps } = usePage()
const siteName = computed(() => pageProps.settings?.site_name || 'Portfolio')
</script>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `npm run test:unit -- PublicNav`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/Public/PublicNav.vue tests/js/PublicNav.test.js
git commit -m "feat: add PublicNav component"
```

---

### Task 4: `PublicFooter` component

**Files:**
- Create: `resources/js/Components/Public/PublicFooter.vue`
- Test: `tests/js/PublicFooter.test.js`

- [ ] **Step 1: Write the failing tests**

```js
// tests/js/PublicFooter.test.js
import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('@inertiajs/vue3', () => ({
  usePage: () => ({ props: { settings: { site_name: 'Test Site' } } }),
}))

describe('PublicFooter', () => {
  it('renders the site name and legal links', async () => {
    const { default: PublicFooter } = await import('@/Components/Public/PublicFooter.vue')
    const wrapper = mount(PublicFooter)
    expect(wrapper.text()).toContain('Test Site')
    expect(wrapper.find('a[href="/terms"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/privacy"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/refund"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/donate"]').exists()).toBe(true)
  })
})
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npm run test:unit -- PublicFooter`
Expected: FAIL — `Cannot find module '@/Components/Public/PublicFooter.vue'`

- [ ] **Step 3: Implement the component**

```vue
<!-- resources/js/Components/Public/PublicFooter.vue -->
<template>
  <footer class="border-t border-slate-100 py-10 text-center">
    <img src="/images/ksnsk-logo.png" alt="KSNSK" class="h-8 mx-auto mb-4 opacity-60" />
    <p class="text-xs text-slate-400 mb-3">{{ siteName }}</p>
    <p class="text-xs text-slate-300">© {{ new Date().getFullYear() }} · Built with Laravel &amp; Vue</p>
    <div class="flex items-center justify-center gap-4 mt-3">
      <a href="/terms" class="text-xs text-slate-400 hover:text-slate-600 transition-colors">Terms</a>
      <span class="text-slate-200">·</span>
      <a href="/privacy" class="text-xs text-slate-400 hover:text-slate-600 transition-colors">Privacy</a>
      <span class="text-slate-200">·</span>
      <a href="/refund" class="text-xs text-slate-400 hover:text-slate-600 transition-colors">Refund Policy</a>
    </div>
    <a href="/donate" class="inline-flex items-center gap-1.5 text-xs text-pink-400 hover:text-pink-600 transition-colors mt-3">
      <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
      Support my work
    </a>
  </footer>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const { props: pageProps } = usePage()
const siteName = computed(() => pageProps.settings?.site_name || 'Portfolio')
</script>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `npm run test:unit -- PublicFooter`
Expected: PASS (1 test)

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/Public/PublicFooter.vue tests/js/PublicFooter.test.js
git commit -m "feat: add PublicFooter component"
```

---

### Task 5: `PublicLayout` wrapper

**Files:**
- Create: `resources/js/Layouts/PublicLayout.vue`
- Test: `tests/js/PublicLayout.test.js`

- [ ] **Step 1: Write the failing test**

```js
// tests/js/PublicLayout.test.js
import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('@inertiajs/vue3', () => ({
  usePage: () => ({ props: { auth: null, settings: { site_name: 'Test Site' } } }),
}))

describe('PublicLayout', () => {
  it('renders PublicNav, the default slot content, and PublicFooter', async () => {
    const { default: PublicLayout } = await import('@/Layouts/PublicLayout.vue')
    const wrapper = mount(PublicLayout, {
      slots: { default: '<p class="page-content">Hello</p>' },
    })
    expect(wrapper.find('nav').exists()).toBe(true)
    expect(wrapper.find('.page-content').text()).toBe('Hello')
    expect(wrapper.find('footer').exists()).toBe(true)
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test:unit -- PublicLayout`
Expected: FAIL — `Cannot find module '@/Layouts/PublicLayout.vue'`

- [ ] **Step 3: Implement the layout**

```vue
<!-- resources/js/Layouts/PublicLayout.vue -->
<template>
  <div class="min-h-screen bg-white font-sans">
    <PublicNav />
    <main class="pt-14">
      <slot />
    </main>
    <PublicFooter />
  </div>
</template>

<script setup>
import PublicNav from '@/Components/Public/PublicNav.vue'
import PublicFooter from '@/Components/Public/PublicFooter.vue'
</script>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test:unit -- PublicLayout`
Expected: PASS (1 test)

- [ ] **Step 5: Commit**

```bash
git add resources/js/Layouts/PublicLayout.vue tests/js/PublicLayout.test.js
git commit -m "feat: add PublicLayout wrapper"
```

---

### Task 6: Wire `Home.vue` to `PublicLayout`

**Files:**
- Modify: `resources/js/Pages/Public/Home.vue`

- [ ] **Step 1: Replace the nav+main+footer wrapper**

Replace lines 1–21 (from `<template>` through the closing `</nav>`) with:

```vue
<template>
  <PublicLayout>
```

Replace the closing of `<main>` and the `<footer>...</footer>` block (the current lines from `</main>` through the final `</div>` and `</template>`) — i.e. replace:

```vue
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-100 py-8 text-center text-xs text-slate-400">
      {{ settings.site_name ?? 'Portfolio' }}
      <span v-if="settings.site_tagline"> — {{ settings.site_tagline }}</span>
    </footer>
  </div>
</template>
```

with:

```vue
  </PublicLayout>
</template>
```

Also remove the now-unwrapped `<main class="pt-14">` opening tag (it becomes redundant since `PublicLayout` already renders `<main class="pt-14">`) — the block that starts `<!-- Page content -->` / `<main class="pt-14">` should just drop that wrapping `<main>` tag, keeping its inner `<template v-if="pages.length">...</template>` and the two `v-else`/`v-else-if` blocks as direct children of `<PublicLayout>`.

- [ ] **Step 2: Update the script block**

Add the import to the top of the `<script setup>` block:

```js
import PublicLayout from '@/Layouts/PublicLayout.vue'
```

The rest of the script block (`props`, `isAdmin`) stays unchanged — `isAdmin` is still used for the "no published pages" empty state, unrelated to the nav.

- [ ] **Step 3: Manually verify**

Run: `npm run build`, then load `/` in the browser (with no admin user, or via whatever currently triggers the `Home.vue` render path). Expected: the page shows the shared `PublicNav` (site name, `Library` link, Login/Sign up or Dashboard depending on auth state) and `PublicFooter` at the bottom; page content (hero/blocks or empty state) renders unchanged in between.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Public/Home.vue
git commit -m "feat: wire Home.vue to PublicLayout"
```

---

### Task 7: Wire the three `Library/*.vue` pages to `PublicLayout`, open chapters in a new tab

**Files:**
- Modify: `resources/js/Pages/Public/Library/Index.vue`
- Modify: `resources/js/Pages/Public/Library/BookDetail.vue`
- Modify: `resources/js/Pages/Public/Library/AuthorShow.vue`

- [ ] **Step 1: `Index.vue`** — replace the `<div class="min-h-screen bg-white font-sans">...<nav>...</nav><main class="pt-20 pb-16 max-w-6xl mx-auto px-6">` opening and the matching `</main></div>` closing with `PublicLayout`:

```vue
<template>
  <Head>
    <title>Library</title>
  </Head>

  <PublicLayout>
    <div class="pt-6 pb-16 max-w-6xl mx-auto px-6">
      <h1 class="text-3xl font-bold text-slate-800 mb-8">Library</h1>

      <div v-if="books.data.length === 0" class="text-sm text-slate-400 py-12 text-center">
        No books available yet.
      </div>

      <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-6">
        <Link v-for="book in books.data" :key="book.slug" :href="`/library/books/${book.slug}`" class="group">
          <div class="aspect-[2/3] rounded-lg overflow-hidden bg-slate-100 mb-2 shadow-sm group-hover:shadow-md transition-shadow">
            <img v-if="book.cover_url" :src="book.cover_url" class="w-full h-full object-cover" />
          </div>
          <p class="text-sm font-medium text-slate-800 line-clamp-2" :title="book.title">{{ book.title }}</p>
          <p v-if="book.author" class="text-xs text-slate-400 mt-0.5 truncate">{{ book.author }}</p>
        </Link>
      </div>

      <div v-if="books.last_page > 1" class="flex flex-wrap gap-1 mt-10 justify-center">
        <Link
          v-for="(link, i) in books.links"
          :key="i"
          :href="link.url ?? '#'"
          v-html="link.label"
          class="px-3 py-1.5 text-sm rounded-lg"
          :class="link.active ? 'bg-orange-500 text-white' : link.url ? 'text-slate-600 hover:bg-slate-100' : 'text-slate-300 pointer-events-none'"
        />
      </div>
    </div>
  </PublicLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PublicLayout from '@/Layouts/PublicLayout.vue'

defineProps({ books: { type: Object, required: true } })
</script>
```

- [ ] **Step 2: `AuthorShow.vue`** — same pattern:

```vue
<template>
  <Head>
    <title>{{ author.name }}</title>
  </Head>

  <PublicLayout>
    <div class="pt-6 pb-16 max-w-6xl mx-auto px-6">
      <h1 class="text-2xl font-bold text-slate-800 mb-2">{{ author.name }}</h1>
      <p v-if="author.bio" class="text-sm text-slate-500 mb-8 max-w-2xl">{{ author.bio }}</p>

      <div v-if="books.data.length === 0" class="text-sm text-slate-400 py-12 text-center">
        No books by this author yet.
      </div>

      <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-6">
        <Link v-for="book in books.data" :key="book.slug" :href="`/library/books/${book.slug}`" class="group">
          <div class="aspect-[2/3] rounded-lg overflow-hidden bg-slate-100 mb-2 shadow-sm group-hover:shadow-md transition-shadow">
            <img v-if="book.cover_url" :src="book.cover_url" class="w-full h-full object-cover" />
          </div>
          <p class="text-sm font-medium text-slate-800 line-clamp-2" :title="book.title">{{ book.title }}</p>
        </Link>
      </div>

      <div v-if="books.last_page > 1" class="flex flex-wrap gap-1 mt-10 justify-center">
        <Link
          v-for="(link, i) in books.links"
          :key="i"
          :href="link.url ?? '#'"
          v-html="link.label"
          class="px-3 py-1.5 text-sm rounded-lg"
          :class="link.active ? 'bg-orange-500 text-white' : link.url ? 'text-slate-600 hover:bg-slate-100' : 'text-slate-300 pointer-events-none'"
        />
      </div>
    </div>
  </PublicLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PublicLayout from '@/Layouts/PublicLayout.vue'

defineProps({
  author: { type: Object, required: true },
  books: { type: Object, required: true },
})
</script>
```

- [ ] **Step 3: `BookDetail.vue`** — same layout swap, AND change the chapter links to open in a new tab (this is the entry point into `ChapterReader.vue`, which per the reading-modes plan is meant to open Drive-style in its own tab):

```vue
<template>
  <Head>
    <title>{{ book.title }}</title>
    <meta name="description" :content="book.description ?? book.title" />
  </Head>

  <PublicLayout>
    <div class="pt-6 pb-16 max-w-4xl mx-auto px-6">
      <div class="flex flex-col sm:flex-row gap-8 mb-10">
        <div class="w-40 flex-shrink-0 mx-auto sm:mx-0">
          <div class="aspect-[2/3] rounded-lg overflow-hidden bg-slate-100 shadow-sm">
            <img v-if="book.cover_url" :src="book.cover_url" class="w-full h-full object-cover" />
          </div>
        </div>
        <div class="flex-1">
          <h1 class="text-2xl font-bold text-slate-800 mb-2">{{ book.title }}</h1>
          <a v-if="book.author" :href="`/library/authors/${book.author.slug}`" class="text-sm text-orange-500 hover:underline">
            {{ book.author.name }}
          </a>
          <p v-if="book.description" class="text-sm text-slate-600 leading-relaxed mt-4">{{ book.description }}</p>
          <dl class="mt-4 space-y-1 text-xs text-slate-400">
            <div v-if="book.publisher"><dt class="inline font-medium">Publisher:</dt> <dd class="inline">{{ book.publisher }}</dd></div>
            <div v-if="book.published_date"><dt class="inline font-medium">Published:</dt> <dd class="inline">{{ book.published_date }}</dd></div>
            <div v-if="book.language"><dt class="inline font-medium">Language:</dt> <dd class="inline">{{ book.language }}</dd></div>
          </dl>
        </div>
      </div>

      <h2 class="text-lg font-semibold text-slate-800 mb-3">Chapters</h2>
      <ol class="divide-y divide-slate-100 border border-slate-100 rounded-xl overflow-hidden">
        <li v-for="chapter in book.chapters" :key="chapter.sort_order">
          <a
            :href="`/library/books/${book.slug}/chapters/${chapter.sort_order}`"
            target="_blank" rel="noopener"
            class="flex items-center justify-between px-4 py-3 text-sm hover:bg-slate-50 transition-colors"
          >
            <span class="line-clamp-1" :title="chapter.title ?? `Chapter ${chapter.sort_order + 1}`">
              {{ chapter.title ?? `Chapter ${chapter.sort_order + 1}` }}
            </span>
            <span class="text-slate-300">›</span>
          </a>
        </li>
      </ol>
    </div>
  </PublicLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import PublicLayout from '@/Layouts/PublicLayout.vue'

defineProps({ book: { type: Object, required: true } })
</script>
```

Note `Link` is no longer imported/used in `BookDetail.vue` since the chapter list now uses a plain `<a target="_blank">` instead of Inertia's `<Link>` (Inertia's `<Link>` doesn't support opening in a new tab the way a plain anchor with `target="_blank"` does).

- [ ] **Step 4: Manually verify**

Run: `npm run build`. Visit `/library`, `/library/authors/<slug>`, and `/library/books/<slug>`. Expected: all three show the shared `PublicNav`/`PublicFooter`; clicking a chapter in `BookDetail.vue` opens `ChapterReader.vue` in a new browser tab while the `BookDetail.vue` tab stays open and unchanged.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Public/Library/Index.vue resources/js/Pages/Public/Library/AuthorShow.vue resources/js/Pages/Public/Library/BookDetail.vue
git commit -m "feat: wire library pages to PublicLayout, open chapters in a new tab"
```

---

### Task 8: Wire `ProjectDetail.vue` to `PublicLayout`

**Files:**
- Modify: `resources/js/Pages/Public/ProjectDetail.vue`

- [ ] **Step 1: Replace the nav wrapper**

Replace:

```vue
  <div class="min-h-screen bg-white font-sans">
    <!-- Nav -->
    <nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl bg-white/80 border-b border-slate-100">
      <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
        <a :href="`/portfolio/${owner.username}`" class="flex items-center gap-2 text-sm text-slate-500 hover:text-orange-500 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
          Back to Portfolio
        </a>
        <span class="text-sm font-semibold text-slate-800">{{ owner.name }}</span>
      </div>
    </nav>

    <main class="pt-14">
```

with:

```vue
  <PublicLayout>
    <div class="max-w-6xl mx-auto px-6 pt-4">
      <a :href="`/portfolio/${owner.username}`" class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-orange-500 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to Portfolio
      </a>
    </div>
```

(the "Back to Portfolio" link becomes page content inside the layout's slot, above the hero section, instead of living inside a competing nav bar — the `{{ owner.name }}` label that used to sit opposite it is dropped since `PublicNav`'s brand area already identifies the site.)

Replace the closing:

```vue
    </main>

    <!-- Lightbox -->
```

with:

```vue
    <!-- Lightbox -->
```

and replace the file's final two closing tags:

```vue
  </div>
</template>
```

with:

```vue
  </PublicLayout>
</template>
```

Note the `<!-- Hero -->` section and everything after it (Gallery, Details, Footer back link, Lightbox `<Teleport>`) stay exactly as they are — only the opening nav/main wrapper and the final closing tags change. The existing "Footer back link" section (the `<div class="py-12 px-6 border-t ...">` with "Back to {{ owner.name }}'s Portfolio") is left untouched — it is page content (a second, bottom-of-page CTA back to the portfolio), not the site's global footer, and stays in addition to `PublicLayout`'s new `PublicFooter`.

- [ ] **Step 2: Update the script block**

Add to the imports:

```js
import PublicLayout from '@/Layouts/PublicLayout.vue'
```

- [ ] **Step 3: Manually verify**

Run: `npm run build`, visit a project detail page. Expected: `PublicNav` appears at the top, then the "Back to Portfolio" link, then the existing dark hero section and the rest of the page unchanged, then the existing bottom "Back to {{ owner.name }}'s Portfolio" link, then `PublicFooter`.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Public/ProjectDetail.vue
git commit -m "feat: wire ProjectDetail.vue to PublicLayout"
```

---

### Task 9: Wire `OrgPage.vue` conditionally on `white_label`

**Files:**
- Modify: `resources/js/Pages/Public/OrgPage.vue`

- [ ] **Step 1: Replace the nav/footer with conditional shared components**

Replace:

```vue
    <nav class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
      <a href="/" class="text-sm font-bold text-slate-800 hover:text-orange-500 transition-colors">
        {{ organization.white_label && organization.custom_brand_name ? organization.custom_brand_name : settings.site_name }}
      </a>
    </nav>
```

with:

```vue
    <PublicNav v-if="!organization.white_label" />
    <nav v-else class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
      <a href="/" class="text-sm font-bold text-slate-800 hover:text-orange-500 transition-colors">
        {{ organization.custom_brand_name || settings.site_name }}
      </a>
    </nav>
```

Replace:

```vue
      <!-- Footer (hidden if white-label) -->
      <footer v-if="!organization.white_label" class="mt-16 pt-8 border-t border-slate-100 text-center text-xs text-slate-400">
        Powered by <a href="/" class="hover:text-orange-500">{{ settings.site_name }}</a>
      </footer>
```

with:

```vue
      <!-- Shared footer (hidden if white-label; the "Powered by" line is white-label's own, more minimal, alternative) -->
      <PublicFooter v-if="!organization.white_label" />
```

- [ ] **Step 2: Update the script block**

```js
import { Head } from '@inertiajs/vue3'
import PublicNav from '@/Components/Public/PublicNav.vue'
import PublicFooter from '@/Components/Public/PublicFooter.vue'

defineProps({
  organization: Object,
  owner:        Object,
  members:      Array,
  achievements: Array,
  settings:     Object,
})
```

(`PublicNav`/`PublicFooter` read `site_name` from the globally-shared `settings` prop via `usePage()` internally from Task 1 — the `settings` prop still declared here is the page's own pre-existing `tenantSettings()`-sourced prop, used for the white-label branch's own inline text, which is unaffected.)

- [ ] **Step 3: Manually verify**

Run: `npm run build`. Visit a non-white-labeled organization page — expected: shows the shared `PublicNav`/`PublicFooter`. Then (using an org with `white_label` true, or by temporarily toggling that flag on a test org via `php artisan tinker`) visit a white-labeled organization page — expected: still shows only the minimal brand-only nav with no `Library` link, and no footer at all, exactly as before this change.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Public/OrgPage.vue
git commit -m "feat: wire OrgPage.vue to shared nav/footer, excluding white-labeled orgs"
```

---

### Task 10: Add a `Library` link to `Portfolio.vue`'s nav, dedup its auth CTA and footer

**Files:**
- Modify: `resources/js/Pages/Public/Portfolio.vue`

- [ ] **Step 1: Add the `Library` link and swap in `AuthNavCta`**

Replace:

```vue
        <div class="flex items-center gap-3">
          <template v-if="isLoggedIn">
            <a v-if="isAdmin" href="/admin/dashboard"
              class="text-sm px-4 py-1.5 rounded-lg bg-slate-900 text-white hover:bg-slate-700 transition-colors duration-200">
              Dashboard →
            </a>
          </template>
          <template v-else>
            <a href="/login"
              class="text-sm px-4 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors duration-200">
              Login
            </a>
            <a href="/register"
              class="text-sm px-4 py-1.5 rounded-lg bg-orange-500 text-white hover:bg-orange-600 transition-colors duration-200">
              Sign up
            </a>
          </template>
        </div>
```

with:

```vue
        <div class="flex items-center gap-3">
          <a href="/library" class="px-3 py-1.5 text-sm font-medium text-slate-600 hover:text-orange-500 transition-colors duration-200 rounded-lg hover:bg-orange-50">
            Library
          </a>
          <AuthNavCta />
        </div>
```

- [ ] **Step 2: Swap the footer for `PublicFooter`**

Replace:

```vue
    <!-- Footer -->
    <footer class="border-t border-slate-100 py-10 text-center">
      <img src="/images/ksnsk-logo.png" alt="KSNSK" class="h-8 mx-auto mb-4 opacity-60" />
      <p class="text-xs text-slate-400 mb-3">{{ settings.site_name || owner.name }}</p>
      <p class="text-xs text-slate-300">© {{ new Date().getFullYear() }} · Built with Laravel &amp; Vue</p>
      <div class="flex items-center justify-center gap-4 mt-3">
        <a href="/terms" class="text-xs text-slate-400 hover:text-slate-600 transition-colors">Terms</a>
        <span class="text-slate-200">·</span>
        <a href="/privacy" class="text-xs text-slate-400 hover:text-slate-600 transition-colors">Privacy</a>
        <span class="text-slate-200">·</span>
        <a href="/refund" class="text-xs text-slate-400 hover:text-slate-600 transition-colors">Refund Policy</a>
      </div>
      <a href="/donate" class="inline-flex items-center gap-1.5 text-xs text-pink-400 hover:text-pink-600 transition-colors mt-3">
        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
        Support my work
      </a>
    </footer>
```

with:

```vue
    <PublicFooter />
```

- [ ] **Step 3: Update the script block and remove now-unused code**

Add to the imports:

```js
import AuthNavCta from '@/Components/Public/AuthNavCta.vue'
import PublicFooter from '@/Components/Public/PublicFooter.vue'
```

Remove the now-unused `isLoggedIn` computed (it was only referenced by the nav markup just replaced) — delete this line:

```js
const isLoggedIn = computed(() => !!pageProps.auth?.user)
```

`isAdmin` stays — it's still referenced elsewhere in the file (e.g. any admin-only affordances outside the nav, if present; if a search shows `isAdmin` is now unused too, remove it as well).

- [ ] **Step 4: Manually verify**

Run: `npm run build`, visit the portfolio page. Expected: the existing anchor nav (Home/About/Skills/Projects/Experience/Contact), scroll-spy highlighting, and all animations work exactly as before; a `Library` link now sits in the same nav bar; the footer looks the same as before (now rendered by the shared `PublicFooter` component instead of inline markup).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Public/Portfolio.vue
git commit -m "feat: add Library link to Portfolio.vue nav, dedup auth CTA and footer"
```

---

### Task 11: Full verification pass

**Files:** none (verification only)

- [ ] **Step 1: Run the backend test suite**

Run: `php artisan test`
Expected: all tests pass, including the new Task 1 test.

- [ ] **Step 2: Run the JS test suite**

Run: `npm run test:unit`
Expected: all tests pass, including the 4 new suites from Tasks 2–5.

- [ ] **Step 3: Run the full build**

Run: `npm run build`
Expected: no errors.

- [ ] **Step 4: Manual browser checklist**

- `Library` link appears and works from `Home.vue`, `Portfolio.vue`, a non-white-labeled `OrgPage.vue`, `ProjectDetail.vue`, and all three Library browsing pages.
- A white-labeled org page shows no shared nav, no `Library` link, no shared footer — identical to before this change.
- `Portfolio.vue`'s anchor nav, scroll-spy active-section highlighting, and animations all still work exactly as before.
- `PublicNav` and `PublicFooter` render correctly and responsively at mobile (375px) and desktop (1280px) widths.
- Clicking a chapter from `BookDetail.vue` opens `ChapterReader.vue` in a new tab; the original `BookDetail.vue` tab remains where it was, unnavigated.
- Within the newly opened reader tab, Previous/Next links (and, once the reading-modes plan is implemented, page-flip/autoscroll auto-advance) navigate within that same tab, not opening further new tabs.

- [ ] **Step 5: Commit any fixes found during manual verification. This plan is then complete.**
