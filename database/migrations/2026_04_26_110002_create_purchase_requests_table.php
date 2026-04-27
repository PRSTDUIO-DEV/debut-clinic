<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('pr_number', 30)->unique();
            $table->date('request_date');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'converted'])->default('draft');
            $table->decimal('estimated_total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index('request_date');
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pr_id')->constrained('purchase_requests')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnUpdate()->nullOnDelete();
            $table->string('description', 200);
            $table->integer('quantity');
            $table->decimal('estimated_cost', 12, 2);
            $table->timestamps();

            $table->index(['pr_id', 'product_id'], 'pri_pr_prod_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
    }
};
