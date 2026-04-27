<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add position (sort order) + color/icon (UI hints) to settings-style tables
     * for consistent admin display.
     */
    public function up(): void
    {
        $tables = ['rooms', 'banks', 'customer_groups', 'suppliers', 'expense_categories', 'product_categories', 'procedures'];
        foreach ($tables as $t) {
            if (! Schema::hasTable($t)) {
                continue;
            }
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (! Schema::hasColumn($t, 'position')) {
                    $table->unsignedSmallInteger('position')->default(0)->after('id');
                }
            });
        }

        // Color / icon for UI display
        $colorables = ['customer_groups', 'product_categories', 'expense_categories', 'procedures'];
        foreach ($colorables as $t) {
            if (! Schema::hasTable($t)) {
                continue;
            }
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (! Schema::hasColumn($t, 'color')) {
                    $table->string('color', 7)->nullable();
                }
                if (! Schema::hasColumn($t, 'icon')) {
                    $table->string('icon', 30)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        $tables = ['rooms', 'banks', 'customer_groups', 'suppliers', 'expense_categories', 'product_categories', 'procedures'];
        foreach ($tables as $t) {
            if (! Schema::hasTable($t)) {
                continue;
            }
            Schema::table($t, function (Blueprint $table) use ($t) {
                foreach (['position', 'color', 'icon'] as $col) {
                    if (Schema::hasColumn($t, $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
