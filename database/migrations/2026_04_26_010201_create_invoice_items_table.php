<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('item_type', ['procedure', 'product', 'course', 'package']);
            $table->unsignedBigInteger('item_id');
            $table->string('item_name', 200);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->foreignId('doctor_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->index(['item_type', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
