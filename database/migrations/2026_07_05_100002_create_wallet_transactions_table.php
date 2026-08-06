<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('wallet_transactions')) {
            return;
        }

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('txn_ref', 30)->unique();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('payer_name')->nullable();
            $table->text('payer_email')->nullable();
            $table->enum('type', ['credit', 'debit']);
            $table->unsignedBigInteger('amount_paise');
            $table->unsignedBigInteger('balance_after_paise');
            $table->enum('category', ['topup', 'subscription', 'refund', 'adjustment'])->default('topup');
            $table->string('note')->nullable();
            $table->string('razorpay_payment_id')->nullable();
            $table->string('razorpay_order_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('txn_ref');
            $table->index('recipient_user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
