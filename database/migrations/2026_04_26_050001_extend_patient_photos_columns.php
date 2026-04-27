<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_photos', function (Blueprint $table) {
            $table->unsignedInteger('width')->nullable()->after('thumbnail_path');
            $table->unsignedInteger('height')->nullable()->after('width');
            $table->string('mime_type', 60)->nullable()->after('height');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
            $table->string('storage_disk', 20)->default('public')->after('file_size');
        });
    }

    public function down(): void
    {
        Schema::table('patient_photos', function (Blueprint $table) {
            $table->dropColumn(['width', 'height', 'mime_type', 'file_size', 'storage_disk']);
        });
    }
};
