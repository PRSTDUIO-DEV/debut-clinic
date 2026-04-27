<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('type', 30)->default('treatment');
            $table->unsignedSmallInteger('floor')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'name']);
            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
