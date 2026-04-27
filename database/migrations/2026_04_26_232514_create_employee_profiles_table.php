<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_no', 32)->unique()->nullable();
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account', 64)->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('emergency_phone', 32)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('national_id', 32)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
