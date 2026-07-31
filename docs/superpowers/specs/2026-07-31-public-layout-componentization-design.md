# Template-Driven Public & Admin Layout System — Design Spec

## Purpose

Replace the public site's duplicated, hand-rolled nav/footer markup and the single fixed admin dashboard look with a **settings-driven template system**: a small number of fully-built, selectable visual templates per audience, switchable from the admin panel without a code deploy. This supersedes the earlier, smaller "just extract a shared nav" plan — through this design conversation the scope deliberately grew into a real template system, confirmed page by page below.

This is a pure frontend + lightweight-settings feature. No new database tables — see "Architecture" below.

## Roles → Templates

| Role/context | Template(s) | Selectable? |
|---|---|---|
| Admin, Owner, Editor (internal, permission-scoped) | **Stripe-like** (one template) | No — single option today, architecture stays extensible for a second later. |
| User (public visitor), Author (portfolio project author + e-library author bio) | **Minimalist** or **Anime.js** | Yes — admin picks one from a settings screen. |
| E-library chapter reader | *(unchanged, existing layout)* | Not part of this system at all — excluded entirely, has its own dedicated settings drawer from the separate reading-modes spec. |

## Scope: full page re-skin, not chrome-only

This was explicitly clarified during design: the Minimalist and Anime.js templates each own the **entire visual identity** of every in-scope public page — hero sections, content layout, section structure, and (for Anime.js) motion — not just a shared Nav/Footer wrapped around unchanged page bodies. Concretely, each of the following pages gets a real implementation under **both** templates, both driven by the same underlying Inertia props/data:

- `Home.vue`
- `Portfolio.vue` (including its scroll-spy anchor nav and animated hero — this becomes template-owned rather than bespoke-and-untouchable)
- `OrgPage.vue` (respecting the existing `white_label` flag — see "Preserved behavior" below)
- `ProjectDetail.vue`
- `Library/Index.vue`
- `Library/BookDetail.vue`
- `Library/AuthorShow.vue`

**Explicitly excluded from per-template re-skinning** (stay as one shared, simple implementation regardless of which public template is active): `Privacy.vue`, `Terms.vue`, `Refund.vue` (legal boilerplate — no value in duplicating), `ProfileStub.vue` (a placeholder state, not a designed page), and `ChapterReader.vue` (its own spec, its own toolbar/drawer).

**Preserved behavior regardless of template:** `OrgPage.vue`'s `white_label` flag continues to suppress platform branding/footer entirely when true — both templates must implement this the same way, it's an existing paid feature, not incidental styling.

## Architecture

**No new `Template` database table.** The app already has a generic key/value `Setting` model (`app/Models/Setting.php`, `Setting::get()`/`Setting::set()`, grouped by a `group` column). Templates are a small hardcoded registry of component sets; which one is *active* is just two Setting rows:

