<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->enum('type', ['percent', 'fixed']);
            $table->decimal('value', 12, 2);
            $table->decimal('min_amount', 12, 2)->nullable();
            $table->decimal('max_discount', 12, 2)->nullable();
            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);
            $table->integer('max_per_customer')->default(1);
            $table->date('valid_from');
            $table->date('valid_to');
            $table->unsignedBigInteger('applicable_category_id')->nullable();
            $table->unsignedBigInteger('applicable_procedure_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'is_active']);
            $table->index(['valid_from', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
