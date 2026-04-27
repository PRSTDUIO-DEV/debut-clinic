<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('total_sessions');
            $table->unsignedSmallInteger('used_sessions')->default(0);
            $table->unsignedSmallInteger('remaining_sessions');
            $table->date('expires_at')->nullable();
            $table->enum('status', ['active', 'expired', 'completed', 'cancelled'])->default('active');
            $table->foreignId('source_invoice_item_id')->nullable()->constrained('invoice_items')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'patient_id', 'status']);
        });

        Schema::create('course_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedSmallInteger('session_number');
            $table->timestamp('used_at');
            $table->foreignId('doctor_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['course_id', 'session_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_usages');
        Schema::dropIfExists('courses');
    }
};
