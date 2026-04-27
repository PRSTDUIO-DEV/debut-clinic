<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('qc_runs')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('qc_checklist_items')->cascadeOnDelete();
            $table->enum('status', ['pass', 'fail', 'na'])->default('pass');
            $table->string('note', 500)->nullable();
            $table->string('photo_path', 500)->nullable();
            $table->dateTime('recorded_at')->nullable();
            $table->timestamps();
            $table->unique(['run_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_run_items');
    }
};
