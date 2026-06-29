<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('github_token')->nullable()->after('github_id');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->after('id');
            $table->boolean('is_home')->default(false)->after('status');
        });

        Schema::table('service_cards', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->after('id');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function ($t) {
            $t->dropUnique(['username']);
            $t->dropColumn(['username', 'github_token']);
        });
        Schema::table('pages', fn ($t) => $t->dropForeignIdFor(\App\Models\User::class)->dropColumn(['user_id', 'is_home']));
        Schema::table('service_cards', fn ($t) => $t->dropForeignIdFor(\App\Models\User::class)->dropColumn('user_id'));
        Schema::table('workspaces', fn ($t) => $t->dropForeignIdFor(\App\Models\User::class)->dropColumn('user_id'));
    }
};
