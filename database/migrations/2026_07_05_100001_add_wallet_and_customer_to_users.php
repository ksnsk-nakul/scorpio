<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'wallet_balance_paise')) {
                $table->unsignedBigInteger('wallet_balance_paise')->default(0)->after('plan');
            }
            if (! Schema::hasColumn('users', 'razorpay_customer_id')) {
                $table->string('razorpay_customer_id')->nullable()->after('wallet_balance_paise');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wallet_balance_paise', 'razorpay_customer_id']);
        });
    }
};
