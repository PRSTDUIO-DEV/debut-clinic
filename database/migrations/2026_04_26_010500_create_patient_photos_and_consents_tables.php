<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained('visits')->cascadeOnUpdate()->nullOnDelete();
            $table->enum('type', ['before', 'after', 'general'])->default('general');
            $table->string('file_path', 255);
            $table->string('thumbnail_path', 255)->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'type']);
        });

        Schema::create('patient_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('file_path', 255)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->enum('status', ['pending', 'signed', 'expired'])->default('pending');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_consents');
        Schema::dropIfExists('patient_photos');
    }
};
