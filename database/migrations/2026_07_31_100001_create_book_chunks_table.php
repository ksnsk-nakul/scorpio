<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('rag')->statement('CREATE EXTENSION IF NOT EXISTS vector');

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
        DB::connection('rag')->statement('DROP TABLE IF EXISTS book_chunks');
    }
};
