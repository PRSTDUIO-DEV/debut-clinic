<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('invoice_item_id')->constrained('invoice_items')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('type', ['doctor_fee', 'staff_commission', 'referral']);
            $table->decimal('base_amount', 12, 2);
            $table->decimal('rate', 6, 2)->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('commission_date');
            $table->boolean('is_paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['branch_id', 'commission_date']);
            $table->index(['user_id', 'commission_date']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_transactions');
    }
};
