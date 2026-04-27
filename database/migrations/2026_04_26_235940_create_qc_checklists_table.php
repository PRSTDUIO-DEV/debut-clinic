<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'per_visit'])->default('daily');
            $table->string('applicable_role', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_checklists');
    }
};
