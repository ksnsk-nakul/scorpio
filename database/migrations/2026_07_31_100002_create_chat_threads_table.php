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
        if (Schema::connection('rag')->hasTable('chat_threads')) {
            return;
        }

        Schema::connection('rag')->create('chat_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('rag')->dropIfExists('chat_threads');
    }
};
