<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnUpdate()->nullOnDelete();
            $table->enum('recipient_type', ['user', 'role', 'patient']);
            $table->unsignedBigInteger('recipient_id');
            $table->string('type', 60);
            $table->enum('severity', ['info', 'warning', 'critical', 'success'])->default('info');
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->enum('channel', ['in_app', 'line', 'sms', 'email'])->default('in_app');
            $table->enum('status', ['pending', 'sent', 'failed', 'read'])->default('pending');
            $table->string('related_type', 60)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['recipient_type', 'recipient_id', 'status'], 'notif_recipient_status_idx');
            $table->index(['branch_id', 'type', 'created_at']);
            $table->index(['related_type', 'related_id']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('channel', ['in_app', 'line', 'sms', 'email']);
            $table->boolean('enabled')->default(true);
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'channel'], 'np_user_channel_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
