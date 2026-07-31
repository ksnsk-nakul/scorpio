# Template System Architecture & Stripe-like Admin Template Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Supersedes:** `docs/superpowers/plans/2026-07-31-public-layout-componentization.md` (committed at `e29f38a`), which was written against an earlier, smaller version of the spec (chrome-only nav/footer, new-tab chapters). That plan is stale and must not be executed. This plan and its sequels (Minimalist and Anime.js public-template plans, written separately once this plan's interfaces are locked) implement the current spec: `docs/superpowers/specs/2026-07-31-public-layout-componentization-design.md`.

**Goal:** Build the settings-driven template-switching architecture (registry + composable + Setting-backed switch), extract the template-agnostic shared components, and restyle the admin panel into the Stripe-like template. This is the foundation the Minimalist and Anime.js public-template plans build on top of.

**Architecture:** No new database table — reuse the existing `Setting` key/value model. A small hardcoded JS registry maps a template key to its component set; a `useActiveTemplate()` composable resolves the active one from an Inertia-shared prop. `AdminLayout.vue` is restyled in place (same data contract: nav array, roles, announcements, demo banner) rather than replaced by a new file, since Admin/Owner/Editor only ever have the one template.

**Tech Stack:** Laravel 13 (Setting model, Inertia middleware, Pest), Vue 3 + Tailwind v4 (Composition API, Vitest). TailAdmin Vue (`~/Downloads/vue-tailwind-admin-dashboard-main.zip`, already confirmed Vue 3 + Tailwind CSS 4) is the structural reference for the admin restyle.

---

### Task 1: Add the `layout_template.public` setting

**Files:**
- Modify: `database/seeders/SettingSeeder.php`
- Modify: `app/Http/Controllers/Admin/SettingController.php:20` (the `groups` array)

- [ ] **Step 1: Add the new setting row to the seeder**

In `database/seeders/SettingSeeder.php`, add a new `appearance` group entry to the `$defaults` array (after the `show_donate_banner` line):

```php
            ['key' => 'show_donate_banner', 'value' => '0', 'group' => 'general'],

            // Appearance
            ['key' => 'layout_template_public', 'value' => 'minimalist', 'group' => 'appearance'],
```

