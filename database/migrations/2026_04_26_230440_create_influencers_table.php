<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('channel', ['instagram', 'facebook', 'tiktok', 'youtube', 'line', 'other']);
            $table->string('handle')->nullable();
            $table->string('contact')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencers');
    }
};
