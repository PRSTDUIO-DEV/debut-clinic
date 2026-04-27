<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->date('entry_date');
            $table->string('journal_no', 30);
            $table->string('document_type', 60);
            $table->unsignedBigInteger('document_id')->nullable();
            $table->foreignId('debit_account_id')->constrained('chart_of_accounts')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('credit_account_id')->constrained('chart_of_accounts')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('description', 255)->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'entry_date'], 'ae_branch_date_idx');
            $table->index(['document_type', 'document_id'], 'ae_doc_idx');
            $table->index('journal_no');
            $table->index('debit_account_id', 'ae_debit_idx');
            $table->index('credit_account_id', 'ae_credit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_entries');
    }
};
