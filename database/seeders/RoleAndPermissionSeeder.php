<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Locked roles per IMPLEMENTATION_BRIEF.md (8 roles).
     */
    public const ROLES = [
        ['name' => 'super_admin', 'display_name' => 'Super Admin'],
        ['name' => 'branch_admin', 'display_name' => 'Branch Admin'],
        ['name' => 'doctor', 'display_name' => 'Doctor'],
        ['name' => 'nurse', 'display_name' => 'Nurse'],
        ['name' => 'receptionist', 'display_name' => 'Receptionist'],
        ['name' => 'pharmacist', 'display_name' => 'Pharmacist'],
        ['name' => 'accountant', 'display_name' => 'Accountant'],
        ['name' => 'marketing_staff', 'display_name' => 'Marketing Staff'],
    ];

    /**
     * Initial permissions matrix grouped by module.
     * Modules align with IMPLEMENTATION_BRIEF.md domain modules.
     */
    public const MODULES = [
        'auth' => ['login', 'logout'],
        'users' => ['view', 'create', 'update', 'delete'],
        'roles' => ['view', 'manage'],
        'branches' => ['view', 'manage', 'create', 'update', 'delete'],
        'patients' => ['view', 'create', 'update', 'delete'],
        'appointments' => ['view', 'create', 'update', 'cancel'],
        'visits' => ['view', 'create', 'update', 'checkout'],
        'invoices' => ['view', 'void', 'export'],
        'inventory' => ['view', 'receive', 'requisition.create', 'requisition.approve', 'adjust'],
        'member' => ['view', 'manage', 'topup', 'refund'],
        'course' => ['view', 'manage'],
        'media' => ['upload', 'delete'],
        'consent' => ['template.manage', 'sign', 'void'],
        'lab' => ['view', 'order', 'result', 'catalog.manage'],
        'notifications' => ['view', 'manage'],
        'messaging' => ['providers.view', 'providers.manage', 'logs.view', 'logs.retry'],
        'finance' => ['view', 'commission.view', 'daily_pl.view', 'reports.view', 'expense.view', 'expense.manage', 'closing.view', 'closing.perform'],
        'accounting' => ['coa.view', 'coa.manage', 'pr.view', 'pr.manage', 'po.view', 'po.manage', 'disbursement.view', 'disbursement.manage', 'tax.view', 'tax.manage', 'ledger.view'],
        'crm' => ['view', 'broadcast.send', 'templates.manage', 'segments.manage'],
        'marketing' => ['coupon.view', 'coupon.manage', 'promotion.view', 'promotion.manage', 'influencer.view', 'influencer.manage', 'review.view', 'review.manage', 'rich_menu.view', 'rich_menu.manage'],
        'payroll' => ['view', 'manage'],
        'time_clock' => ['view', 'manage'],
        'qc' => ['view', 'perform', 'manage'],
        'system' => ['health.view', 'cron.view', 'queue.manage'],
        'settings' => ['view', 'manage'],
        'audit' => ['view'],
    ];

    /**
     * Default permission set per role.
     */
    public const ROLE_PERMS = [
        'super_admin' => '*',
        'branch_admin' => [
            'auth.*', 'users.*', 'roles.view', 'branches.view',
            'patients.*', 'appointments.*', 'visits.*', 'invoices.*',
            'inventory.*', 'member.*', 'course.*', 'media.*', 'consent.*',
            'lab.*', 'notifications.*', 'messaging.*',
            'finance.*', 'accounting.*', 'crm.*', 'marketing.*', 'payroll.*', 'time_clock.*', 'qc.*', 'system.*', 'settings.*', 'audit.view',
        ],
        'doctor' => [
            'auth.*', 'patients.view', 'patients.update',
            'appointments.view', 'appointments.update',
            'visits.view', 'visits.update',
            'course.view', 'course.manage',
            'media.upload', 'media.delete', 'consent.sign',
            'lab.view', 'lab.order', 'lab.result',
            'notifications.view',
        ],
        'nurse' => [
            'auth.*', 'patients.view', 'patients.update',
            'appointments.view', 'visits.view', 'visits.update',
            'inventory.view', 'course.view', 'media.upload',
            'lab.view', 'lab.result',
            'notifications.view',
            'qc.view', 'qc.perform',
        ],
        'receptionist' => [
            'auth.*', 'patients.view', 'patients.create', 'patients.update',
            'appointments.*', 'visits.view', 'visits.create',
            'invoices.view', 'member.view', 'member.topup', 'course.view',
            'consent.sign',
            'notifications.view',
        ],
        'pharmacist' => [
            'auth.*', 'inventory.*', 'patients.view',
        ],
        'accountant' => [
            'auth.*', 'finance.*', 'accounting.*', 'invoices.*', 'audit.view',
            'member.*', 'course.view', 'payroll.*',
        ],
        'marketing_staff' => [
            'auth.*', 'crm.*', 'marketing.*', 'patients.view',
        ],
    ];

    public function run(): void
    {
        // Seed permissions
        $allNames = [];
        foreach (self::MODULES as $module => $actions) {
            foreach ($actions as $action) {
                $name = "$module.$action";
                $allNames[] = $name;
                Permission::updateOrCreate(
                    ['name' => $name],
                    [
                        'display_name' => ucwords(str_replace(['.', '_'], ' ', $name)),
                        'module' => $module,
                        'guard_name' => 'web',
                    ],
                );
            }
        }

        // Seed roles + attach permissions
        foreach (self::ROLES as $row) {
            $role = Role::updateOrCreate(
                ['name' => $row['name']],
                ['display_name' => $row['display_name'], 'guard_name' => 'web'],
            );

            $assigned = self::ROLE_PERMS[$row['name']] ?? [];
            $resolved = $this->resolvePermissionList($assigned, $allNames);
            $ids = Permission::whereIn('name', $resolved)->pluck('id')->all();
            $role->permissions()->sync($ids);
        }
    }

    /**
     * Expand wildcard patterns ("auth.*", "*") into concrete permission names.
     *
     * @param array<int,string>|string $patterns
     * @param array<int,string> $allNames
     * @return array<int,string>
     */
    private function resolvePermissionList(array|string $patterns, array $allNames): array
    {
        if ($patterns === '*') {
            return $allNames;
        }

        $resolved = [];
        foreach ((array) $patterns as $pattern) {
            if ($pattern === '*') {
                return $allNames;
            }
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                foreach ($allNames as $name) {
                    if (str_starts_with($name, $prefix)) {
                        $resolved[] = $name;
                    }
                }
            } else {
                $resolved[] = $pattern;
            }
        }

        return array_values(array_unique($resolved));
    }
}
