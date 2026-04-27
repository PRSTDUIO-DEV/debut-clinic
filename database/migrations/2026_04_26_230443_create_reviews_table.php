<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->enum('source', ['line', 'email', 'walk_in', 'website'])->default('line');
            $table->enum('status', ['pending', 'published', 'hidden'])->default('pending');
            $table->string('public_token', 64)->unique()->nullable();
            $table->dateTime('requested_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('moderated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'status']);
            $table->index(['rating', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
