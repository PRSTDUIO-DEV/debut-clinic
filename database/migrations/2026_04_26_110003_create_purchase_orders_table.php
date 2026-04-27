<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('pr_id')->nullable()->constrained('purchase_requests')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('po_number', 30)->unique();
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->enum('status', ['draft', 'sent', 'partial_received', 'received', 'cancelled'])->default('draft');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['supplier_id', 'order_date']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('po_id')->constrained('purchase_orders')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnUpdate()->nullOnDelete();
            $table->string('description', 200);
            $table->integer('quantity');
            $table->integer('received_qty')->default(0);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total', 14, 2);
            $table->timestamps();

            $table->index(['po_id', 'product_id'], 'poi_po_prod_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