(Using `layout_template_public` with an underscore, not a dot — every other key in this file uses underscores, and the generic Settings UI's `formatKey()` helper does `k.replace(/_/g, ' ')`, which would mangle a dot-separated key.)

- [ ] **Step 2: Run the seeder**

```bash
php artisan db:seed --class=SettingSeeder
```

Expected: no errors. `firstOrCreate` means this is safe to re-run.

- [ ] **Step 3: Verify the row exists**

```bash
php artisan tinker --execute="echo \App\Models\Setting::get('layout_template_public');"
```

Expected output: `minimalist`

- [ ] **Step 4: Register the new settings group**

In `app/Http/Controllers/Admin/SettingController.php`, change:

```php
'groups'   => ['general', 'seo', 'social', 'mail'],
```

to:

```php
'groups'   => ['general', 'seo', 'social', 'mail', 'appearance'],
```

- [ ] **Step 5: Commit**

```bash
git add database/seeders/SettingSeeder.php app/Http/Controllers/Admin/SettingController.php
git commit -m "feat: add layout_template_public setting for the template system"
```

---

### Task 2: Render `layout_template_public` as a dropdown in the Settings UI

**Files:**
- Modify: `resources/js/Pages/Admin/Settings/Index.vue`

The Settings page already renders every key in the active group generically as a text input, with two special-cased key lists (`boolKeys`, `imageKeys`) for other input types. This adds a third: a `selectKeys` map for dropdown-rendered keys.

- [ ] **Step 1: Add a `selectKeys` map and a `isSelectKey`/`selectOptions` helper**

In the `<script setup>` block of `resources/js/Pages/Admin/Settings/Index.vue`, after the existing `imageKeys` line, add:

```js
const selectKeys = {
  layout_template_public: [
    { value: 'minimalist', label: 'Minimalist' },
    { value: 'animejs', label: 'Anime.js' },
  ],
}
const isSelectKey = k => Object.prototype.hasOwnProperty.call(selectKeys, k)
const selectOptions = k => selectKeys[k] ?? []
```

- [ ] **Step 2: Add a select-rendering branch in the template**

In the `<template>`, insert a new `<template v-else-if="isSelectKey(...)">` branch before the final `<template v-else>` (plain text input) branch:

```html
          <template v-else-if="isSelectKey(String(key))">
            <label class="block text-sm text-slate-600 mb-1">{{ formatKey(String(key)) }}</label>
            <select v-model="form[key]"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500">
              <option v-for="opt in selectOptions(String(key))" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </template>
```

- [ ] **Step 3: Manual verification**

Start the dev server, log in as admin, go to `/admin/settings`, click the new "Appearance" tab, confirm "Layout Template Public" renders as a dropdown with "Minimalist"/"Anime.js" options, change it, save, reload, confirm the selection persisted.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Admin/Settings/Index.vue
git commit -m "feat: render layout_template_public as a dropdown in admin settings"
```

---

### Task 3: Share the active public template via Inertia

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Test: `tests/Feature/LayoutTemplateSharedPropTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Setting;

it('shares the active public layout template with every Inertia response', function () {
    Setting::where('key', 'layout_template_public')->update(['value' => 'animejs']);

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->where('layoutTemplates.public', 'animejs')
        ->where('layoutTemplates.admin', 'stripe')
    );
});
```

- [ ] **Step 2: Run it to verify it fails**

```bash
php artisan test --filter=LayoutTemplateSharedPropTest
```

Expected: FAIL — `layoutTemplates` prop not present.

- [ ] **Step 3: Add the shared prop**

In `app/Http/Middleware/HandleInertiaRequests.php`, add `use App\Models\Setting;` to the imports, and inside the array returned by `share()`, after the existing `'demo' => ...` entry, add:

```php
            'layoutTemplates' => [
                'admin'  => 'stripe',
                'public' => Setting::get('layout_template_public', 'minimalist'),
            ],
```

- [ ] **Step 4: Run the test again**

```bash
php artisan test --filter=LayoutTemplateSharedPropTest
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php tests/Feature/LayoutTemplateSharedPropTest.php
git commit -m "feat: share the active public layout template via Inertia"
```

---

### Task 4: `useActiveTemplate` composable

**Files:**
- Create: `resources/js/composables/useActiveTemplate.js`
- Test: `tests/js/useActiveTemplate.test.js`

- [ ] **Step 1: Write the failing test**

```js
import { describe, it, expect, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
  usePage: () => ({ props: { layoutTemplates: { admin: 'stripe', public: 'animejs' } } }),
}))

describe('useActiveTemplate', () => {
  it('resolves the admin template key', async () => {
    const { useActiveTemplate } = await import('@/composables/useActiveTemplate')
    const { adminTemplate } = useActiveTemplate()
    expect(adminTemplate.value).toBe('stripe')
  })

  it('resolves the public template key', async () => {
    const { useActiveTemplate } = await import('@/composables/useActiveTemplate')
    const { publicTemplate } = useActiveTemplate()
    expect(publicTemplate.value).toBe('animejs')
  })
})
```

- [ ] **Step 2: Run it to verify it fails**

```bash
npx vitest run tests/js/useActiveTemplate.test.js
```

Expected: FAIL — module not found.

- [ ] **Step 3: Implement the composable**

```js
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useActiveTemplate() {
  const page = usePage()

  const adminTemplate = computed(() => page.props.layoutTemplates?.admin ?? 'stripe')
  const publicTemplate = computed(() => page.props.layoutTemplates?.public ?? 'minimalist')

  return { adminTemplate, publicTemplate }
}
```

- [ ] **Step 4: Run the test again**

```bash
npx vitest run tests/js/useActiveTemplate.test.js
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/js/composables/useActiveTemplate.js tests/js/useActiveTemplate.test.js
git commit -m "feat: add useActiveTemplate composable"
```

---

### Task 5: Public-template component registry (scaffold only)

**Files:**
- Create: `resources/js/templateRegistry.js`
- Test: `tests/js/templateRegistry.test.js`

This scaffolds the registry with empty placeholders for the two public templates' pages — the Minimalist and Anime.js plans fill these in. Creating it now (rather than in a later plan) means `useActiveTemplate` consumers written in this plan have a real, importable registry to reference, even though most entries resolve to `null` until the sequel plans land.

- [ ] **Step 1: Write the failing test**

```js
import { describe, it, expect } from 'vitest'
import { resolvePublicPage } from '@/templateRegistry'

