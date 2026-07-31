# Public Layout & Nav Componentization — Design Spec

## Purpose

The public site currently has no shared layout: `Home.vue`, `Portfolio.vue`, `OrgPage.vue`, `ProjectDetail.vue`, and the e-library's `Index.vue`/`BookDetail.vue`/`AuthorShow.vue` each hard-code their own nav and (where present) footer markup independently. This adds a `Library` link to the public site's navigation and, since that touches nearly every public page's header, extracts the genuinely shared pieces into reusable, responsive components so future pages don't repeat this duplication — while explicitly leaving alone the pages/sections that are too tightly coupled to their own unique behavior to safely generalize.

This is a pure frontend feature. No backend, route, or controller changes.

## Audit findings

- `Home.vue`, `Library/Index.vue`, `Library/BookDetail.vue`, `Library/AuthorShow.vue`, and `ProjectDetail.vue` all hard-code near-identical "brand + optional CTA" nav bars and (for Home.vue) a near-identical minimal footer.
- `Portfolio.vue`'s nav is fundamentally different: it's an anchor-scroller with scroll-spy active-section tracking and a bespoke fade/float animation system tied to specific section IDs (`#home`, `#about`, `#skills`, `#projects`, `#experience`, `#contact`). It is not a "site nav" in the same sense as the others.
- `OrgPage.vue` has a `white_label` flag that hides the platform's footer/branding entirely when true — an existing paid feature, not incidental duplication.
- `Home.vue` and `Portfolio.vue` both implement a near-identical dynamic block-renderer (`hero`/`text`/`text_image`/`service_cards`/`project_grid`/`contact_form`), but Portfolio's versions are wired into its scroll-reveal/anchor system (IDs, `reveal` classes, staggered `transition-delay`). **This duplication is flagged but intentionally NOT addressed in this pass** — unifying it risks breaking Portfolio's animation behavior for a refactor beyond this feature's scope.
- `ChapterReader.vue` gets its own dedicated reading toolbar (from the separate reading-modes spec) and is excluded from this componentization — see "New-tab entry point" below.

## Components to create

1. **`resources/js/Components/Public/AuthNavCta.vue`** — the auth-aware button cluster: a "Dashboard →" button if the current user is an admin, "Login"/"Sign up" buttons if logged out, nothing if logged in as a non-admin. Extracted so both the new shared nav and Portfolio.vue's own custom nav use the same logic instead of duplicating the admin/guest conditionals.
2. **`resources/js/Components/Public/PublicNav.vue`** — responsive top nav: site brand/logo (links to `/`), a `Library` link, and `<AuthNavCta />`. Collapses to a mobile-friendly compact layout at small viewport widths (matching the existing `md:` breakpoint conventions already used elsewhere in these pages).
3. **`resources/js/Components/Public/PublicFooter.vue`** — the fuller branded footer (logo, site name, © year, Terms/Privacy/Refund links, Support/Donate link), based on Portfolio.vue's existing richer footer markup.
4. **`resources/js/Layouts/PublicLayout.vue`** — thin wrapper: `<PublicNav />` + a default `<slot />` for page content + `<PublicFooter />`. Any new public page going forward wraps itself in this instead of hand-rolling nav+footer.

## Per-page changes

| Page | Change |
|---|---|
| `Home.vue` | Wrapped in `PublicLayout`; existing inline nav/footer markup removed. |
| `Library/Index.vue`, `Library/BookDetail.vue`, `Library/AuthorShow.vue` | Wrapped in `PublicLayout`; existing per-page duplicated nav markup removed. |
| `ProjectDetail.vue` | Wrapped in `PublicLayout` for nav+footer. Its dark hero section and "Back to Portfolio" footer link remain as page content inside the layout's slot — the layout's nav appears above them, it does not replace them. |
| `OrgPage.vue` | Uses `PublicNav` + `PublicFooter` only when `organization.white_label` is falsy. When `white_label` is true, keeps today's minimal brand-only nav and hidden footer, completely unchanged. |
| `Portfolio.vue` | Keeps its own custom nav/animation system as-is. Adds one plain `Library` link into that existing nav bar, and swaps its inline auth-button markup for `<AuthNavCta />` and its footer markup for `<PublicFooter />` (dedup only, no visible/behavioral change since the content already matches). |
| `ChapterReader.vue` | Not touched by this spec at all — no `PublicLayout`, no `PublicNav`. See "New-tab entry point" below. |

## New-tab entry point (ChapterReader)

`BookDetail.vue`'s chapter/table-of-contents links change from Inertia `<Link>` to plain `<a target="_blank" rel="noopener">`, so opening a chapter launches `ChapterReader.vue` in a fresh browser tab — the same pattern as Google Drive opening a document preview in a new tab while the file list stays where it was. This is the only place a new tab opens: once inside the reader, Previous/Next links, page-flip navigation, and autoscroll auto-advance (from the separate reading-modes spec) all continue to navigate within that same tab via normal Inertia navigation — unchanged from that spec.

## Testing approach

No backend changes, so no Pest tests. Manual browser verification:

- `Library` link appears and works from Home.vue, Portfolio.vue, non-white-labeled OrgPage.vue, ProjectDetail.vue, and all three Library browsing pages.
- White-labeled org pages show no shared nav, no `Library` link, no shared footer — behavior identical to before this change.
- Portfolio.vue's anchor nav, scroll-spy active-section highlighting, and animations all still work exactly as before, with the new `Library` link simply present alongside the anchor links.
- `PublicNav` and `PublicFooter` render correctly and responsively at mobile and desktop widths.
- Clicking a chapter from `BookDetail.vue` opens `ChapterReader.vue` in a new tab; the original `BookDetail.vue` tab remains where it was.
- Within the newly opened reader tab, Previous/Next and (once implemented) page-flip/autoscroll auto-advance all navigate within that same tab, not opening further new tabs.
