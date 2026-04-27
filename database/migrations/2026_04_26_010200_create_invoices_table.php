<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('invoice_number', 30)->unique();
            $table->date('invoice_date');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('total_cogs', 12, 2)->default(0);
            $table->decimal('total_commission', 12, 2)->default(0);
            $table->decimal('gross_profit', 12, 2)->default(0);
            $table->enum('status', ['draft', 'paid', 'partial', 'voided', 'refunded'])->default('draft');
            $table->foreignId('cashier_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'invoice_date']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
