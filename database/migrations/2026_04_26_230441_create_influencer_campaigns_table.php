<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('influencer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('shortcode', 16)->unique();
            $table->string('utm_source');
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign');
            $table->string('landing_url')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_budget', 12, 2)->default(0);
            $table->enum('status', ['draft', 'active', 'paused', 'ended'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_campaigns');
    }
};
