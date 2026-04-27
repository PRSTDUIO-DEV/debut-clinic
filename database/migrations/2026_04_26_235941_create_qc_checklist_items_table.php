<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('qc_checklists')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('title');
            $table->string('description', 500)->nullable();
            $table->boolean('requires_photo')->default(false);
            $table->boolean('requires_note')->default(false);
            $table->boolean('default_pass')->default(true);
            $table->timestamps();
            $table->index(['checklist_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_checklist_items');
    }
};
