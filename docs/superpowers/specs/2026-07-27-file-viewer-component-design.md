# Universal File Viewer Component — Design Spec
**Date:** 2026-07-27
**Status:** Approved

---

## Context

Scorpio is a self-hosted portfolio CMS (Laravel 13, Inertia.js v2, Vue 3, SQLite). Two upcoming domains need to show non-image file previews: task/comment attachments (currently a `Media` polymorphic model with no rich preview — just image/video helpers), and a planned e-library book reader.

This spec covers **only the reusable viewer component and its backend conversion pipeline** — not the task-attachment UI wiring, not the e-library domain, not RAG, and not the edtech course builder. Those are each large enough to be their own spec/plan cycle and will *consume* this component once it exists. Building the component now, ahead of those consumers, is a deliberate choice: it's shared infrastructure both future features depend on.

### Formats in scope

| Category | Formats | Render strategy |
|---|---|---|
| Native | PNG, JPG/JPEG, GIF, SVG, MP3, WAV, MP4, MOV | Native `<img>`/`<audio>`/`<video>` |
| Text | TXT, MD, CSV | Client-side render (markdown parser, table for CSV) |
| PDF | PDF | pdf.js |
| Office | DOC, DOCX, ODT | Server-converted to PDF, then rendered via the PDF renderer |
| Comics | CBZ | Server-side extraction via PHP `ZipArchive` → paginated images |
| Comics | CBR | Server-side extraction via `unrar`/`unar` binary → paginated images |
| E-book | EPUB | epub.js |

MOV/codec compatibility (e.g. HEVC-encoded iPhone video not playing in all browsers) is a known risk, not solved here — falls back to the "preview unavailable" state described below.

---

## Existing integration point

`Media` (`app/Models/Media.php`) is already polymorphic (`mediable` → `Task`, `Comment`) with `mime_type`, `disk`, `path`, `isImage()`, `isVideo()`. The viewer takes a `Media` record and needs no new attachment model — it's a rendering layer on top of what exists.

---

## Frontend architecture

`<FileViewer :media="media" mode="embedded|fullscreen" />` is the single entry point. It dispatches by `mime_type`/extension to one renderer component:

- `ImageRenderer`, `VideoRenderer`, `AudioRenderer` — native elements
- `PdfRenderer` — pdf.js
- `TextRenderer` — txt/md/csv
- `EpubRenderer` — epub.js
- `ComicRenderer` — paginated image viewer, shared by CBZ and CBR (both resolve to a list of page image URLs after server processing)
- `OfficeRenderer` — thin wrapper that points `PdfRenderer` at the converted file

Every renderer implements the same prop contract: `fileUrl`, `mimeType`, `meta` (page count, dimensions, etc. — renderer-specific). This is the "pluggable renderer" requirement: a new format later means one new renderer + one dispatch entry, no changes to `FileViewer` itself.

### Modes

- **Embedded**: inline in a task/comment card. Default state shows content only, no chrome. On hover, a toolbar fades in as a gradient overlay (top: filename + page position + expand button; bottom: prev/next for paginated content). A thin, always-visible progress indicator (page position) stays present even without hover, so orientation isn't lost.
- **Fullscreen**: opened via the expand button, uses the browser Fullscreen API. Same hover-to-reveal pattern, fuller toolbar (search, zoom, download, close) plus arrow overlays for page navigation.

### Theming

Two independent theme layers:
1. **Chrome** — the admin UI has no dark mode today (no `dark:` classes, no theme variables anywhere in `resources/js`); it's a fixed light palette (slate/blue, per `ImagePicker.vue` and similar components). The viewer chrome follows that same fixed palette for consistency — there's no "automatic" theme-following to build, just matching the existing look.
2. **Reader theme** — for text-based renderers only (`TextRenderer`, `EpubRenderer`, `OfficeRenderer`): a picker for sepia/dark/light background plus font-size and line-spacing, tucked in an "Aa" toolbar menu. Persisted per-browser via `localStorage`. This is the one place dark styling exists in the app, and it's scoped to reading content only, not the chrome or the rest of the admin UI.

---

## Backend conversion pipeline

Two formats need server-side pre-processing before they're viewable:

- **Office → PDF**: `ConvertOfficeDocumentJob` (queued) shells out to `soffice --headless --convert-to pdf`, writing to `storage/app/conversions/{media_id}.pdf`.
- **CBR → images**: `ExtractComicArchiveJob` (queued) uses the `unrar`/`unar` CLI binary to extract pages to `storage/app/comics/{media_id}/page-*.jpg`. CBZ uses PHP's built-in `ZipArchive` (no external binary needed) via the same job/output shape, so `ComicRenderer` doesn't care which archive type it came from.

**Timing**: eager — both jobs dispatch immediately on upload, not on first view. A `status` column is added to `media` (`ready` default; `pending`/`processing`/`failed` for the two convertible types). The viewer shows a "processing…" state while status isn't `ready`, and polls/refreshes until it is. Because output is cached to storage, conversion only ever happens once per file.

**Server dependencies**: this introduces a hard requirement on `soffice` (LibreOffice headless) and `unrar`/`unar` being installed on the host — needs to be documented in `docs/INSTALLATION.md` / `docs/CONFIGURATION.md` as a prerequisite (both Docker and bare-metal paths), and should be checked into the existing Docker images if not already present.

---

## Error handling

- Conversion job failure (binary missing, corrupt archive, LibreOffice crash) → `media.status = 'failed'`, reason logged; viewer shows "preview unavailable — download to view" instead of a blank/broken state.
- Unsupported or unrecognized MIME types get the same fallback rather than attempting a renderer.

---

## Testing

- Pest feature tests for `ConvertOfficeDocumentJob` and `ExtractComicArchiveJob`: mock the shell calls, assert `status` transitions (`pending` → `processing` → `ready`/`failed`) and output file paths.
- Vue component tests per renderer: prop handling, loading state, error/fallback state. `FileViewer` dispatch logic tested separately from individual renderers.

---

## Out of scope (future specs)

- Wiring `<FileViewer>` into the task/comment attachment UI (upload flow, attachment list display)
- E-library domain (book model, reading progress, library browsing UI)
- RAG over the e-library via a Laravel MCP server
- EdTech course generator from a compressed structured folder
