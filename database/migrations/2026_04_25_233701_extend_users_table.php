<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('uuid', 36)->unique()->after('id');
            $table->foreignId('branch_id')->nullable()->after('uuid')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('employee_code', 20)->nullable()->unique()->after('branch_id');
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('avatar', 255)->nullable()->after('phone');
            $table->string('position', 50)->nullable()->after('avatar');
            $table->boolean('is_doctor')->default(false)->after('position');
            $table->string('license_no', 20)->nullable()->after('is_doctor');
            $table->boolean('is_active')->default(true)->after('license_no');
            $table->softDeletes();

            $table->index(['branch_id', 'is_active']);
            $table->index('is_doctor');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['branch_id', 'is_active']);
            $table->dropIndex(['is_doctor']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'uuid',
                'branch_id',
                'employee_code',
                'phone',
                'avatar',
                'position',
                'is_doctor',
                'license_no',
                'is_active',
            ]);
        });
    }
};
