<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\CustomerGroup;
use App\Models\Procedure;
use App\Models\Room;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::query()->first();
        if (! $branch) {
            return; // BranchAndAdminSeeder must run first
        }

        $branchId = $branch->id;

        $rooms = [
            ['name' => 'ห้องตรวจ 1', 'type' => 'consultation', 'floor' => 1],
            ['name' => 'ห้องหัตถการ 1', 'type' => 'treatment', 'floor' => 1],
            ['name' => 'ห้อง VIP', 'type' => 'vip', 'floor' => 2],
        ];
        foreach ($rooms as $r) {
            Room::firstOrCreate(['branch_id' => $branchId, 'name' => $r['name']], $r + ['branch_id' => $branchId, 'is_active' => true]);
        }

        $banks = [
            ['name' => 'SCB', 'mdr_rate' => 1.80],
            ['name' => 'Kbank', 'mdr_rate' => 1.85],
            ['name' => 'BBL', 'mdr_rate' => 1.95],
        ];
        foreach ($banks as $b) {
            Bank::firstOrCreate(['branch_id' => $branchId, 'name' => $b['name']], $b + ['branch_id' => $branchId, 'is_active' => true]);
        }

        $groups = [
            ['name' => 'ลูกค้าทั่วไป', 'discount_rate' => 0],
            ['name' => 'สมาชิกเงินฝาก', 'discount_rate' => 5],
            ['name' => 'VIP', 'discount_rate' => 10],
        ];
        foreach ($groups as $g) {
            CustomerGroup::firstOrCreate(['branch_id' => $branchId, 'name' => $g['name']], $g + ['branch_id' => $branchId, 'is_active' => true]);
        }

        $suppliers = [
            ['name' => 'Allergan Thailand', 'contact_person' => 'Sales Rep', 'phone' => '02-111-1111', 'payment_terms' => 'Net 30'],
            ['name' => 'Local Pharma', 'contact_person' => 'Mr. A', 'phone' => '02-222-2222', 'payment_terms' => 'Net 15'],
        ];
        foreach ($suppliers as $s) {
            Supplier::firstOrCreate(['branch_id' => $branchId, 'name' => $s['name']], $s + ['branch_id' => $branchId, 'is_active' => true]);
        }

        $procedures = [
            ['code' => 'BTX-100', 'name' => 'Botox 100u', 'category' => 'Injection', 'price' => 12000, 'cost' => 4000, 'duration_minutes' => 30, 'doctor_fee_rate' => 30, 'staff_commission_rate' => 5, 'follow_up_days' => 14],
            ['code' => 'FILLER-1', 'name' => 'Filler 1cc', 'category' => 'Injection', 'price' => 18000, 'cost' => 5500, 'duration_minutes' => 45, 'doctor_fee_rate' => 30, 'staff_commission_rate' => 5, 'follow_up_days' => 14],
            ['code' => 'CONSULT', 'name' => 'ปรึกษาแพทย์', 'category' => 'Consultation', 'price' => 500, 'cost' => 0, 'duration_minutes' => 20, 'doctor_fee_rate' => 50, 'staff_commission_rate' => 0, 'follow_up_days' => 0],
            ['code' => 'IPL-PKG-6', 'name' => 'IPL Package 6 ครั้ง', 'category' => 'Laser', 'price' => 30000, 'cost' => 4500, 'duration_minutes' => 30, 'doctor_fee_rate' => 0, 'staff_commission_rate' => 5, 'follow_up_days' => 0, 'is_package' => true, 'package_sessions' => 6, 'package_validity_days' => 365],
            ['code' => 'HIFU-PKG-3', 'name' => 'HIFU Package 3 ครั้ง', 'category' => 'Laser', 'price' => 45000, 'cost' => 9000, 'duration_minutes' => 60, 'doctor_fee_rate' => 0, 'staff_commission_rate' => 5, 'follow_up_days' => 0, 'is_package' => true, 'package_sessions' => 3, 'package_validity_days' => 180],
        ];
        foreach ($procedures as $p) {
            Procedure::firstOrCreate(['branch_id' => $branchId, 'code' => $p['code']], $p + ['branch_id' => $branchId, 'is_active' => true]);
        }
    }
}
