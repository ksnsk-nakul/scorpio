# Chapter Reader Reading Modes — Design Spec

## Purpose

The public e-library Chapter Reader (`resources/js/Pages/Public/Library/ChapterReader.vue`) currently supports only one reading mode: a single continuous vertical scroll, with theme (White/Sepia/Dark Sepia/Black) and font-size (A−/A+) controls inline in the toolbar. This adds three more reading modes — horizontal page-flip, vertical page-flip, and autoscroll — behind a unified settings drawer, matching the feel of dedicated e-reader apps.

This is a pure frontend feature. No backend, route, controller, or database changes are required — `LibraryController::chapter()` already returns everything needed (`chapter.content`, `hasPrev`, `hasNext`).

## Out of scope

- Anime.js / animation polish (drawer open/close, page-flip transitions, autoscroll easing). Tracked as a separate follow-up pass after this feature and the Plan 3 manual-verification wrap-up are both done.
- Reading progress persistence across devices/accounts (already a separately deferred spec).
- Any change to EPUB parsing, chapter content, or backend endpoints.

## Reading modes

1. **Scroll** (default) — today's existing behavior, unchanged: one continuous document, normal vertical scrolling.
2. **H-Page** — chapter content is split into fixed-width "pages" using a CSS multi-column layout; user flips left/right through them.
3. **V-Page** — chapter content is split into fixed-height "pages" using CSS scroll-snap; user flips up/down through them.
4. **Autoscroll** — same continuous layout as Scroll mode, but the page auto-scrolls at a chosen speed until paused.

## Settings drawer

- Toolbar simplifies to `← Book Title` on the left and a single `⚙` icon button on the right. All theme, font-size, and reading-mode controls move out of the inline toolbar into a **side drawer** (slides in from the right, dims page content behind it), opened by the `⚙` button.
- Drawer sections, top to bottom:
  1. **Theme** — the existing White / Sepia / Dark Sepia / Black buttons, unchanged in behavior.
  2. **Font size** — the existing A− / A+ buttons, unchanged in behavior.
  3. **Reading Mode** — 4 buttons: Scroll / H-Page / V-Page / Autoscroll.
  4. **Autoscroll speed** — only rendered when mode is `autoscroll`: Slow / Medium / Fast preset buttons, plus a Play/Pause toggle.
- The existing "← Previous / Next →" links at the bottom of the chapter content stay exactly as they are today, in every mode, as a manual fallback that always works regardless of reading mode.

## State: `useReaderMode` composable