- `layout_template.admin` — fixed value `stripe` (no picker needed yet, since it's the only option; still a registry lookup so a second admin template is a drop-in later, not a redesign)
- `layout_template.public` — `minimalist` | `animejs`, changeable from a new "Public Site Template" section in the existing Admin Settings screen (`resources/js/Pages/Admin/Settings/Index.vue`, `app/Http/Controllers/Admin/SettingController.php`)

`SettingController::update()` only updates *existing* setting rows (it doesn't create missing ones), so a migration/seeder must insert these two rows with their defaults, and the settings `groups` array in `SettingController::index()` needs a new `appearance` group.

A small `useActiveTemplate()` composable resolves the registry entry for the current context, reading the relevant setting via an Inertia shared prop (extend `HandleInertiaRequests` middleware to share `layoutTemplates: { admin, public }` on every request).

```
Registry shape (illustrative):
{
  stripe:     { Layout: AdminLayout },              // admin/owner/editor
  minimalist: { Home, Portfolio, OrgPage, ProjectDetail, LibraryIndex, BookDetail, AuthorShow },
  animejs:    { Home, Portfolio, OrgPage, ProjectDetail, LibraryIndex, BookDetail, AuthorShow },
}
```

Each public page's Inertia component becomes a thin resolver: it fetches the active public template from the registry and renders that template's implementation of itself, passing through the same props the controller already provides. Controllers are untouched — this is purely a component-resolution layer.

## Shared (template-agnostic) components

A few pieces are genuinely generic utility, not visual identity, and are **not** duplicated per template — built once, styled via CSS custom properties each template defines:

- `BackLink.vue` — the "← X" pattern
- `Pagination.vue` — page-number controls (used by Library/Index, AuthorShow)
- `EmptyState.vue` — "nothing here yet" pattern
- `LegalPage.vue` — shared structure for Privacy/Terms/Refund

Excluded from extraction (per earlier audit decision, still holds): a unified `SectionCard.vue` and `ContactForm.vue` merge — different content shapes / different submission behavior between Home's static and Portfolio's live form made these a leaky abstraction not worth forcing.

## Template sources

**Stripe-like admin** (`stripe`): built primarily from **TailAdmin Vue** (already downloaded — confirmed via its `package.json`/README to be Vue 3 + Tailwind CSS 4, matching this app's exact stack) as the structural base — sidebar, routing conventions, component patterns. Visual polish toward an actual Stripe-like clean minimal look draws on the AI-generated `stitch_vaigrantha` dark-theme export screens (`author_dashboard_desktop_dark`, `upload_publish_desktop_dark`, `pricing_desktop_dark`, `library_desktop_dark`), since those were generated specifically against this app's own brand/content rather than generic placeholder content. `flowbite-vue` (downloaded — confirmed to be the Flowbite Vue **component library**, not a full dashboard template) and `themesberg/flowbite-admin-dashboard` (the real dashboard-with-pages repo, not yet downloaded) are secondary references for specific component patterns (data tables, modals) where useful. This becomes the new `AdminLayout.vue`, replacing the current one.

**Minimalist** (`minimalist`): structural base is `vuejs-tailwindcss-portfolio` (already downloaded, confirmed Vue 3 Composition API + Tailwind CSS 3, ~300+ GitHub stars, multipage, dark mode) — restyled to this app's real content and branding, cross-referencing the Stitch orange-theme screens (`platform_homepage_orange_theme`, `library_discovery_orange_theme`, `reader_view_orange_theme`, `author_dashboard_orange_theme`) for visual details specific to this app.

**Anime.js** (`animejs`): the **same** structural base and page-mapping work as Minimalist — not a separately maintained codebase — with anime.js-driven entrance/scroll motion layered on top. This was a deliberate choice during design: nearly everything discoverable under "anime.js portfolio template" online is actually *anime-aesthetic* (Japanese cartoon style), a naming collision, not a real anime.js-library template — so building on Minimalist's already-correct structure and adding motion is both more accurate to what "Anime.js template" actually means here and cheaper to maintain than two divergent codebases.

**Not built in this pass:** `Bento-Grid-Portfolio` (downloaded, confirmed Vue + Tailwind) is kept as a candidate **future third public template** — the registry design supports adding it later without rework, but only two public templates (Minimalist, Anime.js) were confirmed in scope for this plan.

## Loaders

Three distinct loading patterns, used consistently across both templates and the admin template:

1. **Skeleton screens** for component-level async content (e.g. the book grid on `Library/Index.vue`/`AuthorShow.vue` while paginating) — a pulsing placeholder shaped like the real content, avoiding layout shift.
2. **Top progress bar** for full Inertia page navigations (NProgress-style thin bar).
3. **Spinners** for icons, images, and button-press/form-submission states (in-place, small).

## Mobile navigation

A real, currently-existing gap: no page in the app has a working mobile nav today (`Portfolio.vue` hides its nav links under `md:flex` with no fallback). Both `minimalist` and `animejs` templates' Nav implementations must include an actual hamburger-menu / slide-down mobile nav — not optional polish, a genuine missing capability being fixed here.

## Auth-aware nav element

`AuthNavCta` — a small reusable piece (not a full template-owned component, since its *logic* is identical everywhere): renders a "Dashboard →" button for admins, "Login"/"Sign up" for guests, nothing for logged-in non-admins. Both templates' Nav implementations use it rather than re-deriving the same `isAdmin`/auth conditional independently (currently computed redundantly via `usePage().props.auth.roles` in nearly every public page).

## Out of scope / deferred

- **Animation polish beyond each template's own motion** (i.e. the broader anime.js integration pass covering the reading-modes drawer, page-flip transitions, etc.) — tracked separately, starts once this and the reading-modes feature are both functionally complete.
- A picker for the **admin** template group (`layout_template.admin`) — architecture supports it, not built until there's a second admin template to choose between.
- The `Bento-Grid-Portfolio` third public template.
- Any change to `ChapterReader.vue`, which keeps its current layout entirely, governed only by the separate reading-modes spec.

## Testing approach

No backend logic changes beyond the two new Setting rows and the shared-prop addition, so minimal Pest coverage (verify the settings update endpoint persists `layout_template.public` correctly; verify the shared prop is present on an Inertia response). The bulk of verification is manual browser testing:

- Switching `layout_template.public` between `minimalist`/`animejs` in Admin Settings visibly changes Home/Portfolio/OrgPage/ProjectDetail/Library pages without a deploy.
- Every in-scope page renders correctly, responsively, under both public templates.
- `OrgPage.vue`'s `white_label` behavior is identical under both templates.
- Mobile hamburger nav works in both public templates.
- Admin/Owner/Editor all see the new Stripe-like admin template; permission-gated nav items still respect role.
- Skeleton/progress-bar/spinner loaders appear in their respective contexts.
- Legal pages, `ProfileStub.vue`, and `ChapterReader.vue` are visually unaffected by the public template switch.
