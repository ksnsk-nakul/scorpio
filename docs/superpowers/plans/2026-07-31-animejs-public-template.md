# Anime.js Public Template Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the `animejs` public template — full re-skins of all 7 in-scope public pages (Home, Portfolio, OrgPage, ProjectDetail, Library Index/BookDetail/AuthorShow), each wired into the template registry built in `docs/superpowers/plans/2026-07-31-template-system-architecture-and-admin.md`, switchable live from the already-built Admin Settings "Public Site Template" dropdown. Per explicit direction: build Anime.js before Minimalist, keep Portfolio.vue's existing dynamic behaviors (scroll-spy, rotating hero text, scroll-reveal) as the baseline rather than dropping them, and use anime.js's animation/easing capabilities generously across all 7 pages for a smooth, polished feel — not just a light touch on one page.

**Architecture:** Each page gets a real implementation at `resources/js/Pages/Public/Templates/Animejs/<Name>.vue`, registered in `resources/js/templateRegistry.js`. The existing `resources/js/Pages/Public/*.vue` files become thin resolvers: when `useActiveTemplate().publicTemplate === 'animejs'` AND the registry has a non-null entry, render the Animejs implementation via `<component :is>`; otherwise, fall back to rendering exactly what the page renders today (its current markup, unchanged). This means nothing breaks and nothing visually changes until an admin explicitly switches the setting to `animejs` — the default stays `minimalist` (currently `null` in the registry, so it always falls through to today's markup regardless of this plan).

**Tech Stack:** Vue 3 (Composition API) + Inertia.js v2 + Tailwind v4, [anime.js v4.5.0](https://animejs.com) (new dependency — v4's API is `import { animate, stagger, createTimeline } from 'animejs'`, note the v3→v4 breaking changes: named exports not a default export, `ease` not `easing`, targets are the first argument to `animate()` not a property inside the config). Structural/visual reference: `~/Downloads/vuejs-tailwindcss-portfolio-main.zip` (Vue Router-based portfolio template — router and demo data must be stripped, not reused) and the two `stitch_vaigrantha` Stitch export zips (`~/Downloads/stitch_vaigrantha.zip`, `~/Downloads/stitch_vaigrantha (1).zip` — orange-theme screens especially, e.g. `platform_homepage_orange_theme`, `library_discovery_orange_theme`).

---

### Task 1: Add anime.js dependency and the `useAnimeReveal` composable

**Files:**
- Modify: `package.json` (add dependency)
- Create: `resources/js/composables/useAnimeReveal.js`
- Test: `tests/js/useAnimeReveal.test.js`

This is the one shared animation primitive every page in this plan reuses: fade+rise-in reveal on scroll, staggered when multiple elements reveal together (grids, lists). It replaces the class-toggling `.reveal`/`.revealed` + raw `IntersectionObserver` pattern currently hand-rolled in `Portfolio.vue` with an anime.js-powered version with the same trigger mechanism but actual eased motion instead of a CSS class swap.

- [ ] **Step 1: Install anime.js**

```bash
npm install animejs@^4.5.0
```

- [ ] **Step 2: Write the failing test**

```js
import { describe, it, expect, vi, beforeEach } from 'vitest'

const animateMock = vi.fn()
const staggerMock = vi.fn((v) => v)

vi.mock('animejs', () => ({
  animate: animateMock,
  stagger: staggerMock,
}))

class MockIntersectionObserver {
  constructor(callback) {
    this.callback = callback
    this.observed = []
  }
  observe(el) { this.observed.push(el) }
  unobserve(el) { this.observed = this.observed.filter(e => e !== el) }
  disconnect() { this.observed = [] }
  trigger(el) {
    this.callback([{ target: el, isIntersecting: true }])
  }
}

describe('useAnimeReveal', () => {
  let observerInstance

  beforeEach(() => {
    animateMock.mockClear()
    staggerMock.mockClear()
    global.IntersectionObserver = vi.fn((cb) => {
      observerInstance = new MockIntersectionObserver(cb)
      return observerInstance
    })
  })

  it('observes every element matching the selector on mount', async () => {
    const { mount } = await import('@vue/test-utils')
    const { useAnimeReveal } = await import('@/composables/useAnimeReveal')
    const TestComponent = {
      template: '<div><p class="reveal">A</p><p class="reveal">B</p></div>',
      setup() {
        useAnimeReveal('.reveal')
      },
    }
    mount(TestComponent, { attachTo: document.body })
    expect(observerInstance.observed.length).toBe(2)
  })

  it('calls animate() with a fade+rise config when an element intersects', async () => {
    const { mount } = await import('@vue/test-utils')
    const { useAnimeReveal } = await import('@/composables/useAnimeReveal')
    const TestComponent = {
      template: '<div><p class="reveal">A</p></div>',
      setup() {
        useAnimeReveal('.reveal')
      },
    }
    mount(TestComponent, { attachTo: document.body })
    const el = observerInstance.observed[0]
    observerInstance.trigger(el)
    expect(animateMock).toHaveBeenCalledTimes(1)
    const [target, config] = animateMock.mock.calls[0]
    expect(target).toBe(el)
    expect(config.opacity).toEqual([0, 1])
    expect(config.ease).toBeTruthy()
  })
})
```

- [ ] **Step 3: Run it to verify it fails**

```bash
npx vitest run tests/js/useAnimeReveal.test.js
```

Expected: FAIL — module not found.

- [ ] **Step 4: Implement the composable**

```js
import { animate, stagger } from 'animejs'
import { onMounted, onUnmounted } from 'vue'

/**
 * Fades + rises in every element matching `selector` the first time it
 * scrolls into view, then stops observing it (one-shot reveal, not a
 * repeating scroll effect). Elements revealed in the same intersection
 * batch (e.g. a grid rendered at once) get a staggered delay so they don't
 * all animate in lockstep.
 */
export function useAnimeReveal(selector = '.reveal', options = {}) {
  let observer

  onMounted(() => {
    const els = document.querySelectorAll(selector)
    if (!els.length) return

    observer = new IntersectionObserver((entries) => {
      const visible = entries.filter(e => e.isIntersecting)
      visible.forEach((entry, i) => {
        animate(entry.target, {
          opacity: [0, 1],
          translateY: [24, 0],
          duration: 700,
          ease: 'outQuad',
          delay: stagger(80)(i),
        })
        observer.unobserve(entry.target)
      })
    }, { threshold: 0.15, ...options })

    els.forEach(el => observer.observe(el))
  })

  onUnmounted(() => observer?.disconnect())
}
```

- [ ] **Step 5: Run the test again**

```bash
npx vitest run tests/js/useAnimeReveal.test.js
```

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add package.json package-lock.json resources/js/composables/useAnimeReveal.js tests/js/useAnimeReveal.test.js
git commit -m "feat: add anime.js dependency and useAnimeReveal composable"
```

---

### Task 2: `AnimeJsNav` and `AnimeJsFooter` shared components

**Files:**
- Create: `resources/js/Components/Templates/Animejs/AnimeJsNav.vue`
- Create: `resources/js/Components/Templates/Animejs/AnimeJsFooter.vue`
- Reuse: `resources/js/Components/Shared/BackLink.vue` (do not duplicate)

These are shared across all 7 Animejs pages — built once here, imported everywhere else in this plan.

**`AnimeJsNav.vue` requirements:**
- Props: `settings: Object` (for `site_name`), `sections: { type: Array, default: () => [] }` (optional anchor-link list, e.g. `[{id: 'about', label: 'About'}, ...]` — only `Portfolio.vue`'s page passes this; other pages pass `[]` and just get a plain nav with no anchor links).
- Fixed/sticky top nav, backdrop-blur, matching this app's established `bg-white/80 backdrop-blur border-b border-slate-100` convention (see audit of `Home.vue`/`ProjectDetail.vue`/Library pages for the exact established pattern — stay consistent with it, this is a re-skin of chrome, not an invented new design language).
- **Scroll-spy active-section highlighting** when `sections` is non-empty: port the mechanism from the current `Portfolio.vue` (a `scroll` listener computing which section is currently in view, an `activeSection` ref, active-state class on the matching anchor link). This is porting existing logic into a reusable component, not inventing new behavior.
- **Real mobile hamburger menu** — a currently-missing capability app-wide (no page has one today). Tap opens a slide-down panel with the same links as desktop; animate the open/close with `animate()` (height/opacity transition), not just a `v-if` toggle with no motion.
- Auth-aware CTA: reuse the same "Dashboard →" (admin) / nothing (non-admin) logic every current page independently re-derives via `usePage().props.auth.roles` — inline it here rather than creating a separate `AuthNavCta` component (out of scope for this plan; if you find yourself duplicating this logic elsewhere in this plan's other new files, note it in your report rather than building a new shared component unprompted).
- A `Library` link (`/library`) always present, per the original nav-refactor spec's requirement.

**`AnimeJsFooter.vue` requirements:**
- Props: `settings: Object`.
- Site name/tagline, Terms/Privacy/Refund links (`/terms`, `/privacy`, `/refund`), matching content already established in the current `Portfolio.vue` footer (read it for the exact current link set/copy — don't invent new legal links).
- No `white_label`-awareness needed here — `OrgPage.vue`'s Animejs implementation (Task 5) handles that conditionally itself by not rendering this component at all when `organization.white_label` is true, same as today's behavior.

- [ ] **Step 1: Read `resources/js/Pages/Public/Portfolio.vue` in full** to extract the exact current scroll-spy mechanism, nav link set, and footer content/links before building these two components.

- [ ] **Step 2: Build `AnimeJsNav.vue`** per the requirements above, using `useAnimeReveal`'s sibling pattern (direct `animate()` calls, not the reveal composable, since menu open/close is interaction-triggered, not scroll-triggered).

- [ ] **Step 3: Build `AnimeJsFooter.vue`** per the requirements above.

- [ ] **Step 4: Manual verification** — these have no consumers yet (built in Task 3+), so verify by temporarily mounting them in a scratch page or via a quick Vitest smoke test asserting they render without error given minimal props; delete the scratch page before committing if you created one.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/Templates/Animejs/AnimeJsNav.vue resources/js/Components/Templates/Animejs/AnimeJsFooter.vue
git commit -m "feat: add AnimeJsNav and AnimeJsFooter shared components"
```

---

### Task 3: `Templates/Animejs/Portfolio.vue` + wire the resolver

**Files:**
- Create: `resources/js/Pages/Public/Templates/Animejs/Portfolio.vue`
- Modify: `resources/js/Pages/Public/Portfolio.vue` (becomes a thin resolver)
- Modify: `resources/js/templateRegistry.js` (register `animejs.Portfolio`)

This is the richest, highest-priority page — the real portfolio homepage most visitors actually see (per the Part 1/2 audit: `Home.vue` is a fallback that rarely renders; `Portfolio.vue` is what renders when an admin has a published site, which is the normal case).

**Props (unchanged, passed through by the resolver):** `page: Object`, `owner: Object`, `settings: Object`, `workspaces: Object`, `dbSkills: Array|null`, `dbAbout: Object|null`, `dbExperience: Array|null` (`auth` comes from the global Inertia share, not an explicit prop).

**Behaviors to preserve (read the current `Portfolio.vue` in full first — these are real, working features, not placeholders to invent):**
- Rotating hero text (currently a raw `setInterval` text swap) — reimplement using `animate()` for the transition between phrases (fade-out old phrase, fade-in new one) instead of an instant swap.
- Scroll-spy nav — now lives in `AnimeJsNav.vue` (Task 2); pass this page's actual section list (`home`, `about`, `skills`, `experience`, `projects`, `contact`) as its `sections` prop.
- Scroll-reveal on sections — replace the current manual `.reveal`/`.revealed` IntersectionObserver with `useAnimeReveal()` (Task 1) applied to each major section.
- Real contact form (`useForm({name, email, message, user_id, page_slug})` posting to `/contact`) — keep this working exactly as today; you may restyle the form's markup but must not change its submission logic, field names, or endpoint.
- Project grid: workspace projects (`workspaces`) OR the static JSON fallback, exactly as today's branching logic — apply `useAnimeReveal` with stagger to the grid so cards fade/rise in sequence rather than all at once.
- Skills/about/experience sections: render `dbSkills`/`dbAbout`/`dbExperience` when present, matching today's fallback behavior when they're `null`.

**Visual reference:** the downloaded `vuejs-tailwindcss-portfolio-main` template's `AboutMe.vue`, `AboutClients.vue`, `ProjectSingle.vue`/`ProjectsGrid.vue` card layout, and `ContactForm.vue`/`ContactDetails.vue` two-column split are reasonable structural starting points for the corresponding sections here — but its Vue Router, feather-icons (`feather.replace()`), and `src/data/*.js` demo data must NOT be carried over; use inline SVG icons (matching the icon approach already established in `AdminLayout.vue` from the prior plan) and this page's real props instead. Also cross-reference the Stitch `platform_homepage_orange_theme` screen (`~/Downloads/stitch_vaigrantha (1).zip`) for this app's actual brand styling.

- [ ] **Step 1: Read `resources/js/Pages/Public/Portfolio.vue` in full.**

- [ ] **Step 2: Build `resources/js/Pages/Public/Templates/Animejs/Portfolio.vue`** implementing all sections and preserved behaviors above, using `AnimeJsNav`, `AnimeJsFooter`, and `useAnimeReveal`.

- [ ] **Step 3: Register it in the templateRegistry**

In `resources/js/templateRegistry.js`, change:
```js
  animejs:    { Home: null, Portfolio: null, OrgPage: null, ProjectDetail: null, LibraryIndex: null, BookDetail: null, AuthorShow: null },
```
to import the new component and set `Portfolio: AnimejsPortfolio` (add the import at the top of the file: `import AnimejsPortfolio from '@/Pages/Public/Templates/Animejs/Portfolio.vue'`).

- [ ] **Step 4: Turn `resources/js/Pages/Public/Portfolio.vue` into a thin resolver**

Wrap the file's existing `<template>` content in a conditional branch, and add a second branch that resolves and renders the template registry entry:

```vue
<template>
  <component
    v-if="animejsComponent"
    :is="animejsComponent"
    :page="page" :owner="owner" :settings="settings" :workspaces="workspaces"
    :dbSkills="dbSkills" :dbAbout="dbAbout" :dbExperience="dbExperience"
  />
  <!-- existing template content, completely unchanged, as the fallback -->
  <div v-else class="...">
    ... (everything that was already here) ...
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useActiveTemplate } from '@/composables/useActiveTemplate'
import { resolvePublicPage } from '@/templateRegistry'
// ...existing imports/props/script content, completely unchanged...

const { publicTemplate } = useActiveTemplate()
const animejsComponent = computed(() =>
  publicTemplate.value === 'animejs' ? resolvePublicPage('animejs', 'Portfolio') : null
)
</script>
```

Do not remove, rewrite, or "clean up" anything in the existing fallback branch — it must render byte-for-byte identically to how the file worked before this task, for every visitor whose active template isn't `animejs` (which, by default, is everyone, since the default setting is `minimalist`).

- [ ] **Step 5: Manual verification**

In Admin Settings → Appearance, switch "Public Site Template" to `animejs`, save. Visit the portfolio homepage. Confirm: new Animejs design renders, scroll-spy/rotating-text/reveal/contact-form all still function, nothing errors in the console. Switch the setting back to `minimalist` and confirm the page reverts to exactly today's existing design (the fallback branch).

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Public/Templates/Animejs/Portfolio.vue resources/js/Pages/Public/Portfolio.vue resources/js/templateRegistry.js
git commit -m "feat: add Animejs Portfolio template"
```

---

### Task 4: `Templates/Animejs/Home.vue` + wire the resolver

**Files:**
- Create: `resources/js/Pages/Public/Templates/Animejs/Home.vue`
- Modify: `resources/js/Pages/Public/Home.vue` (thin resolver, same pattern as Task 3 Step 4)
- Modify: `resources/js/templateRegistry.js` (register `animejs.Home`)

**Context:** per the Part 1/2 audit, this page only renders when there is no admin user with published content at all — a rare fallback path, not the normal experience. Still in scope (it's one of the 7 pages the spec lists), but don't over-invest relative to Task 3.

**Props (unchanged):** `pages: Array`, `settings: Object`.

**Behavior to preserve:** the block-driven rendering loop (`pages[].blocks[]` with types `hero`, `text`, `text_image`, `service_cards`, `project_grid`, `contact_form`), and the admin-vs-public empty state. Apply `useAnimeReveal` to each rendered block. Use `AnimeJsNav`/`AnimeJsFooter` (no `sections` prop needed — this page has no anchor nav).

- [ ] **Step 1: Read `resources/js/Pages/Public/Home.vue` in full.**
- [ ] **Step 2: Build `resources/js/Pages/Public/Templates/Animejs/Home.vue`.**
- [ ] **Step 3: Register in `templateRegistry.js`** (same pattern as Task 3 Step 3).
- [ ] **Step 4: Turn `Home.vue` into a thin resolver** (same pattern as Task 3 Step 4).
- [ ] **Step 5: Manual verification** — this page is hard to reach in normal seeded data (needs a state with no admin/published content); if you cannot easily reach it live, verify via a Vitest component test asserting it renders without error given representative `pages`/`settings` props instead, and note this limitation in your report.
- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Public/Templates/Animejs/Home.vue resources/js/Pages/Public/Home.vue resources/js/templateRegistry.js
git commit -m "feat: add Animejs Home template"
```

---

### Task 5: `Templates/Animejs/OrgPage.vue` + wire the resolver

**Files:**
- Create: `resources/js/Pages/Public/Templates/Animejs/OrgPage.vue`
- Modify: `resources/js/Pages/Public/OrgPage.vue` (thin resolver)
- Modify: `resources/js/templateRegistry.js` (register `animejs.OrgPage`)

**Props (unchanged):** `organization: Object`, `owner: Object`, `members: Array`, `achievements: Array`, `settings: Object`.

**Behavior to preserve exactly:** the `organization.white_label` conditional — when true, no shared branding/footer at all (today's `OrgPage.vue` keeps a minimal brand-only nav and hides the footer entirely in that case; the Animejs version must do the same, not use `AnimeJsNav`/`AnimeJsFooter` unconditionally). Apply `useAnimeReveal` with stagger to the members grid and achievements list — the downloaded template's `AboutClients.vue`/`AboutClientSingle.vue` logo-grid pattern is a reasonable structural reference for the members grid.

- [ ] **Step 1: Read `resources/js/Pages/Public/OrgPage.vue` in full**, paying particular attention to the exact `white_label` branching.
- [ ] **Step 2: Build `resources/js/Pages/Public/Templates/Animejs/OrgPage.vue`**, replicating the `white_label` conditional exactly.
- [ ] **Step 3: Register in `templateRegistry.js`.**
- [ ] **Step 4: Turn `OrgPage.vue` into a thin resolver.**
- [ ] **Step 5: Manual verification** — find or create a seeded organization with `white_label: false` and confirm the new design renders with nav/footer; find or create one with `white_label: true` and confirm it still renders minimal/brand-only with no footer, identical to today's behavior, regardless of the active template setting.
- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Public/Templates/Animejs/OrgPage.vue resources/js/Pages/Public/OrgPage.vue resources/js/templateRegistry.js
git commit -m "feat: add Animejs OrgPage template"
```

---

### Task 6: `Templates/Animejs/ProjectDetail.vue` + wire the resolver

**Files:**
- Create: `resources/js/Pages/Public/Templates/Animejs/ProjectDetail.vue`
- Modify: `resources/js/Pages/Public/ProjectDetail.vue` (thin resolver)
- Modify: `resources/js/templateRegistry.js` (register `animejs.ProjectDetail`)

**Props (unchanged):** `project: Object`, `media: Array`, `owner: Object`, `settings: Object`.

**Behavior to preserve exactly (read the current file in full first):** the `lightboxIndex` state machine (`openLightbox`/`closeLightbox`/`prevItem`/`nextItem`) and its keyboard navigation (`ArrowLeft`/`ArrowRight`/`Escape` via `onMounted`/`onUnmounted` window listeners) — this is real, working interactive behavior, not decoration. You may animate the lightbox's open/close and slide transitions with `animate()`, but the underlying index-tracking logic and keyboard handlers must keep working identically. Apply `useAnimeReveal` with stagger to the gallery grid.

- [ ] **Step 1: Read `resources/js/Pages/Public/ProjectDetail.vue` in full.**
- [ ] **Step 2: Build `resources/js/Pages/Public/Templates/Animejs/ProjectDetail.vue`.**
- [ ] **Step 3: Register in `templateRegistry.js`.**
- [ ] **Step 4: Turn `ProjectDetail.vue` into a thin resolver.**
- [ ] **Step 5: Manual verification** — open a project with multiple media items, confirm the lightbox opens, arrow keys navigate between items, Escape closes it, and the gallery grid animates in on scroll.
- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Public/Templates/Animejs/ProjectDetail.vue resources/js/Pages/Public/ProjectDetail.vue resources/js/templateRegistry.js
git commit -m "feat: add Animejs ProjectDetail template"
```

---

### Task 7: `Templates/Animejs/LibraryIndex.vue` + wire the resolver

**Files:**
- Create: `resources/js/Pages/Public/Templates/Animejs/LibraryIndex.vue`
- Modify: `resources/js/Pages/Public/Library/Index.vue` (thin resolver)
- Modify: `resources/js/templateRegistry.js` (register `animejs.LibraryIndex`)

**Props (unchanged):** `books: Object` (Laravel paginator: `data`, `links`, `last_page`).

Use the shared `resources/js/Components/Shared/Pagination.vue` and `EmptyState.vue` (from the prior plan) rather than reimplementing pagination/empty-state markup. Apply `useAnimeReveal` with stagger to the book cover grid. Cross-reference the Stitch `library_discovery_orange_theme` screen for this app's actual brand styling of a book grid.

- [ ] **Step 1: Read `resources/js/Pages/Public/Library/Index.vue` in full.**
- [ ] **Step 2: Build `resources/js/Pages/Public/Templates/Animejs/LibraryIndex.vue`**, importing `Pagination`/`EmptyState` from `@/Components/Shared/`.
- [ ] **Step 3: Register in `templateRegistry.js`** (note the registry key is `LibraryIndex`, not `Library` or `Index`).
- [ ] **Step 4: Turn `Library/Index.vue` into a thin resolver.**
- [ ] **Step 5: Manual verification** — confirm the book grid animates in, pagination still navigates correctly, and the empty state (if reachable in seed data with zero ready books) still renders.
- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Public/Templates/Animejs/LibraryIndex.vue resources/js/Pages/Public/Library/Index.vue resources/js/templateRegistry.js
git commit -m "feat: add Animejs LibraryIndex template"
```

---

### Task 8: `Templates/Animejs/BookDetail.vue` + wire the resolver

**Files:**
- Create: `resources/js/Pages/Public/Templates/Animejs/BookDetail.vue`
- Modify: `resources/js/Pages/Public/Library/BookDetail.vue` (thin resolver)
- Modify: `resources/js/templateRegistry.js` (register `animejs.BookDetail`)

**Props (unchanged):** `book: Object` (with nested `author{name,slug}`, `chapters[{title,sort_order}]`).

Apply `useAnimeReveal` to the cover/metadata section and the chapter list (staggered). Chapter links must continue to route to `/library/books/{slug}/chapters/{sort_order}` exactly as today — this page is explicitly NOT touched by the separate reading-modes plan; only the chapter list/detail page itself is in scope here, not `ChapterReader.vue`.

- [ ] **Step 1: Read `resources/js/Pages/Public/Library/BookDetail.vue` in full.**
- [ ] **Step 2: Build `resources/js/Pages/Public/Templates/Animejs/BookDetail.vue`.**
- [ ] **Step 3: Register in `templateRegistry.js`.**
- [ ] **Step 4: Turn `Library/BookDetail.vue` into a thin resolver.**
- [ ] **Step 5: Manual verification** — confirm metadata renders, chapter list animates in, clicking a chapter still navigates to the correct chapter reader URL.
- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Public/Templates/Animejs/BookDetail.vue resources/js/Pages/Public/Library/BookDetail.vue resources/js/templateRegistry.js
git commit -m "feat: add Animejs BookDetail template"
```

---

### Task 9: `Templates/Animejs/AuthorShow.vue` + wire the resolver

**Files:**
- Create: `resources/js/Pages/Public/Templates/Animejs/AuthorShow.vue`
- Modify: `resources/js/Pages/Public/Library/AuthorShow.vue` (thin resolver)
- Modify: `resources/js/templateRegistry.js` (register `animejs.AuthorShow`)

**Props (unchanged):** `author: Object` (`name`, `bio`), `books: Object` (paginator, `title`/`slug`/`cover_url` — note: unlike `LibraryIndex`, no nested `author` key here since the page is already author-scoped).

Same `Pagination`/`EmptyState` reuse and stagger-reveal approach as Task 7's book grid.

- [ ] **Step 1: Read `resources/js/Pages/Public/Library/AuthorShow.vue` in full.**
- [ ] **Step 2: Build `resources/js/Pages/Public/Templates/Animejs/AuthorShow.vue`.**
- [ ] **Step 3: Register in `templateRegistry.js`.**
- [ ] **Step 4: Turn `Library/AuthorShow.vue` into a thin resolver.**
- [ ] **Step 5: Manual verification** — confirm author bio renders, book grid animates in, pagination works.
- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Public/Templates/Animejs/AuthorShow.vue resources/js/Pages/Public/Library/AuthorShow.vue resources/js/templateRegistry.js
git commit -m "feat: add Animejs AuthorShow template"
```

---

### Task 10: Full verification pass

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

```bash
php artisan test
npx vitest run
```

Expected: all green (aside from the pre-existing unrelated `ExampleTest` failure noted in the architecture plan).

- [ ] **Step 2: Run the frontend build**

```bash
npm run build
```

Expected: no errors, including no anime.js import/bundling issues.

- [ ] **Step 3: Manual checklist, with the setting switched to `animejs`**

- All 7 pages render their new design: Home (or confirm via component test if unreachable live), Portfolio, OrgPage (both white-label and non-white-label), ProjectDetail, Library Index, BookDetail, AuthorShow.
- Portfolio's rotating hero text, scroll-spy nav, reveal animations, and contact form submission all work.
- ProjectDetail's lightbox open/close, arrow-key navigation, and Escape-to-close all work.
- Mobile hamburger menu (in `AnimeJsNav`) opens/closes with animation on a narrow viewport, on at least 2 of the 7 pages.
- No console errors on any of the 7 pages.

- [ ] **Step 4: Manual checklist, with the setting switched back to `minimalist`**

- All 7 pages revert to exactly today's pre-existing design — confirm at least 3 of them (Portfolio, one Library page, ProjectDetail) render identically to how they looked before this entire plan, proving the fallback branches were never touched.

- [ ] **Step 5: Commit any final fixes**

If manual verification surfaces issues, fix and commit them individually with descriptive messages before considering this plan complete.
