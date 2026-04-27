<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_rich_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('messaging_providers')->nullOnDelete();
            $table->string('name');
            $table->enum('layout', ['compact_4', 'compact_6', 'full_4', 'full_6', 'full_12'])->default('compact_6');
            $table->json('buttons');
            $table->string('image_path')->nullable();
            $table->string('line_rich_menu_id')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_rich_menus');
    }
};
