<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->cascadeOnUpdate()->nullOnDelete();
            $table->string('sku', 30);
            $table->string('name', 200);
            $table->string('unit', 20)->default('ชิ้น');
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->unsignedInteger('min_stock')->default(0);
            $table->unsignedInteger('max_stock')->default(0);
            $table->unsignedInteger('reorder_point')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('block_dispensing_when_expired')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'sku']);
            $table->index(['branch_id', 'is_active']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
