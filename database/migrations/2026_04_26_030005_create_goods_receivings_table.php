<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receivings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->cascadeOnUpdate()->nullOnDelete();
            $table->string('document_no', 30)->unique();
            $table->date('receive_date');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('status', ['draft', 'completed', 'cancelled'])->default('draft');
            $table->foreignId('received_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'receive_date']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('goods_receiving_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receiving_id')->constrained('goods_receivings')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnUpdate()->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('lot_no', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->index(['goods_receiving_id', 'product_id'], 'gri_gr_prod_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receiving_items');
        Schema::dropIfExists('goods_receivings');
    }
};
