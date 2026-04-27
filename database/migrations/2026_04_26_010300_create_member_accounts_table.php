<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('patient_id')->unique()->constrained('patients')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('package_name', 100)->nullable();
            $table->decimal('total_deposit', 12, 2)->default(0);
            $table->decimal('total_used', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->date('expires_at')->nullable();
            $table->enum('status', ['active', 'expired', 'suspended'])->default('active');
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index('expires_at');
        });

        Schema::create('member_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_account_id')->constrained('member_accounts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('type', ['deposit', 'usage', 'refund', 'adjustment']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->cascadeOnUpdate()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_transactions');
        Schema::dropIfExists('member_accounts');
    }
};
