<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('disbursement_no', 30)->unique();
            $table->date('disbursement_date');
            $table->enum('type', ['salary', 'utilities', 'rent', 'tax', 'supplier', 'other']);
            $table->decimal('amount', 14, 2);
            $table->enum('payment_method', ['cash', 'transfer', 'check', 'credit_card'])->default('transfer');
            $table->string('vendor', 150)->nullable();
            $table->string('reference', 100)->nullable();
            $table->foreignId('related_po_id')->nullable()->constrained('purchase_orders')->cascadeOnUpdate()->nullOnDelete();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'approved', 'paid', 'cancelled'])->default('draft');
            $table->foreignId('requested_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['branch_id', 'disbursement_date']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursements');
    }
};
