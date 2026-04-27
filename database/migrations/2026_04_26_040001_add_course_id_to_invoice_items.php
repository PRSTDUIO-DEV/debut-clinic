<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->after('item_id')->constrained('courses')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('course_id');
        });
    }
};
