<?php

use App\Support\RagConnectionGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Environments without real DB_RAG_* credentials (a fresh clone, CI, a teammate's
        // machine) must not have artisan migrate/test fail here — skip cleanly instead.
        if (! RagConnectionGuard::available()) {
            return;
        }

        // The `rag` connection's search_path names the schema these tables should live
        // in ('public' in production, 'rag_test' in tests — see phpunit.xml). Postgres
        // won't create a table in a schema that doesn't exist yet, and "CREATE TABLE IF
        // NOT EXISTS" alone can't fix that — so ensure the schema itself exists first.
        // This is what makes a fresh clone's test suite work with zero manual setup.
        $schema = explode(',', config('database.connections.rag.search_path'))[0];
        if ($schema !== 'public') {
            DB::connection('rag')->statement("CREATE SCHEMA IF NOT EXISTS {$schema}");
        }

        DB::connection('rag')->statement('CREATE EXTENSION IF NOT EXISTS vector');

        // IF NOT EXISTS is intentional here (unlike the app's ordinary same-connection
        // migrations): the test suite's RefreshDatabase resets the default SQLite
        // connection fresh on every run, which re-executes every migration file
        // including this one — but this table lives on the persistent, external `rag`
        // Postgres (Supabase) connection, which is never dropped between test runs.
        // 768 = Gemini gemini-embedding-001 with outputDimensionality: 768 (see GeminiClient).
        DB::connection('rag')->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS book_chunks (
                id BIGSERIAL PRIMARY KEY,
                book_id BIGINT NOT NULL,
                chapter_id BIGINT NOT NULL,
                chunk_index INTEGER NOT NULL,
                content TEXT NOT NULL,
                embedding VECTOR(768) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT now()
            )
        SQL);

        DB::connection('rag')->statement('CREATE INDEX IF NOT EXISTS book_chunks_book_id_index ON book_chunks (book_id)');
    }

    public function down(): void
    {
        if (! RagConnectionGuard::available()) {
            return;
        }

        DB::connection('rag')->statement('DROP TABLE IF EXISTS book_chunks');
    }
};
