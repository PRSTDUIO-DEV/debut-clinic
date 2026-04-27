<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('method', ['cash', 'credit_card', 'transfer', 'member_credit', 'coupon']);
            $table->decimal('amount', 12, 2);
            $table->foreignId('bank_id')->nullable()->constrained('banks')->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('mdr_rate', 5, 2)->nullable();
            $table->decimal('mdr_amount', 12, 2)->nullable();
            $table->string('reference_no', 50)->nullable();
            $table->date('payment_date');
            $table->timestamps();

            $table->index(['branch_id', 'payment_date']);
            $table->index(['branch_id', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
