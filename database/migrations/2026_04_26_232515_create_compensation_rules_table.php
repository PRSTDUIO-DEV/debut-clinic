<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compensation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('type', ['monthly', 'hourly', 'daily', 'per_procedure', 'commission']);
            $table->decimal('base_amount', 12, 2)->default(0);
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->unsignedBigInteger('applicable_procedure_id')->nullable();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'user_id', 'is_active']);
            $table->index(['branch_id', 'role_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compensation_rules');
    }
};
