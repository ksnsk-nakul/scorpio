# E-Library — Bulk EPUB Ingestion, Chapter Reader, Public Browsing — Design Spec
**Date:** 2026-07-30
**Status:** Approved

---

## Context

Scorpio is a self-hosted portfolio CMS (Laravel 13, Inertia.js v2, Vue 3, SQLite). This spec adds an e-library: admins/editors bulk-upload EPUB files, which get parsed into structured books with chapters and images, browsable and readable by anyone visiting the site — free, no purchase flow yet.

This builds on the universal file viewer component (`docs/superpowers/specs/2026-07-27-file-viewer-component-design.md`), but does **not** reuse its EPUB rendering path. That component's `EpubRenderer` reads raw `.epub` files client-side via epub.js; this spec instead parses EPUBs server-side into a first-class `Book`/`Chapter` data model with a custom reader, because a DB-backed model is what future RAG chunking and reading-progress tracking need to build on.

**Explicitly out of scope** (each gets its own future spec):
- RAG search over the library (embeddings, chunking, Laravel MCP server) — depends on library content existing first
- **Reader accounts + reading progress + personal shelves** — browsing stays anonymous/public in this spec, but a future spec covers: sign-in required to actually read a book (browsing remains free), per-book reading progress with "continue reading" suggestions, and user-defined shelves/categories (e.g. "reading," "plan to read") with filtering — all three depend on a new reader-account system (distinct from the existing admin/editor/viewer CMS auth) that doesn't exist yet, so this needs its own spec once the core library has real content to attach accounts to
- Purchases, pricing, paid access — all books are free for now
- Multi-author books (one primary author per book for v1)

---

## Data model

### `authors`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `name` | string | |
| `slug` | string, unique | for `/library/authors/{slug}` |
| `bio` | text, nullable | not extracted from EPUBs (rarely present/reliable) — manually editable later |
| timestamps | | |

### `books`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `author_id` | FK → authors | matches the first `dc:creator` entry in the EPUB |
| `title` | string | from `dc:title` |
| `slug` | string, unique | for `/library/books/{slug}` |
| `description` | text, nullable | from `dc:description` |
| `cover_path` | string, nullable | extracted cover image, stored directly (see Storage section) |
| `language` | string, nullable | from `dc:language` |
| `publisher` | string, nullable | from `dc:publisher` |
| `published_date` | date, nullable | from `dc:date` |
| `subject` | string, nullable | comma-separated tags from `dc:subject` entries — not a normalized model |
| `source_epub_path` | string | original uploaded file, kept for re-parsing and direct download |
| `status` | string, default `pending` | `pending` / `processing` / `ready` / `failed` |
| `status_reason` | string, nullable | parse error message when `failed` |
| `uploaded_by` | FK → users | |
| timestamps | | |

### `chapters`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `book_id` | FK → books | |
| `title` | string, nullable | from the chapter's XHTML `<title>` or spine `<itemref>` label, when present |
| `content` | longtext | chapter XHTML body, `<img src>` rewritten to point at extracted images |
| `sort_order` | unsigned int | position in the EPUB spine |
| timestamps | | |

### Storage (no `Media` model involvement)
Chapter images and covers are extracted straight to disk during parsing — `storage/app/public/books/{book_id}/cover.{ext}` and `storage/app/public/books/{book_id}/images/{filename}` — not created as `Media` rows. These come from programmatic extraction, not a user-driven upload through `MediaService`'s mime-validation pipeline, so a parallel, simpler storage convention (matching the precedent set by `ComicArchiveExtractionService`'s direct-to-disk comic pages) is a better fit than forcing them through a model designed for validated user uploads.

---

## EPUB parsing

