<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('birthday_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->json('templates'); // {"30":{"channel":"in_app","title":"...","body":"..."}, "7":{...}, "0":{...}, "+3":{...}}
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('total_sent')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('birthday_campaigns');
    }
};
