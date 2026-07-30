# Library RAG Chat — Design Spec
**Date:** 2026-07-30
**Status:** Approved

---

## Context

Once the e-library (`docs/superpowers/specs/2026-07-30-e-library-design.md`) has real books with parsed chapters, this spec adds an "ask your library" chat: questions get answered by retrieving relevant chapter excerpts and generating a cited answer via an LLM. This spec depends on the e-library existing and having content to index — it should not be implemented before that.

The original idea (from earlier brainstorming) was to expose this as a Laravel MCP server for external AI clients. That's deliberately **not** what this spec builds: the user chose an in-app chat instead. The retrieval logic still lives in its own service class rather than being embedded in a controller, so exposing it as an MCP tool later is an additive change, not a rewrite — but MCP itself is out of scope here.

**Explicitly out of scope:**
- MCP server exposure (may follow once the in-app chat is proven)
- Public/anonymous access to the chat (see Access section — ties to the deferred reader-accounts spec)
- Any provider other than Gemini for embeddings/generation

---

## Provider

**Google Gemini API** for both retrieval embeddings and answer generation — a single `GEMINI_API_KEY`, no second provider account. Calls go through Laravel's `Http` client directly (no new SDK dependency), matching this app's established minimal-dependency pattern (same approach used for LibreOffice/unrar shelling out, hand-rolled EPUB parsing). The specific embedding model (and its output dimension, needed for the pgvector column definition) is confirmed at implementation time against Gemini's current model lineup rather than assumed here.

---

## Cross-database reality check

Books/chapters live in the app's primary SQLite database; this spec's tables live in a new named Postgres connection (`rag`) pointed at Supabase:

```
DB_RAG_CONNECTION=pgsql
DB_RAG_HOST=db.hocphrdjgvhxgtraynnw.supabase.co
DB_RAG_PORT=5432
DB_RAG_DATABASE=postgres
DB_RAG_USERNAME=postgres
DB_RAG_PASSWORD=<set locally, never committed>
```

**These two databases cannot have real foreign keys between them.** `book_chunks.book_id`/`chapter_id` are plain integers with no DB-enforced referential integrity against SQLite's `books`/`chapters` tables — consistency is maintained at the application level (a book/chapter deletion dispatches a job to remove its orphaned chunks). This is a structural constraint of splitting the app across two databases, not an oversight to fix later.

---

## Data model

All in the `rag` Postgres connection (migrations run with `--database=rag`), except `chat_threads.user_id` which references the primary DB's `users` table by ID only (same no-cross-DB-FK caveat as above).

### `book_chunks`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `book_id` | integer | no FK — references primary DB `books.id` |
| `chapter_id` | integer | no FK — references primary DB `chapters.id` |
| `chunk_index` | unsigned int | order within the chapter |
| `content` | text | plain text (HTML stripped) |
| `embedding` | `vector` | pgvector extension column; dimension confirmed at implementation |
| `created_at` | timestamp | |

### `chat_threads`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `user_id` | integer | no FK — references primary DB `users.id` |
| `title` | string, nullable | auto-set from the first question |
| timestamps | | |

### `chat_messages`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `thread_id` | FK → `chat_threads` | real FK — both tables are in the same `rag` connection |
| `role` | string | `user` / `assistant` |
| `content` | text | |
| `citations` | JSON, nullable | `[{book_id, chapter_id, book_title, chapter_title}]`, assistant messages only |
| `created_at` | timestamp | |

---

## Indexing pipeline

When a `Book` reaches `status: ready` (per the e-library spec), a queued `IndexBookChunksJob` runs: strip each chapter's HTML to plain text, group paragraphs into chunks up to ~500–800 tokens (splitting at paragraph boundaries, not mid-sentence), call the Gemini embeddings endpoint per chunk (batched where the API supports it), store results in `book_chunks`. Re-runs (replacing existing chunks for that book) when a book's chapters are manually edited.

---

## Chat flow

1. A logged-in CMS user (admin/editor/viewer) asks a question, in a new or existing thread.
2. Embed the question via Gemini; query `book_chunks` via pgvector cosine-similarity for the top ~6 matches.
3. Assemble a Gemini prompt: system instructions (answer only from the provided excerpts, cite book/chapter, say plainly when the library doesn't cover something), the retrieved chunks with book/chapter labels, prior messages in the thread (for multi-turn context), the new question.
4. Call Gemini for the answer; persist both the question and answer as `chat_messages`, with citations recording which chunks were used.
5. UI renders the answer with citations linking to `/library/books/{slug}/chapters/{sort_order}`.

---

## Access

Restricted to logged-in CMS users (`admin`,`editor`,`viewer` — the existing role middleware) for now. The library itself is public/anonymous per the e-library spec, but this chat is not exposed to anonymous visitors, since each question costs a real API call and there's no visitor-identity or rate-limiting system yet — that's tied to the deferred reader-accounts spec, which is the natural place to revisit public access.

## UI

Admin-only chat page (`/admin/library/chat`): thread list sidebar, message thread view, input box — standard chat layout, no new design system needed beyond what the admin UI already uses.

## Testing

- Pest tests for the chunking logic (paragraph-grouping boundaries, size cap behavior)
- Pest tests for the retrieval service with mocked Gemini embedding responses and a mocked/seeded `rag` Postgres connection
- Pest tests for chat message persistence and citation storage
- All Gemini HTTP calls mocked in tests — no real API calls in CI

## Out of scope (future specs)

- MCP server exposure of the same retrieval layer
- Public/anonymous chat access (depends on reader accounts)
- Any non-Gemini provider
