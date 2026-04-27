<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('type', ['doctor_fee', 'staff_commission', 'referral']);
            $table->enum('applicable_type', ['procedure', 'procedure_category', 'all'])->default('procedure');
            $table->unsignedBigInteger('applicable_id')->nullable(); // null = all
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('rate', 6, 2)->nullable();           // percentage 0-100
            $table->decimal('fixed_amount', 12, 2)->nullable();  // alternative to rate
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'type', 'applicable_type', 'applicable_id'], 'comm_rates_lookup_idx');
            $table->index(['branch_id', 'user_id'], 'comm_rates_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rates');
    }
};
