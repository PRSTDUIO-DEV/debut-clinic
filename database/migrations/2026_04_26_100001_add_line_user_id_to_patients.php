<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // line_id (display handle) already exists from Sprint 2
            // line_user_id is the actual LINE userId from LIFF/webhook (used to push messages)
            $table->string('line_user_id', 64)->nullable()->after('line_id');
            $table->timestamp('line_linked_at')->nullable()->after('line_user_id');

            $table->unique('line_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique(['line_user_id']);
            $table->dropColumn(['line_user_id', 'line_linked_at']);
        });
    }
};