A new `App\Services\EpubParsingService`, using PHP's built-in `ZipArchive` and `SimpleXMLElement` — no third-party EPUB-parsing Composer package. Steps:
1. Open the zip, read `META-INF/container.xml` to locate the OPF package file.
2. Parse the OPF's `<metadata>` block (Dublin Core namespace) for title/creator/description/language/publisher/date/subject.
3. Parse the OPF's `<manifest>` (id → href/media-type map) and `<spine>` (ordered list of manifest ids) to determine chapter reading order.
4. For each spine item: read the XHTML content, find `<img>` tags, resolve their `src` paths relative to the XHTML file's location within the zip, extract those image bytes to `books/{book_id}/images/`, and rewrite the `src` attributes to the new public URLs. Store the resulting HTML as the chapter's `content`. Chapter `title` comes from the XHTML's `<title>` tag when present, else the spine `<itemref>`'s linked manifest label when present, else falls back to `"Chapter {sort_order + 1}"`.
5. Locate the cover image (OPF `<meta name="cover">` or manifest item with `properties="cover-image"`, falling back to the first image in the manifest if neither is present) and extract it to `books/{book_id}/cover.{ext}`.

This mirrors the approach and precedent already used for CBZ extraction in the file-viewer component.

### Processing job
`ParseEpubBookJob` (queued), one per uploaded file: sets `status: processing`, runs `EpubParsingService`, creates/matches the `Author` by exact-name string match (case-insensitive), creates the `Book` and its `Chapter` rows, sets `status: ready`. On any exception, sets `status: failed` with `status_reason`. Same async lifecycle pattern as `ConvertOfficeDocumentJob`/`ExtractComicArchiveJob`.

Book and author slugs are generated from title/name (lowercase, hyphenated) with a numeric suffix appended on collision (`the-hobbit`, `the-hobbit-2`, ...) — a known v1 simplification is that author matching is exact-name-only, so name variants (e.g. "J.K. Rowling" vs "J. K. Rowling") across different EPUBs will create separate `Author` rows rather than being merged; manual admin cleanup can fix this later if it becomes a real problem.

---

## Bulk upload

An admin page (`admin,editor` role, matching existing content-management permissions) with a multi-file drop zone (`accept=".epub"`, `multiple`). Each selected file is uploaded as its own request, creating its own `Book` row (`status: pending`) and dispatching its own `ParseEpubBookJob` — so one corrupt file doesn't block the rest of the batch. The upload UI lists every file in the batch with live status, polling each book's status endpoint (same polling pattern just built for the file-viewer's `Media` status) until it resolves to `ready` or `failed`; failed entries show the reason inline.

---

## Public UI

- **Library index** (`/library`, no auth) — paginated grid of book covers, titles, authors.
- **Book detail** (`/library/books/{slug}`) — cover, description, author (linked), chapter table of contents in spine order.
- **Chapter reader** (`/library/books/{slug}/chapters/{sort_order}`) — renders `chapter.content`, prev/next chapter navigation, reuses the existing `useReaderTheme` composable (white/black/sepia/dark-sepia, built for the file-viewer's `TextRenderer`) for consistent reading theming — no new theme system.
- **Author page** (`/library/authors/{slug}`) — books by that author.

## Admin UI

A book management list mirroring the existing Pages/Projects admin list pattern: bulk-upload entry point, per-book edit (title/author/description can be manually overridden after parsing), delete, and a visible `failed` state with the parse error and a retry action (re-dispatches `ParseEpubBookJob` against the already-stored `source_epub_path`).

---

## Error handling

Parse failures (corrupt zip, missing/malformed OPF, unreadable metadata) set `status: failed` with a specific `status_reason` — the book simply doesn't appear in the public library until fixed or retried. No partial books: a `Book` row exists as soon as upload starts (so the admin UI can show its status), but its chapters/cover only populate once parsing fully succeeds.

## Testing

- Pest unit tests for `EpubParsingService` against real minimal EPUB fixtures (built the same way as the manual file-viewer testing fixtures earlier — a hand-constructed valid EPUB with known metadata/chapters/images, to assert exact extraction correctness).
- Pest feature tests for the bulk upload endpoint (per-file `Book`/job creation, one failure not blocking others) and the `ParseEpubBookJob` status lifecycle (`pending` → `processing` → `ready`/`failed`).
- Vue component tests for the chapter reader (content rendering, prev/next navigation, reader-theme reuse) and library browse/pagination.

## Out of scope (future specs)

- RAG search over the library via a Laravel MCP server
- Reading progress / "continue reading"
- Purchases, pricing, paid access
- Multi-author books
