<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('influencer_campaigns')->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->dateTime('referred_at');
            $table->dateTime('first_visit_at')->nullable();
            $table->decimal('lifetime_value', 12, 2)->default(0);
            $table->timestamps();
            $table->index(['campaign_id', 'patient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_referrals');
    }
};
