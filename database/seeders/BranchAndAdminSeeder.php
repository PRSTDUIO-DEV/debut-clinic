<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\ChartOfAccountSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BranchAndAdminSeeder extends Seeder
{
    /**
     * One user per role for development quick-login.
     * Password is uniform across dev accounts: "password".
     */
    public const DEV_USERS = [
        ['role' => 'super_admin',      'email' => 'super@debut-clinic.local',      'name' => 'Super Admin',      'employee_code' => 'EMP-0001', 'is_doctor' => false, 'license_no' => null],
        ['role' => 'branch_admin',     'email' => 'branch@debut-clinic.local',     'name' => 'Branch Admin',     'employee_code' => 'EMP-0002', 'is_doctor' => false, 'license_no' => null],
        ['role' => 'doctor',           'email' => 'doctor@debut-clinic.local',     'name' => 'หมอตัวอย่าง',       'employee_code' => 'EMP-0003', 'is_doctor' => true,  'license_no' => 'MD-99999'],
        ['role' => 'nurse',            'email' => 'nurse@debut-clinic.local',      'name' => 'พยาบาลตัวอย่าง',    'employee_code' => 'EMP-0004', 'is_doctor' => false, 'license_no' => null],
        ['role' => 'receptionist',     'email' => 'reception@debut-clinic.local',  'name' => 'พนักงานต้อนรับ',     'employee_code' => 'EMP-0005', 'is_doctor' => false, 'license_no' => null],
        ['role' => 'pharmacist',       'email' => 'pharmacist@debut-clinic.local', 'name' => 'เภสัชกร',           'employee_code' => 'EMP-0006', 'is_doctor' => false, 'license_no' => null],
        ['role' => 'accountant',       'email' => 'accountant@debut-clinic.local', 'name' => 'ฝ่ายบัญชี',          'employee_code' => 'EMP-0007', 'is_doctor' => false, 'license_no' => null],
        ['role' => 'marketing_staff',  'email' => 'marketing@debut-clinic.local',  'name' => 'การตลาด',           'employee_code' => 'EMP-0008', 'is_doctor' => false, 'license_no' => null],
    ];

    public function run(): void
    {
        $branch = Branch::firstOrCreate(
            ['code' => env('DEFAULT_BRANCH_CODE', 'DC01')],
            [
                'name' => 'สาขาหลัก',
                'address' => '—',
                'phone' => '02-000-0000',
                'email' => 'main@debut-clinic.local',
                'opening_time' => '09:00',
                'closing_time' => '20:00',
                'is_active' => true,
                'settings' => [],
            ],
        );

        // Seed default chart of accounts for the branch
        app(ChartOfAccountSeeder::class)->seed($branch->id);

        foreach (self::DEV_USERS as $row) {
            $user = User::firstOrCreate(
                ['email' => $row['email']],
                [
                    'branch_id' => $branch->id,
                    'employee_code' => $row['employee_code'],
                    'name' => $row['name'],
                    'password' => Hash::make('password'),
                    'is_doctor' => $row['is_doctor'],
                    'license_no' => $row['license_no'],
                    'is_active' => true,
                ],
            );

            $user->branches()->syncWithoutDetaching([$branch->id => ['is_primary' => true]]);

            $role = Role::where('name', $row['role'])->first();
            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }
        }
    }
}
