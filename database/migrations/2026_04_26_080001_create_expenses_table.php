<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('expense_categories')->cascadeOnUpdate()->nullOnDelete();
            $table->date('expense_date');
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'transfer', 'credit_card', 'check', 'other'])->default('cash');
            $table->string('vendor', 150)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'expense_date']);
            $table->index(['branch_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
