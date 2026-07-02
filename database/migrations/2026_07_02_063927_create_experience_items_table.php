<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('experience_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period', 30);
            $table->string('title', 120);
            $table->string('company', 120)->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_seeded')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experience_items');
    }
};
