<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // notes|task|slides|demo_problem|demo_try_it|demo_solution|video
            $table->longText('content')->nullable();
            $table->string('download_policy')->default('view_only'); // downloadable|view_only
            $table->string('status')->default('ready'); // ready|generating|not_generated|failed
            $table->timestamps();
            $table->unique(['topic_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
