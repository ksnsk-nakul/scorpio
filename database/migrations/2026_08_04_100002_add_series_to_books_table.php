<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->after('author_id')->constrained('series')->nullOnDelete();
            $table->integer('volume_number')->nullable()->after('series_id');
            $table->index(['series_id', 'volume_number']);
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['series_id', 'volume_number']);
            $table->dropConstrainedForeignId('series_id');
            $table->dropColumn('volume_number');
        });
    }
};
