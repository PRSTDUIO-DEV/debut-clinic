<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('tax_invoice_no', 30)->unique();
            $table->date('issued_at');
            $table->string('customer_name', 200);
            $table->string('customer_tax_id', 30)->nullable();
            $table->text('customer_address')->nullable();
            $table->decimal('taxable_amount', 14, 2);
            $table->decimal('vat_rate', 5, 2)->default(7);
            $table->decimal('vat_amount', 14, 2);
            $table->decimal('total', 14, 2);
            $table->enum('status', ['active', 'voided'])->default('active');
            $table->foreignId('issued_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'issued_at']);
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_invoices');
    }
};
