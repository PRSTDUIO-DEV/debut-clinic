<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount_discounted', 12, 2);
            $table->dateTime('redeemed_at');
            $table->timestamps();
            $table->index(['coupon_id', 'patient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
    }
};
