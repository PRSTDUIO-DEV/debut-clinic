<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_accounts', function (Blueprint $table) {
            $table->timestamp('last_topup_at')->nullable()->after('expires_at');
            $table->timestamp('last_used_at')->nullable()->after('last_topup_at');
            $table->unsignedInteger('lifetime_topups')->default(0)->after('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('member_accounts', function (Blueprint $table) {
            $table->dropColumn(['last_topup_at', 'last_used_at', 'lifetime_topups']);
        });
    }
};
