<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('display_name', 150);
            $table->string('module', 50);
            $table->string('guard_name', 20)->default('web');
            $table->timestamps();

            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
