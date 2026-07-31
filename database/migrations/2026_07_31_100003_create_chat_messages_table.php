<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // See the comment in 2026_07_31_100001_create_book_chunks_table.php: this guard
        // exists because RefreshDatabase re-runs every migration against a fresh SQLite
        // db per test run, but this table lives on the persistent external `rag` connection.
        if (Schema::connection('rag')->hasTable('chat_messages')) {
            return;
        }

        Schema::connection('rag')->create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('chat_threads')->cascadeOnDelete();
            $table->string('role');
            $table->text('content');
            $table->json('citations')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('rag')->dropIfExists('chat_messages');
    }
};
