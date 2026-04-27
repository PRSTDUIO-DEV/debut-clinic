<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 20: hot-path indexes for common query patterns.
 * All indexes are guarded — duplicates skipped automatically by MySQL/SQLite.
 */
return new class extends Migration
{
    private function indexExists(string $table, string $name): bool
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            $rows = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$name]);

            return ! empty($rows);
        }
        if ($driver === 'sqlite') {
            $rows = DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name = ? AND name = ?", [$table, $name]);

            return ! empty($rows);
        }

        return false;
    }

    public function up(): void
    {
        $idx = [
            'appointments' => [
                ['cols' => ['branch_id', 'appointment_date', 'doctor_id'], 'name' => 'apt_branch_date_doc_idx'],
                ['cols' => ['patient_id', 'status'], 'name' => 'apt_patient_status_idx'],
            ],
            'invoices' => [
                ['cols' => ['branch_id', 'invoice_date', 'status'], 'name' => 'inv_branch_date_status_idx'],
                ['cols' => ['patient_id', 'invoice_date'], 'name' => 'inv_patient_date_idx'],
                ['cols' => ['cashier_id', 'invoice_date'], 'name' => 'inv_cashier_date_idx'],
            ],
            'invoice_items' => [
                ['cols' => ['invoice_id', 'item_type'], 'name' => 'invitem_inv_type_idx'],
                ['cols' => ['doctor_id', 'item_type'], 'name' => 'invitem_doctor_type_idx'],
            ],
            'payments' => [
                ['cols' => ['invoice_id', 'method'], 'name' => 'pay_inv_method_idx'],
                ['cols' => ['payment_date', 'method'], 'name' => 'pay_date_method_idx'],
            ],
            'commission_transactions' => [
                ['cols' => ['user_id', 'commission_date'], 'name' => 'comm_user_date_idx'],
                ['cols' => ['branch_id', 'is_paid', 'commission_date'], 'name' => 'comm_branch_paid_idx'],
            ],
            'visits' => [
                ['cols' => ['patient_id', 'visit_date'], 'name' => 'visit_patient_date_idx'],
                ['cols' => ['branch_id', 'visit_date', 'status'], 'name' => 'visit_branch_date_status_idx'],
            ],
            'patients' => [
                ['cols' => ['branch_id', 'last_visit_at'], 'name' => 'pt_branch_lastvisit_idx'],
                ['cols' => ['phone'], 'name' => 'pt_phone_idx'],
                ['cols' => ['line_user_id'], 'name' => 'pt_line_user_idx'],
            ],
            'audit_logs' => [
                ['cols' => ['user_id', 'auditable_type', 'created_at'], 'name' => 'audit_user_type_date_idx'],
                ['cols' => ['auditable_type', 'auditable_id'], 'name' => 'audit_target_idx'],
            ],
            'follow_ups' => [
                ['cols' => ['patient_id', 'status'], 'name' => 'fu_patient_status_idx'],
                ['cols' => ['branch_id', 'follow_up_date', 'status'], 'name' => 'fu_branch_date_status_idx'],
            ],
            'notifications' => [
                ['cols' => ['user_id', 'read_at'], 'name' => 'notif_user_read_idx'],
            ],
        ];

        foreach ($idx as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $i) {
                if ($this->indexExists($table, $i['name'])) {
                    continue;
                }
                // Verify all cols exist
                $allExist = collect($i['cols'])->every(fn ($c) => Schema::hasColumn($table, $c));
                if (! $allExist) {
                    continue;
                }
                Schema::table($table, function (Blueprint $t) use ($i) {
                    $t->index($i['cols'], $i['name']);
                });
            }
        }
    }

    public function down(): void
    {
        // Index drops kept manual — retain on rollback for safety
    }
};
