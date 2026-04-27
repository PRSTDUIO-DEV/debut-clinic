<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->enum('status', ['draft', 'finalized', 'paid'])->default('draft');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->dateTime('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->string('payment_reference', 64)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['branch_id', 'period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
