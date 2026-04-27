<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_consents', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->after('patient_id')->constrained('consent_templates')->cascadeOnUpdate()->nullOnDelete();
            $table->string('signature_path', 255)->nullable()->after('file_path');
            $table->string('signed_by_name', 150)->nullable()->after('signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('patient_consents', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
        });
        Schema::table('patient_consents', function (Blueprint $table) {
            $table->dropColumn(['template_id', 'signature_path', 'signed_by_name']);
        });
    }
};
