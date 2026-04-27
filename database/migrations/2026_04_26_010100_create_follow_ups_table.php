<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained('visits')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('procedure_id')->nullable()->constrained('procedures')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->date('follow_up_date');
            $table->enum('priority', ['critical', 'high', 'normal', 'low'])->default('normal');
            $table->enum('status', ['pending', 'contacted', 'scheduled', 'completed', 'cancelled'])->default('pending');
            $table->unsignedSmallInteger('contact_attempts')->default(0);
            $table->timestamp('last_contacted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'follow_up_date']);
            $table->index(['branch_id', 'priority', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};
