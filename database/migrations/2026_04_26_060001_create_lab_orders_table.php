<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained('visits')->cascadeOnUpdate()->nullOnDelete();
            $table->string('order_no', 30)->unique();
            $table->timestamp('ordered_at')->useCurrent();
            $table->foreignId('ordered_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->enum('status', ['draft', 'sent', 'completed', 'cancelled'])->default('draft');
            $table->date('result_date')->nullable();
            $table->string('report_path', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['patient_id', 'ordered_at']);
        });

        Schema::create('lab_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('lab_test_id')->constrained('lab_tests')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['lab_order_id', 'lab_test_id'], 'loi_order_test_uniq');
        });

        Schema::create('lab_result_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('lab_test_id')->constrained('lab_tests')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('value_numeric', 14, 4)->nullable();
            $table->string('value_text', 200)->nullable();
            $table->enum('abnormal_flag', ['normal', 'low', 'high', 'critical'])->default('normal');
            $table->text('notes')->nullable();
            $table->timestamp('measured_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->unique(['lab_order_id', 'lab_test_id'], 'lrv_order_test_uniq');
            $table->index('abnormal_flag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_result_values');
        Schema::dropIfExists('lab_order_items');
        Schema::dropIfExists('lab_orders');
    }
};
