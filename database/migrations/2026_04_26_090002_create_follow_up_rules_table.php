<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_up_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 150);
            $table->enum('priority', ['critical', 'high', 'normal', 'low']);
            $table->enum('condition_type', [
                'overdue_days',           // เลยกำหนดนัด N วัน
                'course_expiring_days',   // คอร์สใกล้หมดอายุภายใน N วัน
                'wallet_low_amount',      // ยอด wallet ต่ำกว่า N บาท
                'dormant_days',           // ไม่มาคลินิกเกิน N วัน
                'has_critical_tag',       // มี tag (ผลข้างเคียง / ร้องเรียน)
                'vip_overdue_days',       // VIP เลยนัด N วัน
            ]);
            $table->json('condition_value');
            $table->boolean('notify_doctor')->default(false);
            $table->boolean('notify_branch_admin')->default(true);
            $table->enum('preferred_channel', ['in_app', 'line', 'email'])->default('in_app');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_rules');
    }
};
