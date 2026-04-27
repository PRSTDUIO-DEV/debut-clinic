<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('segment_id')->constrained('broadcast_segments')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('template_id')->constrained('broadcast_templates')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 200);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'sending', 'completed', 'failed', 'cancelled'])->default('draft');
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index('scheduled_at');
        });

        Schema::create('broadcast_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('broadcast_campaigns')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('channel', ['line', 'sms', 'email']);
            $table->string('recipient_address', 200)->nullable();
            $table->enum('status', ['pending', 'sent', 'failed', 'skipped'])->default('pending');
            $table->text('payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['patient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_messages');
        Schema::dropIfExists('broadcast_campaigns');
    }
};