describe('templateRegistry', () => {
  it('returns null for a page not yet implemented by a template', () => {
    expect(resolvePublicPage('minimalist', 'Home')).toBeNull()
  })

  it('throws for an unknown template key', () => {
    expect(() => resolvePublicPage('does-not-exist', 'Home')).toThrow(/unknown template/i)
  })
})
```

- [ ] **Step 2: Run it to verify it fails**

```bash
npx vitest run tests/js/templateRegistry.test.js
```

Expected: FAIL — module not found.

- [ ] **Step 3: Implement the registry**

```js
// Registry of public-template page implementations. Each template's page
// components are added by the plan that builds that template — see
// docs/superpowers/specs/2026-07-31-public-layout-componentization-design.md.
const PUBLIC_TEMPLATES = {
  minimalist: {
    Home: null,
    Portfolio: null,
    OrgPage: null,
    ProjectDetail: null,
    LibraryIndex: null,
    BookDetail: null,
    AuthorShow: null,
  },
  animejs: {
    Home: null,
    Portfolio: null,
    OrgPage: null,
    ProjectDetail: null,
    LibraryIndex: null,
    BookDetail: null,
    AuthorShow: null,
  },
}

export function resolvePublicPage(templateKey, pageName) {
  const template = PUBLIC_TEMPLATES[templateKey]
  if (!template) {
    throw new Error(`resolvePublicPage: unknown template "${templateKey}"`)
  }
  return template[pageName] ?? null
}
```

- [ ] **Step 4: Run the test again**

```bash
npx vitest run tests/js/templateRegistry.test.js
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/js/templateRegistry.js tests/js/templateRegistry.test.js
git commit -m "feat: scaffold the public-template component registry"
```

---

### Task 6: `BackLink` shared component

**Files:**
- Create: `resources/js/Components/Shared/BackLink.vue`

This extracts the "← X" pattern already duplicated across `ProjectDetail.vue`, `Library/Index.vue`, `Library/BookDetail.vue`, `Library/AuthorShow.vue`, `Privacy.vue`, `Terms.vue`, and `Refund.vue`. Not wired into those pages yet in this plan — that happens when each page is rebuilt under a template (public pages) or in Task 8 below (legal pages, which are template-agnostic).

- [ ] **Step 1: Create the component**

```vue
<template>
  <Link :href="href" class="text-sm text-slate-500 hover:text-orange-500 transition-colors">
    ← {{ label }}
  </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  href: { type: String, required: true },
  label: { type: String, required: true },
})
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Components/Shared/BackLink.vue
git commit -m "feat: add shared BackLink component"
```

---

### Task 7: `EmptyState` and `Pagination` shared components

**Files:**
- Create: `resources/js/Components/Shared/EmptyState.vue`
- Create: `resources/js/Components/Shared/Pagination.vue`

- [ ] **Step 1: Create `EmptyState.vue`**

```vue
<template>
  <div class="text-slate-400 py-12 text-center">
    {{ message }}
  </div>
</template>

