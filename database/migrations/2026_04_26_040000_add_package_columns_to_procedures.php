<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->boolean('is_package')->default(false)->after('cost');
            $table->unsignedSmallInteger('package_sessions')->default(0)->after('is_package');
            $table->unsignedSmallInteger('package_validity_days')->default(0)->after('package_sessions');
        });
    }

    public function down(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropColumn(['is_package', 'package_sessions', 'package_validity_days']);
        });
    }
};
