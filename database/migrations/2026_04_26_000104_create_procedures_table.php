<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->string('category', 50)->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('cost', 12, 2)->default(0);
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->decimal('doctor_fee_rate', 6, 2)->default(0);
            $table->decimal('staff_commission_rate', 6, 2)->default(0);
            $table->unsignedSmallInteger('follow_up_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
            $table->index(['branch_id', 'is_active']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedures');
    }
};
