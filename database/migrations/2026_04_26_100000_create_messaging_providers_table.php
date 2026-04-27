<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('type', ['line', 'sms', 'email']);
            $table->string('name', 100);
            // config JSON encrypted (channel_secret, channel_access_token, sender_id, etc.)
            $table->text('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->enum('status', ['ok', 'warning', 'error', 'unknown'])->default('unknown');
            $table->timestamp('last_check_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'type', 'is_active']);
        });

        Schema::create('messaging_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->nullable()->constrained('messaging_providers')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnUpdate()->nullOnDelete();
            $table->enum('channel', ['line', 'sms', 'email']);
            $table->string('recipient_address', 255);
            $table->text('payload')->nullable();
            $table->text('response')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed', 'bounced'])->default('pending');
            $table->string('external_id', 100)->nullable();
            $table->text('error')->nullable();
            $table->string('related_type', 60)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'status', 'created_at'], 'mlogs_prov_status_idx');
            $table->index(['related_type', 'related_id']);
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_logs');
        Schema::dropIfExists('messaging_providers');
    }
};
