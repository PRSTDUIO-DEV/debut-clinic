<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->date('closing_date');
            $table->decimal('expected_cash', 14, 2)->default(0);
            $table->decimal('counted_cash', 14, 2)->default(0);
            $table->decimal('variance', 14, 2)->default(0);
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->decimal('total_cogs', 14, 2)->default(0);
            $table->decimal('total_commission', 14, 2)->default(0);
            $table->decimal('total_mdr', 14, 2)->default(0);
            $table->decimal('total_expenses', 14, 2)->default(0);
            $table->decimal('gross_profit', 14, 2)->default(0);
            $table->decimal('net_profit', 14, 2)->default(0);
            $table->json('payment_breakdown')->nullable();
            $table->enum('status', ['draft', 'closed', 'reopened'])->default('draft');
            $table->foreignId('closed_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'closing_date']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closings');
    }
};
