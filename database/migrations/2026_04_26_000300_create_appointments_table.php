<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('procedure_id')->nullable()->constrained('procedures')->cascadeOnUpdate()->nullOnDelete();
            $table->date('appointment_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['pending', 'confirmed', 'arrived', 'completed', 'cancelled', 'no_show'])->default('pending');
            $table->string('source', 20)->default('manual');
            $table->unsignedBigInteger('follow_up_id')->nullable(); // FK reserved (Sprint 4)
            $table->boolean('reminder_sent')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'appointment_date', 'status']);
            $table->index(['doctor_id', 'appointment_date']);
            $table->index(['room_id', 'appointment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
