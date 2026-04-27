<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->enum('channel', ['line', 'sms', 'email']);
            $table->string('subject', 200)->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
            $table->index(['branch_id', 'channel', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_templates');
    }
};