New file: `resources/js/composables/useReaderMode.js`, a sibling to the existing `resources/js/composables/useReaderTheme.js` (same module-singleton pattern, own localStorage key — kept separate from the theme composable since it's a distinct concern).

```js
// Shape (illustrative, not final implementation):
// state: { mode: 'scroll'|'h-page'|'v-page'|'autoscroll', autoscrollSpeed: 'slow'|'medium'|'fast' }
// persisted to localStorage key 'reader-mode-prefs', loaded on first import
// isPlaying: non-persisted ref, always starts false on a fresh module load
//
// exports: mode, autoscrollSpeed, isPlaying, setMode(mode), setAutoscrollSpeed(speed), play(), pause(), togglePlay()
```

Must be imported via the lowercase `@/composables/useReaderMode` path everywhere (matching the established project lesson: a capital-`C` `@/Composables` import previously created a second, independently-stated module instance for `useReaderTheme` — the same bug class must not recur here).

**Persistence:** `mode` and `autoscrollSpeed` persist across chapters and sessions, exactly like theme/font-size already do. `isPlaying` is intentionally NOT persisted — see the sessionStorage resume mechanism below for how autoscroll survives a full page navigation instead.

## Page-flip mechanics (H-Page / V-Page)

- **H-Page:** the chapter content div gets `column-width: 100vw; column-gap: 0` (CSS multi-column). The container is translated horizontally by `currentPage * 100vw` via a CSS transform. `totalPages` is derived by measuring `scrollWidth / viewportWidth` after content mounts (and after theme/font-size changes, since those change layout and must trigger re-measurement).
- **V-Page:** the chapter content div gets `scroll-snap-type: y mandatory`, with `scroll-snap-align: start` on 100vh-tall page sections. Navigation uses `scrollTo` to the target page's snap point.
- **Input handling (both):** a single `goToPage(delta)` function is wired to three input sources simultaneously:
  - Click/tap on the left or right third of the screen (top/bottom third for V-Page) calls `goToPage(-1)` / `goToPage(+1)`.
  - Arrow keys (←/→ for H-Page, ↑/↓ for V-Page) call the same function.
  - Touch swipe (a `touchstart`/`touchend` delta past a small threshold) calls the same function.
- **Known accepted limitation:** an image or other embed can visually split across a column/page boundary in H-Page mode. This is inherent to CSS-column-based pagination and is accepted as-is (same limitation exists in most ebook readers built this way) — not something this feature attempts to solve.

## Chapter-boundary navigation

- **Forward, page-flip:** calling `goToPage(+1)` while already on the last page: if `hasNext`, Inertia-visits the next chapter's route, landing on page 1 there. If not `hasNext`, no-op (already at the true end of the book).
- **Backward, page-flip:** calling `goToPage(-1)` while on page 1: if `hasPrev`, Inertia-visits the previous chapter's route — landing on **page 1** of that chapter, not its true last page. We cannot know a chapter's page count without first rendering it, so this is an accepted simplification, not a bug to fix later.
- **Forward, autoscroll:** when the scroll position reaches the bottom of the page (`scrollTop + clientHeight >= scrollHeight`, with a small epsilon) and `hasNext` is true: write a one-shot `sessionStorage` flag (e.g. `library-autoscroll-resume = <speed>`), then Inertia-visit the next chapter. On mount, `ChapterReader.vue` checks for that flag, and if present, clears it and calls `play()` immediately at the stored speed — so autoscroll keeps flowing across the chapter boundary without the user needing to press Play again. If `hasNext` is false, autoscroll simply stops at the bottom; nothing else happens.
- **Scroll mode:** unaffected — identical to today's behavior, no auto-advance.

## Autoscroll mechanics

- Implemented via `requestAnimationFrame`, calling `window.scrollBy(0, pxPerFrame)` each frame while `isPlaying` is true. `pxPerFrame` is derived from the `autoscrollSpeed` preset (three fixed constants — Slow/Medium/Fast — no numeric slider, so there's no arbitrary value to validate or persist).
- Any manual scroll, click, or touch event anywhere on the page immediately sets `isPlaying = false` (auto-pause on interaction), so autoscroll never fights the reader's own input. The user resumes via the drawer's Play button, or automatically via the forward-boundary resume mechanism above.

## Components touched/created

- **Create:** `resources/js/composables/useReaderMode.js`
- **Create:** `resources/js/Components/ReaderSettingsDrawer.vue`
- **Modify:** `resources/js/Pages/Public/Library/ChapterReader.vue` — simplified toolbar (gear icon replaces inline buttons), mode-conditional content rendering (scroll/autoscroll share the same continuous layout; h-page/v-page get the paginated wrapper), boundary-navigation logic, sessionStorage resume check on mount.

## Testing approach

No backend changes, so no Pest tests. Verification is manual browser testing (matching how Plan 3's public UI was verified), covering:

- All 4 modes render and are switchable from the drawer.
- Mode and autoscroll speed persist across a chapter navigation and a hard page reload.
- H-Page and V-Page: click zones, arrow keys, and touch swipe (via the browser's touch emulation or a real device) all navigate pages correctly.
- Forward auto-advance into the next chapter works for H-Page, V-Page, and Autoscroll; autoscroll resumes playing automatically after the auto-advance.
- Reaching the true last chapter's end stops cleanly in every mode (no crash, no infinite navigation loop).
- Backward page-flip past page 1 lands on the previous chapter's page 1.
- Autoscroll auto-pauses on manual scroll/click/tap, and resumes correctly via the drawer's Play button.
- The existing Previous/Next links at the bottom still work, unchanged, in every mode.
- Theme and font-size controls still work identically now that they live in the drawer.
- Drawer opens/closes correctly, and doesn't visually break on mobile viewport widths.
