<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('base_pay', 12, 2)->default(0);
            $table->decimal('commission_total', 12, 2)->default(0);
            $table->decimal('overtime_pay', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('deduction', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->decimal('hours_worked', 8, 2)->default(0);
            $table->unsignedSmallInteger('days_worked')->default(0);
            $table->unsignedSmallInteger('late_count')->default(0);
            $table->string('compensation_type', 32)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['payroll_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