<script setup>
defineProps({
  message: { type: String, default: 'Nothing here yet.' },
})
</script>
```

- [ ] **Step 2: Create `Pagination.vue`**

Based on the identical pagination markup currently duplicated in `Library/Index.vue` and `Library/AuthorShow.vue` (Laravel paginator `links` array: `{url, label, active}`).

```vue
<template>
  <nav v-if="links.length > 3" class="flex items-center gap-1 justify-center mt-8">
    <Link
      v-for="(link, i) in links"
      :key="i"
      :href="link.url ?? '#'"
      :class="[
        'px-3 py-1.5 text-sm rounded-lg transition-colors',
        link.active ? 'bg-orange-500 text-white' : 'text-slate-600 hover:bg-slate-100',
        !link.url ? 'pointer-events-none opacity-40' : '',
      ]"
      v-html="link.label"
    />
  </nav>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  links: { type: Array, required: true },
})
</script>
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/Shared/EmptyState.vue resources/js/Components/Shared/Pagination.vue
git commit -m "feat: add shared EmptyState and Pagination components"
```

---

### Task 8: `LegalPage` shared layout, wired to Privacy/Terms/Refund

**Files:**
- Create: `resources/js/Components/Shared/LegalPage.vue`
- Modify: `resources/js/Pages/Public/Privacy.vue`
- Modify: `resources/js/Pages/Public/Terms.vue`
- Modify: `resources/js/Pages/Public/Refund.vue`

These three pages are explicitly out of the per-template re-skin (spec: "Explicitly excluded from per-template re-skinning"), so they get one shared layout regardless of which public template is active.

- [ ] **Step 1: Read the three existing pages to confirm their shared shape**

```bash
head -40 resources/js/Pages/Public/Privacy.vue resources/js/Pages/Public/Terms.vue resources/js/Pages/Public/Refund.vue
```

Confirm each uses the same `border-b border-slate-100 px-6 py-4` nav with a single `← {{site_name}}` link, and the same `prose prose-slate max-w-none space-y-8` / `max-w-3xl mx-auto px-6 py-16` body structure, differing only in their numbered `<section>` content and "Last updated" date.

- [ ] **Step 2: Create `LegalPage.vue`**

```vue
<template>
  <div class="min-h-screen bg-white text-slate-900">
    <nav class="border-b border-slate-100 px-6 py-4">
      <BackLink href="/" :label="siteName" />
    </nav>
    <main class="max-w-3xl mx-auto px-6 py-16">
      <h1 class="text-3xl font-bold text-slate-900 mb-2">{{ title }}</h1>
      <p class="text-sm text-slate-400 mb-10">Last updated {{ lastUpdated }}</p>
      <div class="prose prose-slate max-w-none space-y-8">
        <slot />
      </div>
    </main>
  </div>
</template>

<script setup>
import BackLink from '@/Components/Shared/BackLink.vue'

defineProps({
  siteName: { type: String, required: true },
  title: { type: String, required: true },
  lastUpdated: { type: String, required: true },
})
</script>
```

- [ ] **Step 3: Wire each of the three pages to use it**

For each of `Privacy.vue`, `Terms.vue`, `Refund.vue`: replace the page's own `<nav>` and outer wrapper markup with `<LegalPage :site-name="settings.site_name" title="..." last-updated="...">`, keeping each page's existing numbered `<section>` content as the slot content, unchanged. (Exact title/last-updated values: read each file's current `<h1>` and "Last updated" text and carry them over verbatim — do not invent new copy.)

- [ ] **Step 4: Manual verification**

Visit `/privacy`, `/terms`, `/refund`. Confirm each renders identically to before (same content, same "← {{site_name}}" back link, same layout) — this step is a pure refactor, zero visible change expected.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/Shared/LegalPage.vue resources/js/Pages/Public/Privacy.vue resources/js/Pages/Public/Terms.vue resources/js/Pages/Public/Refund.vue
git commit -m "refactor: extract shared LegalPage layout for Privacy/Terms/Refund"
```

---

### Task 9: Restyle `AdminLayout.vue` into the Stripe-like template

