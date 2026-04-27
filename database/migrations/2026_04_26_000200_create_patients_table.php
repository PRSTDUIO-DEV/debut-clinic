<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('hn', 30)->index();
            $table->string('prefix', 20)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('nickname', 50)->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->default('other');
            $table->date('date_of_birth')->nullable();
            $table->string('id_card', 13)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('line_id', 50)->nullable();
            $table->text('address')->nullable();
            $table->text('allergies')->nullable();
            $table->text('underlying_diseases')->nullable();
            $table->string('blood_type', 5)->nullable();
            $table->json('emergency_contact')->nullable();
            $table->string('avatar', 255)->nullable();
            $table->string('source', 30)->default('walk_in');
            $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->unsignedInteger('visit_count')->default(0);
            $table->timestamp('last_visit_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'hn']);
            $table->index(['branch_id', 'phone']);
            $table->index(['branch_id', 'last_visit_at']);
            $table->index('date_of_birth'); // birthday alerts (Sprint 14)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
