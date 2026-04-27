<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('qc_checklists')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('run_date');
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedSmallInteger('total_items')->default(0);
            $table->unsignedSmallInteger('passed_count')->default(0);
            $table->unsignedSmallInteger('failed_count')->default(0);
            $table->unsignedSmallInteger('na_count')->default(0);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'run_date']);
            $table->index(['checklist_id', 'run_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_runs');
    }
};