**Files:**
- Modify: `resources/js/Layouts/AdminLayout.vue`
- Reference: `~/Downloads/vue-tailwind-admin-dashboard-main.zip` (TailAdmin Vue — structural reference for sidebar/topbar patterns)
- Reference: `~/Downloads/stitch_vaigrantha.zip` → `author_dashboard_desktop_dark/screen.png` and `code.html` (visual reference, generated against this app's own brand)

This restyles the admin shell in place. The data contract stays identical — same `nav` array (still filtered by `userRoles`), same `isActive`, same `announcements`/`demo` props, same logout link — only the visual structure and Tailwind classes change.

- [ ] **Step 1: Extract the TailAdmin reference and inspect its sidebar/topbar structure**

```bash
mkdir -p /tmp/tailadmin-ref && unzip -q ~/Downloads/vue-tailwind-admin-dashboard-main.zip -d /tmp/tailadmin-ref
find /tmp/tailadmin-ref -iname "*sidebar*" -o -iname "*appshell*" -o -iname "*header*" | grep -v node_modules
```

Read the matched files to identify TailAdmin's sidebar collapse/icon pattern, spacing scale, and color tokens (it uses a dark sidebar + light content area, similar shape to the current `AdminLayout.vue` but with icons, section grouping, and a topbar instead of a bare nav list).

- [ ] **Step 2: Open the Stitch reference screenshot**

```bash
mkdir -p /tmp/stitch-ref && unzip -q ~/Downloads/stitch_vaigrantha.zip -d /tmp/stitch-ref
open /tmp/stitch-ref/stitch_vaigrantha/author_dashboard_desktop_dark/screen.png
```

Use this as the target visual style (colors, spacing, typography) — it was generated specifically against this app's branding, so it takes priority over TailAdmin's own default color scheme when the two disagree.

- [ ] **Step 3: Restyle the template**

Rebuild `AdminLayout.vue`'s `<template>` block: add per-item icons (simple inline SVG or a small icon map, matching TailAdmin's icon-plus-label sidebar item pattern), group nav items into labeled sections if the Stitch reference does so, add a topbar (breadcrumb/page title + user menu) above the `<slot />` instead of the current bare `<div class="p-6">`. Preserve exactly: the `v-for="item in nav"`, `isActive(item.href)` highlighting logic, the demo-mode banner, `<AnnouncementBanner>`/`<AnnouncementModal>`, and the logout `<Link method="post">`. Do not change `<script setup>` except to add any new icon-lookup helper — `nav`, `isActive`, `userRoles`, `isAdmin`/`isEditor`/`isViewer` stay as-is.

- [ ] **Step 4: Manual verification**

Log in as admin (`admin@example.com` / `password` per `.env`), visit `/admin/dashboard`. Confirm: sidebar nav still lists all expected items and highlights the active route, logout still works, demo-mode banner still shows (since `.env` has `DEMO_MODE=true`), announcements still render. Then log in as an editor/viewer-role user if one exists in seed data and confirm role-gated nav items (Users, Integrations, Settings, Announcements) are correctly hidden.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Layouts/AdminLayout.vue
git commit -m "feat: restyle AdminLayout into the Stripe-like template"
```

---

### Task 10: Full verification pass

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

```bash
php artisan test
npx vitest run
```

Expected: all green, including the new `LayoutTemplateSharedPropTest`, `useActiveTemplate.test.js`, and `templateRegistry.test.js`.

- [ ] **Step 2: Run the frontend build**

```bash
npm run build
```

Expected: no errors.

- [ ] **Step 3: Manual checklist**

- `/admin/settings` → Appearance tab shows the Layout Template dropdown, changes persist.
- Every `/admin/*` page still renders correctly under the restyled `AdminLayout` (spot-check Dashboard, Pages, Library, Settings, Users).
- `/privacy`, `/terms`, `/refund` render identically to before Task 8's refactor.
- `resolvePublicPage('minimalist', 'Home')` and `resolvePublicPage('animejs', 'Home')` both still return `null` (expected — filled in by the sequel plans), and nothing in the app currently calls this registry yet, so nothing breaks from the `null`s.

- [ ] **Step 4: Commit any final fixes**

If manual verification surfaces issues, fix and commit them individually with descriptive messages before considering this plan complete.
