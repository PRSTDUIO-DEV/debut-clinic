<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('document_no', 30)->unique();
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('dest_warehouse_id')->constrained('warehouses')->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('stock_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_requisition_id')->constrained('stock_requisitions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnUpdate()->cascadeOnDelete();
            $table->integer('requested_qty');
            $table->integer('approved_qty')->default(0);
            $table->timestamps();

            $table->index(['stock_requisition_id', 'product_id'], 'sri_req_prod_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_requisition_items');
        Schema::dropIfExists('stock_requisitions');
    }
};
