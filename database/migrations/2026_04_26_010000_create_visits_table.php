<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->cascadeOnUpdate()->nullOnDelete();
            $table->string('visit_number', 30)->unique();
            $table->date('visit_date');
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->enum('status', ['waiting', 'in_progress', 'completed', 'cancelled'])->default('waiting');
            $table->string('source', 20)->default('walk_in');
            $table->json('vital_signs')->nullable();
            $table->text('chief_complaint')->nullable();
            $table->text('doctor_notes')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'visit_date']);
            $table->index(['branch_id', 'status']);
            $table->index('patient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
