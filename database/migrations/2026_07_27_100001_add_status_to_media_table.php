<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('status')->default('ready')->after('alt_text');
            $table->string('status_reason')->nullable()->after('status');
            $table->json('page_manifest')->nullable()->after('status_reason');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['status', 'status_reason', 'page_manifest']);
        });
    }
};
