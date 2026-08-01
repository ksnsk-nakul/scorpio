# Minimalist Public Template Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the `minimalist` public template — the calm, static counterpart to the already-completed `animejs` template (`docs/superpowers/plans/2026-07-31-animejs-public-template.md`, all 7 pages done). Minimalist deliberately drops every purely decorative motion (scroll-spy highlight-as-you-scroll, rotating-text crossfade animation, scroll-reveal-on-intersect, lightbox slide/fade transitions) while preserving every piece of *real* functionality unchanged (contact form submission, project-grid data branching, `white_label` gating, lightbox keyboard navigation, chapter routing). This is what gives the two templates genuine differentiation: Anime.js is motion-forward, Minimalist is quiet and content-first — not just a re-skin with fewer `animate()` calls.

**Architecture:** Same registry/resolver pattern already established. **Critical prerequisite fixed in Task 1**: the 7 existing resolvers currently hardcode `publicTemplate.value === 'animejs'` as the only condition for rendering a template component — meaning even though the `Setting` defaults to `'minimalist'`, nothing has ever actually resolved through that branch, because the resolvers never check for it. Task 1 generalizes all 7 resolvers to look up whatever template is active, not just `'animejs'` — without this fix, Minimalist would build correctly but never actually render for anyone.

**Tech Stack:** Vue 3 (Composition API) + Inertia.js v2 + Tailwind v4. **No anime.js import anywhere in this plan** — that's the point. Structural/visual reference: `~/Downloads/vuejs-tailwindcss-portfolio-main.zip` (already used for the Anime.js template's structural ideas; strip its Vue Router and feather-icons as before) and the two `stitch_vaigrantha` Stitch export zips.

---

### Task 1: Generalize the 7 resolvers to resolve any active template, not just `animejs`

