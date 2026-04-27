<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('type', ['receive', 'issue', 'transfer_in', 'transfer_out', 'adjust', 'return', 'pos_deduct', 'void_restore']);
            $table->integer('quantity');
            $table->integer('before_qty');
            $table->integer('after_qty');
            $table->string('lot_no', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['branch_id', 'created_at']);
            $table->index(['product_id', 'warehouse_id', 'created_at'], 'sm_prod_wh_time_idx');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
