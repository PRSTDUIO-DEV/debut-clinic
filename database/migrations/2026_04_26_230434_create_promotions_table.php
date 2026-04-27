<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['percent', 'fixed', 'buy_x_get_y', 'bundle']);
            $table->json('rules');
            $table->date('valid_from');
            $table->date('valid_to');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'is_active']);
            $table->index(['valid_from', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
