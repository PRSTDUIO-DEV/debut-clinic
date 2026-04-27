<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnUpdate()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->string('lot_no', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->unsignedInteger('par_level')->default(0);
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id'], 'sl_prod_wh_idx');
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
