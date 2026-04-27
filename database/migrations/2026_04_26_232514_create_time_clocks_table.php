<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_clocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->dateTime('clock_in');
            $table->dateTime('clock_out')->nullable();
            $table->integer('total_minutes')->nullable();
            $table->integer('late_minutes')->default(0);
            $table->integer('overtime_minutes')->default(0);
            $table->enum('source', ['pin', 'biometric', 'manual', 'kiosk'])->default('pin');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'clock_in']);
            $table->index(['branch_id', 'clock_in']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_clocks');
    }
};
