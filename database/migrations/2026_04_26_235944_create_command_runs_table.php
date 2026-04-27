<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command', 128);
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->integer('exit_code')->nullable();
            $table->text('output')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['command', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_runs');
    }
};
