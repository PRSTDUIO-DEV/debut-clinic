<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('name', 100);
            $table->string('code', 10)->unique();
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->time('opening_time')->nullable();
            $table->time('closing_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
