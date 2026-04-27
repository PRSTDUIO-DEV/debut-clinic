<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 100);
            $table->enum('type', ['main', 'floor'])->default('floor');
            $table->foreignId('room_id')->nullable()->constrained('rooms')->cascadeOnUpdate()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'name']);
            $table->index(['branch_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
