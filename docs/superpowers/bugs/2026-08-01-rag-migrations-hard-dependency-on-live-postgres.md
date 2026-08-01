# Bug: RAG migrations crash artisan migrate/test without a live Postgres+pgvector connection

**Found:** 2026-08-01, during post-merge verification of `feature/e-library-backend` → `main`.
**Status:** Resolved 2026-08-01, in `feature/e-library-backend`.

## Problem

The three RAG migrations call `DB::connection('rag')` / `Schema::connection('rag')` directly, unconditionally, during every `artisan migrate` (and therefore every `RefreshDatabase` test run):

- `database/migrations/2026_07_31_100001_create_book_chunks_table.php`
- `database/migrations/2026_07_31_100002_create_chat_threads_table.php`
- `database/migrations/2026_07_31_100003_create_chat_messages_table.php`

`config/database.php`'s `rag` connection defaults to `127.0.0.1:5432` / `postgres` / `postgres` (`DB_RAG_HOST`, `DB_RAG_PORT`, `DB_RAG_DATABASE`, `DB_RAG_USERNAME`, `DB_RAG_PASSWORD`, `DB_RAG_SEARCH_PATH`, `DB_SSLMODE`). `phpunit.xml` only overrides `DB_RAG_SEARCH_PATH` to `rag_test,public` — it does not supply host/credentials.

**Result:** in any environment whose `.env` doesn't set real `DB_RAG_*` values pointing at a reachable Postgres instance with the `vector` extension available, `php artisan migrate` throws a connection exception partway through the batch, and `php artisan test` fails almost entirely (not just RAG-specific tests) — e.g. `Tests\Feature\ExampleTest` fails with `PDOException: SQLSTATE[HY000]: General error: 1 no such table: announcements` because the migration batch never completes.

Reproduced on the primary repo checkout at `/Users/nakul/Herd/PortFolio` (main), which has no `DB_RAG_*` in `.env`. The feature worktree (`.worktrees/e-library-backend`) has real Supabase RAG credentials configured, which is why its test runs showed 135/136 passing (1 pre-existing unrelated failure) — that green run was masking this gap.

## Impact

- Any fresh clone, CI runner, or teammate's machine without Supabase/pgvector credentials cannot run `artisan migrate` or the test suite at all, post-merge to `main`.
- This is broader than a RAG-feature-scoped failure — it takes down the whole app's migrate/test pipeline.

## Where this was found

Post-merge verification step of `docs/superpowers/plans/2026-07-31-template-system-architecture-and-admin.md` / `-animejs-public-template.md` / `-minimalist-public-template.md`, immediately before pushing `main` to `origin`. See session progress file: `docs/superpowers/progress/2026-07-31-81875e98-template-system-and-animejs.md`.

## Resolution

Added `App\Support\RagConnectionGuard::available()` — a small helper that attempts `DB::connection('rag')->getPdo()` once per process (catching any `Throwable`, logging a warning, and caching the boolean result). All three migrations now call it first in both `up()` and `down()` and return early (no-op) when it's `false`, instead of letting the connection exception propagate.

This alone fixed `artisan migrate`, but four existing RAG feature tests (`ChatServiceTest`, `LibraryChatControllerTest`, `RagSchemaTest`, `RetrievalServiceTest`, `IndexBookChunksJobTest`) still tried to read/write `rag`-connection tables directly and would fail with a raw `QueryException` once those tables were skipped. Added a `beforeEach` guard to each, calling `$this->markTestSkipped(...)` when `RagConnectionGuard::available()` is `false`, so they skip cleanly instead of failing.

Added `tests/Unit/RagConnectionGuardTest.php` covering both the "unreachable" and "caches after first check" behavior directly (via `config()` override + `DB::purge('rag')` + a reflection-based cache reset, since the guard's cache is process-lifetime by design).

**Verified:**
- `DB_RAG_HOST=127.0.0.1 DB_RAG_PORT=1 DB_RAG_USERNAME=nobody DB_RAG_PASSWORD=nobody php artisan migrate --force` against a scratch SQLite file — full batch completes, including the three `rag` migrations (skipped in ~0.02ms each).
- Same env vars with `php artisan test` — 136 tests, 114 passed, 21 skipped (the RAG-dependent ones), 1 failed. That 1 failure (`ExampleTest`) is pre-existing and unrelated to RAG — confirmed by running it in isolation with real Supabase credentials too, where it fails identically.
- `php artisan test` with real Supabase credentials (no override) — 138 tests (136 + 2 new), 137 passed, same 1 pre-existing unrelated failure. No regression to the working case.
