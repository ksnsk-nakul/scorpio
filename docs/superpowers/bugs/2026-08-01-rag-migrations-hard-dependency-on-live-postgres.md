# Bug: RAG migrations crash artisan migrate/test without a live Postgres+pgvector connection

**Found:** 2026-08-01, during post-merge verification of `feature/e-library-backend` → `main`.
**Status:** Open. Owner: the RAG chat session (per user, being handled there) — this file exists so it isn't lost.

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

## Suggested fix (not yet implemented)

Guard the three migrations so they no-op or skip cleanly when the `rag` connection isn't configured/reachable (e.g. check `config('database.connections.rag.host')` is set and/or wrap the connection attempt, skipping with a clear message when unavailable) — so `artisan migrate` and the test suite succeed in environments that don't have RAG set up, while still working correctly wherever real credentials are present.

## Where this was found

Post-merge verification step of `docs/superpowers/plans/2026-07-31-template-system-architecture-and-admin.md` / `-animejs-public-template.md` / `-minimalist-public-template.md`, immediately before pushing `main` to `origin`. See session progress file: `docs/superpowers/progress/2026-07-31-81875e98-template-system-and-animejs.md`.