**Files:**
- Modify: `resources/js/Pages/Public/Home.vue`
- Modify: `resources/js/Pages/Public/Portfolio.vue`
- Modify: `resources/js/Pages/Public/OrgPage.vue`
- Modify: `resources/js/Pages/Public/ProjectDetail.vue`
- Modify: `resources/js/Pages/Public/Library/Index.vue`
- Modify: `resources/js/Pages/Public/Library/BookDetail.vue`
- Modify: `resources/js/Pages/Public/Library/AuthorShow.vue`
- Test: `tests/js/templateRegistry.test.js` (extend, don't replace)

Every one of these 7 files currently has a computed shaped like this (using `Portfolio.vue` as the example — the pattern is identical in all 7, just with a different registry page-name string):

```js
const animejsComponent = computed(() =>
  publicTemplate.value === 'animejs' ? resolvePublicPage('animejs', 'Portfolio') : null
)
```

and a template branch:

```vue
<component v-if="animejsComponent" :is="animejsComponent" ... />
<template v-else> ... original markup ... </template>
```

- [ ] **Step 1: Read all 7 files** to confirm the current exact variable name/shape in each (they should all follow the identical pattern established across the Anime.js plan, but confirm before editing — do not assume).

- [ ] **Step 2: In each of the 7 files, rename and generalize the computed.** Example for `Portfolio.vue`:

```js
const activeTemplateComponent = computed(() => resolvePublicPage(publicTemplate.value, 'Portfolio'))
```

(Replace `'Portfolio'` with the correct registry key for each file: `Home`, `Portfolio`, `OrgPage`, `ProjectDetail`, `LibraryIndex`, `BookDetail`, `AuthorShow`.) This removes the `publicTemplate.value === 'animejs' ? ... : null` ternary entirely — `resolvePublicPage()` already returns `null` for any template/page combination that isn't registered (verified behavior, see `resources/js/templateRegistry.js`), so the ternary was always redundant *and* was the actual bug hard-coding `animejs` as the only reachable branch.

- [ ] **Step 3: Update the template branch's condition to use the renamed variable.**

```vue
<component v-if="activeTemplateComponent" :is="activeTemplateComponent" ... />
<template v-else> ... unchanged ... </template>
```

Do not touch anything inside the `v-else` fallback branch in this task — that stays exactly as-is; only the `v-if` condition and its backing computed change.

- [ ] **Step 4: Add a regression test to `tests/js/templateRegistry.test.js`** (or a new small test file if that one doesn't fit) asserting that once a page is registered under *any* template key (not just `animejs`), `resolvePublicPage` returns it — this test already conceptually passes today since `resolvePublicPage` itself was never the bug, but add an explicit case using a stubbed second template key to guard against this exact class of resolver-side regression recurring.

- [ ] **Step 5: Run the full suite and build**

```bash
npx vitest run
npx vite build
```

Expected: all existing tests still pass (the `animejs` template must keep resolving exactly as before — this task is a generalization, not a behavior change for the `animejs` path), clean build.

- [ ] **Step 6: Manual verification**

With `layout_template_public` still set to `minimalist` (the default) and *no* Minimalist components registered yet (this task runs before any are built), confirm every one of the 7 pages still renders its original fallback markup exactly as before — `activeTemplateComponent` should still be `null` for all of them right now, since `minimalist.*` entries are still `null` in the registry. This proves the generalization didn't accidentally break the "nothing registered yet" case. Then flip the setting to `animejs` and spot-check 2-3 pages still resolve to their Anime.js versions exactly as before.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Public/Home.vue resources/js/Pages/Public/Portfolio.vue resources/js/Pages/Public/OrgPage.vue resources/js/Pages/Public/ProjectDetail.vue resources/js/Pages/Public/Library/Index.vue resources/js/Pages/Public/Library/BookDetail.vue resources/js/Pages/Public/Library/AuthorShow.vue tests/js/templateRegistry.test.js
git commit -m "fix: resolve any active public template, not just animejs, in all 7 page resolvers"
```

---

### Task 2: `MinimalistNav` and `MinimalistFooter` shared components

**Files:**
- Create: `resources/js/Components/Templates/Minimalist/MinimalistNav.vue`
- Create: `resources/js/Components/Templates/Minimalist/MinimalistFooter.vue`

Same props contract as `AnimeJsNav`/`AnimeJsFooter` (`settings`, `sections` for the nav) so every page task can swap one template's nav for the other without prop mismatches. The difference is entirely in behavior/motion, not the interface:

**`MinimalistNav.vue`:**
- Fixed/sticky top nav, same `bg-white/80 backdrop-blur border-b border-slate-100` convention as `AnimeJsNav`.
- Anchor links (`sections` prop) work via plain `href="#id"` — native browser anchor scrolling, **no JS scroll listener, no active-section highlighting**. This is the core "drop the motion" decision for this task: Portfolio's original scroll-spy is real, working code, but is explicitly decorative (highlighting which section you're in) rather than functional (the links still navigate correctly without it).
- Mobile menu: a `v-if`/`v-show` toggle with a plain Tailwind `transition` utility class for the open/close (e.g. `transition-all duration-150`) if you want *some* softness, or an instant toggle with no transition at all — either is acceptable for "minimal," but **no `animate()`, no anime.js import anywhere in this file**.
- Same three-way auth CTA (Dashboard→ / Login+Signup / nothing) as `AnimeJsNav`, same `/library` link always present.

**`MinimalistFooter.vue`:**
- Same content contract as `AnimeJsFooter` (site name, Terms/Privacy/Refund links, donate link gated on `settings.show_donate_banner`) — the footer was never animated in the Anime.js version either, so this is close to a content-identical sibling file, kept separate per this system's template-independence design (each template owns its own Nav+Footer files, even where the content ends up similar, so either can evolve independently later).

- [ ] **Step 1: Read `resources/js/Components/Templates/Animejs/AnimeJsNav.vue` and `AnimeJsFooter.vue`** as your reference for the props contract and content, then build the Minimalist versions with motion removed per the description above.
- [ ] **Step 2: Build `MinimalistNav.vue`.**
- [ ] **Step 3: Build `MinimalistFooter.vue`.**
- [ ] **Step 4: Verify** via a Vitest smoke test (mount with minimal props, assert no error, assert no `animejs` import anywhere in either file — a simple `grep`/string-check test is fine here to enforce the "no motion library" constraint mechanically).
- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/Templates/Minimalist/MinimalistNav.vue resources/js/Components/Templates/Minimalist/MinimalistFooter.vue
git commit -m "feat: add MinimalistNav and MinimalistFooter shared components"
```

---

### Task 3: `Templates/Minimalist/Portfolio.vue` + wire the registry

**Files:**
- Create: `resources/js/Pages/Public/Templates/Minimalist/Portfolio.vue`
- Modify: `resources/js/templateRegistry.js` (register `minimalist.Portfolio`)

**Props (unchanged):** `page`, `owner`, `settings`, `workspaces`, `dbSkills`, `dbAbout`, `dbExperience` — identical to the Anime.js Portfolio template (see `resources/js/Pages/Public/Templates/Animejs/Portfolio.vue` for the exact prop shapes and the original's full behavior reference, both already in this codebase).

**What to preserve exactly (real functionality, not motion):**
- Contact form: `useForm({name, email, message, user_id, page_slug})` posting to `/contact`, `preserveScroll: true`, `onSuccess: reset()` — byte-identical logic to both the original and the Anime.js version.
- Project grid: DB-workspace branch vs. static-JSON-fallback branch, exact field names in each.
- `dbSkills`/`dbAbout`/`dbExperience` fallback order, exact same expressions as the original/Anime.js versions.
- `<Head>` meta (`pageTitle`/`pageDescription` computed logic).
- Nav `sections` list: same admin-reorderable `page.blocks`-order derivation used in the Anime.js version's fix (do not regress to a fixed canonical order).

**What to drop (decorative motion — this is what makes it "minimalist"):**
- Rotating hero text: keep the `setInterval`-driven index cycling (still shows all the configured phrases in rotation — this is real content the admin configured, not decoration) but the phrase swap is an **instant text change, no fade/slide transition of any kind**. No `Transition` component, no `animate()`.
- No scroll-spy (handled by `MinimalistNav` already).
- No scroll-reveal — sections render fully visible immediately, no `useAnimeReveal`, no IntersectionObserver, no opacity/translateY animation on mount or scroll.

- [ ] **Step 1: Read `resources/js/Pages/Public/Portfolio.vue` (the resolver's `v-else` fallback, which is the original untouched source) and `resources/js/Pages/Public/Templates/Animejs/Portfolio.vue` (for the already-verified-correct preserved-behavior reference) yourself first.**
- [ ] **Step 2: Build `resources/js/Pages/Public/Templates/Minimalist/Portfolio.vue`** using `MinimalistNav`/`MinimalistFooter`, with all real functionality preserved and all decorative motion removed per above.
- [ ] **Step 3: Register in `resources/js/templateRegistry.js`**: add `import MinimalistPortfolio from '@/Pages/Public/Templates/Minimalist/Portfolio.vue'`, set `minimalist.Portfolio: MinimalistPortfolio`.
- [ ] **Step 4: Verify**: `npx vite build` clean. You will have live browser access for this one (per the pattern established in the Anime.js plan, where Portfolio was live-verified) — switch `layout_template_public` to `minimalist` (via `php artisan tinker` if the admin UI is in demo/read-only mode, same approach used throughout the Anime.js plan's verification), visit the homepage, confirm: hero renders, rotating text still cycles through phrases (just without a fade), all sections render immediately with no fade-in delay, contact form still submits successfully, project grid still branches correctly. Also grep your own new file for the string `animejs` / `useAnimeReveal` and confirm zero matches.
- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Public/Templates/Minimalist/Portfolio.vue resources/js/templateRegistry.js
git commit -m "feat: add Minimalist Portfolio template"
```

---

### Task 4: `Templates/Minimalist/Home.vue` + wire the registry

**Files:**
- Create: `resources/js/Pages/Public/Templates/Minimalist/Home.vue`
- Modify: `resources/js/templateRegistry.js` (register `minimalist.Home`)

**Props (unchanged):** `pages: Array`, `settings: Object`. Same low-priority context as the Anime.js Home template (rarely-hit fallback path — see `resources/js/Pages/Public/Templates/Animejs/Home.vue` for the exact preserved-behavior reference and the block-type list: `hero`/`text`/`text_image`/`service_cards`/`project_grid`/`contact_form`).

**Preserve:** the block-driven rendering loop, the admin-vs-public empty state, the **non-functional** contact form stub exactly as-is (do not wire it to real submission — same reasoning as the Anime.js version: no `owner` prop exists on this page for a real submission to target).

**Drop:** any reveal animation (there's no rotating text or scroll-spy on this page to begin with, so the only motion to remove is scroll-reveal).

- [ ] **Step 1: Read `resources/js/Pages/Public/Home.vue` (resolver fallback) and `resources/js/Pages/Public/Templates/Animejs/Home.vue` yourself first.**
- [ ] **Step 2: Build `resources/js/Pages/Public/Templates/Minimalist/Home.vue`.**
- [ ] **Step 3: Register in `templateRegistry.js`.**
- [ ] **Step 4: Verify** — same hard-to-reach-live caveat as the Anime.js version applies; use a Vitest component test covering all 6 block types + both empty states, confirm the contact form has no submit handler (same test pattern as `tests/js/AnimejsHome.test.js`, but this time write an assertion that actually proves it — recall the Anime.js version's test for this was flagged in review as not actually testing what it claimed; don't repeat that mistake, assert on the absence of a `useForm`/`post` call path rather than an `onsubmit` DOM attribute).
- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Public/Templates/Minimalist/Home.vue resources/js/templateRegistry.js
git commit -m "feat: add Minimalist Home template"
```

---

### Task 5: `Templates/Minimalist/OrgPage.vue` + wire the registry

**Files:**
- Create: `resources/js/Pages/Public/Templates/Minimalist/OrgPage.vue`
- Modify: `resources/js/templateRegistry.js` (register `minimalist.OrgPage`)

**Props (unchanged):** `organization`, `owner`, `members`, `achievements`, `settings`.

**THE critical requirement, same as the Anime.js version:** `organization.white_label` must gate `MinimalistNav`/`MinimalistFooter` exactly the same way it gates `AnimeJsNav`/`AnimeJsFooter` in `resources/js/Pages/Public/Templates/Animejs/OrgPage.vue` — no shared branding/footer at all when true, a minimal brand-only nav instead. This is a paid-feature boundary, not a styling choice; get the branching structure from the already-verified-correct `Animejs/OrgPage.vue` file directly rather than re-deriving it from the original, to avoid re-introducing a bug that was already found and fixed once (the `pt-28` vs. nav-height padding mismatch from that task's review — check your padding against the nav's real height here too).

**Drop:** member-grid and achievement-list stagger/reveal animation — sections render fully visible.

- [ ] **Step 1: Read `resources/js/Pages/Public/OrgPage.vue` (resolver fallback) and `resources/js/Pages/Public/Templates/Animejs/OrgPage.vue` (for the verified-correct white_label branching structure) yourself first.**
- [ ] **Step 2: Build `resources/js/Pages/Public/Templates/Minimalist/OrgPage.vue`.**
- [ ] **Step 3: Register in `templateRegistry.js`.**
- [ ] **Step 4: Verify** — write a Vitest test covering both `white_label` states (mirroring `tests/js/AnimejsOrgPage.test.js`'s approach: assert full absence of nav/footer chrome, not just visual hiding).
- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Public/Templates/Minimalist/OrgPage.vue resources/js/templateRegistry.js
git commit -m "feat: add Minimalist OrgPage template"
```

---

### Task 6: `Templates/Minimalist/ProjectDetail.vue` + wire the registry

**Files:**
- Create: `resources/js/Pages/Public/Templates/Minimalist/ProjectDetail.vue`
- Modify: `resources/js/templateRegistry.js` (register `minimalist.ProjectDetail`)

**Props (unchanged):** `project`, `media`, `owner`, `settings`.

**Preserve exactly (real functionality):** the `lightboxIndex` state machine and keyboard navigation (`ArrowLeft`/`ArrowRight`/`Escape`) — get this from `resources/js/Pages/Public/Templates/Animejs/ProjectDetail.vue`, which already has it verified byte-identical to the original, including the 2-item-gallery direction-detection fix (not relevant here since Minimalist has no slide direction to compute at all, see below) and the `aria-label`s added to the lightbox controls during that task's review (carry those over too — they're accessibility fixes, not motion, keep them).

**Drop:** the `Transition`-based open/close fade and the `watch()`-driven slide-between-items animation. The lightbox opens/closes via a plain `v-if`, and switching between items just updates `currentItem` with no transition at all — instant image swap. This actually *simplifies* this file relative to its Anime.js counterpart (no `isForward` direction-detection logic needed at all, since there's nothing to animate a direction for).

**Nav/footer:** same design call as the Anime.js version — keep a minimal hand-rolled "← Back to Portfolio | Owner Name" bar (or use the shared `BackLink.vue` if it fits, same as before), not `MinimalistNav`, for the same reasons (sub-page, not top-level site nav).

- [ ] **Step 1: Read `resources/js/Pages/Public/ProjectDetail.vue` (resolver fallback) and `resources/js/Pages/Public/Templates/Animejs/ProjectDetail.vue` yourself first.**
- [ ] **Step 2: Build `resources/js/Pages/Public/Templates/Minimalist/ProjectDetail.vue`.**
- [ ] **Step 3: Register in `templateRegistry.js`.**
- [ ] **Step 4: Verify** — write a Vitest test suite mirroring `tests/js/AnimejsProjectDetail.test.js`'s real-`KeyboardEvent`-dispatch approach (open/close, prev/next wraparound at both boundaries, Escape/ArrowLeft/ArrowRight via genuine `window` keydown events, listener cleanup on unmount, single-item gallery edge case). This is the highest-risk file in this plan for the same reason it was in the Anime.js plan — real interactive state, not just rendering.
- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Public/Templates/Minimalist/ProjectDetail.vue resources/js/templateRegistry.js
git commit -m "feat: add Minimalist ProjectDetail template"
```

---

### Task 7: `Templates/Minimalist/LibraryIndex.vue` + wire the registry

**Files:**
- Create: `resources/js/Pages/Public/Templates/Minimalist/LibraryIndex.vue`
- Modify: `resources/js/templateRegistry.js` (register `minimalist.LibraryIndex`)

**Props (unchanged):** `books: Object` (paginator).

**Note — check before you start:** a separate follow-up task (spawned during the Anime.js plan's code review) has been extracting shared `resources/js/Components/Shared/LibraryNav.vue` (props `backHref`, `backLabel`, `title`, `titleTruncate`) and `resources/js/Components/Shared/BookCoverGrid.vue` (props `books`, `showAuthor`) out of the Anime.js library pages, and may already be landing in this same worktree while this plan executes. **Check whether these two files exist before building this task** — if they do, use them directly (they already include `:alt="book.title"` and the `reveal-item` class hook) instead of hand-rolling the nav/grid markup a further time; the grid's `reveal-item` class is inert with no effect unless something calls `useAnimeReveal` on it, so it's safe to reuse as-is in a template with no reveal system. If they don't exist yet when you start, build the nav/grid inline the same way `resources/js/Pages/Public/Templates/Animejs/LibraryIndex.vue`'s pre-extraction version did, and don't block on the other task. Either way: use the shared `resources/js/Components/Shared/Pagination.vue`/`EmptyState.vue`, book grid renders immediately with no stagger-reveal, and cover images have `:alt="book.title"` from the start.

- [ ] **Step 1: Read `resources/js/Pages/Public/Library/Index.vue` (resolver fallback) and `resources/js/Pages/Public/Templates/Animejs/LibraryIndex.vue` yourself first — check its current imports to see whether it already uses `LibraryNav`/`BookCoverGrid` or still has inline markup, and follow whichever state it's actually in.**
- [ ] **Step 2: Build `resources/js/Pages/Public/Templates/Minimalist/LibraryIndex.vue`.**
- [ ] **Step 3: Register in `templateRegistry.js`.**
- [ ] **Step 4: Verify** — Vitest test mirroring `tests/js/AnimejsLibraryIndex.test.js` (empty state, populated grid hrefs, pagination boundary).
- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Public/Templates/Minimalist/LibraryIndex.vue resources/js/templateRegistry.js
git commit -m "feat: add Minimalist LibraryIndex template"
```

---

### Task 8: `Templates/Minimalist/BookDetail.vue` + wire the registry

**Files:**
- Create: `resources/js/Pages/Public/Templates/Minimalist/BookDetail.vue`
- Modify: `resources/js/templateRegistry.js` (register `minimalist.BookDetail`)

**Props (unchanged):** `book: Object`. Preserve cover/metadata/author-link/chapter-list bindings and the exact chapter route + `Chapter N` fallback title (verify the `+1` off-by-one the same way the Anime.js task's review specifically checked). Include cover `:alt="book.title"` from the start. No reveal animation on the chapter list — renders immediately.

**Same shared-component note as Task 7**: check whether `resources/js/Components/Shared/LibraryNav.vue` exists and use it (`back-href="/library"`, `back-label="Library"`, `:title="book.title"`, `title-truncate`) instead of hand-rolling the nav bar, matching whatever `resources/js/Pages/Public/Templates/Animejs/BookDetail.vue` is currently doing at the time you read it.

- [ ] **Step 1: Read `resources/js/Pages/Public/Library/BookDetail.vue` (resolver fallback) and `resources/js/Pages/Public/Templates/Animejs/BookDetail.vue` yourself first — check its current nav implementation and follow the same approach.**
- [ ] **Step 2: Build `resources/js/Pages/Public/Templates/Minimalist/BookDetail.vue`.**
- [ ] **Step 3: Register in `templateRegistry.js`.**
- [ ] **Step 4: Verify** — Vitest test mirroring `tests/js/AnimejsBookDetail.test.js` (metadata fields, author link, chapter hrefs including the null-title fallback case).
- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Public/Templates/Minimalist/BookDetail.vue resources/js/templateRegistry.js
git commit -m "feat: add Minimalist BookDetail template"
```

---

### Task 9: `Templates/Minimalist/AuthorShow.vue` + wire the registry

**Files:**
- Create: `resources/js/Pages/Public/Templates/Minimalist/AuthorShow.vue`
- Modify: `resources/js/templateRegistry.js` (register `minimalist.AuthorShow`)

**Props (unchanged):** `author: Object`, `books: Object`. Same shared `Pagination`/`EmptyState` reuse as `LibraryIndex`, same minimal-nav precedent, cover `:alt="book.title"` from the start, no author line on book cards (props never carry it here, same as the Anime.js version — if `BookCoverGrid.vue` exists, this means passing it without the `show-author` flag).

**Same shared-component note as Tasks 7-8**: check whether `LibraryNav.vue`/`BookCoverGrid.vue` exist and use them, matching whatever `resources/js/Pages/Public/Templates/Animejs/AuthorShow.vue` is currently doing at the time you read it.

- [ ] **Step 1: Read `resources/js/Pages/Public/Library/AuthorShow.vue` (resolver fallback) and `resources/js/Pages/Public/Templates/Animejs/AuthorShow.vue` yourself first — check its current nav/grid implementation and follow the same approach.**
- [ ] **Step 2: Build `resources/js/Pages/Public/Templates/Minimalist/AuthorShow.vue`.**
- [ ] **Step 3: Register in `templateRegistry.js`.**
- [ ] **Step 4: Verify** — Vitest test mirroring `tests/js/AnimejsAuthorShow.test.js` (bio present/absent, empty state, grid hrefs, pagination boundary).
- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Public/Templates/Minimalist/AuthorShow.vue resources/js/templateRegistry.js
git commit -m "feat: add Minimalist AuthorShow template"
```

---

### Task 10: Full verification pass

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite and build**

```bash
php artisan test
npx vitest run
npm run build
```

Expected: all green (aside from the pre-existing unrelated `ExampleTest` failure already documented in the architecture plan).

- [ ] **Step 2: Manual checklist with the setting on `minimalist` (the actual default now, for the first time genuinely reachable)**

- All 7 pages render the new static design (Home via component test if unreachable live, same as before).
- Portfolio's rotating text still cycles through all configured phrases (no crash, no stuck-blank state), just without a transition.
- ProjectDetail's lightbox opens/closes, arrow keys and Escape work, no animation but state is correct.
- OrgPage's white_label branching genuinely absent/present correctly.
- Mobile nav toggle works on at least 2 of the 7 pages.
- No console errors on any of the 7 pages.
- Confirm **zero** `animejs`/`useAnimeReveal` imports anywhere under `resources/js/Pages/Public/Templates/Minimalist/` or `resources/js/Components/Templates/Minimalist/` (`grep -r "animejs\|useAnimeReveal" resources/js/Pages/Public/Templates/Minimalist resources/js/Components/Templates/Minimalist` should return nothing).

- [ ] **Step 3: Manual checklist — switch back to `animejs`, confirm nothing regressed**

Spot-check 3 pages (Portfolio, one Library page, ProjectDetail) still render their Anime.js versions correctly — proving Task 1's resolver generalization didn't disturb the already-working `animejs` path.

- [ ] **Step 4: Update the admin Settings dropdown copy if needed**

Check `resources/js/Pages/Admin/Settings/Index.vue`'s `selectKeys.layout_template_public` labels ("Minimalist", "Anime.js") still read correctly now that both are real, finished options rather than one real option and one placeholder — no code change expected, just confirm the existing labels don't need updating.

- [ ] **Step 5: Commit any final fixes**

If manual verification surfaces issues, fix and commit them individually with descriptive messages before considering this plan complete.
