<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;

/**
 * Idempotently seeds Thailand-style mini chart of accounts for a branch.
 * Used during install + when a new branch is created.
 */
class ChartOfAccountSeeder
{
    /**
     * @return array<string, ChartOfAccount> code => model
     */
    public function seed(int $branchId): array
    {
        $defaults = [
            // Assets (1xxx)
            ['1100', 'เงินสด', 'asset'],
            ['1110', 'เงินฝากธนาคาร', 'asset'],
            ['1200', 'ลูกหนี้การค้า', 'asset'],
            ['1300', 'สินค้าคงเหลือ', 'asset'],

            // Liabilities (2xxx)
            ['2100', 'เจ้าหนี้การค้า', 'liability'],
            ['2110', 'ภาษีขายค้างจ่าย', 'liability'],
            ['2400', 'หนี้สินสมาชิก (Member Wallet)', 'liability'],

            // Equity (3xxx)
            ['3100', 'ทุน', 'equity'],
            ['3200', 'กำไรสะสม', 'equity'],

            // Revenue (4xxx)
            ['4100', 'รายได้ค่ารักษา', 'revenue'],
            ['4200', 'รายได้จำหน่ายผลิตภัณฑ์', 'revenue'],
            ['4300', 'รายได้คอร์ส/แพ็กเกจ', 'revenue'],

            // Expenses (5xxx-6xxx)
            ['5100', 'ต้นทุนสินค้าขาย (COGS)', 'expense'],
            ['5200', 'ค่ามือแพทย์', 'expense'],
            ['5300', 'ค่าคอมพนักงาน', 'expense'],
            ['5400', 'ค่าธรรมเนียมบัตรเครดิต (MDR)', 'expense'],
            ['6100', 'ค่าเช่าสถานที่', 'expense'],
            ['6200', 'ค่าน้ำ-ค่าไฟ', 'expense'],
            ['6300', 'ค่าทำความสะอาด', 'expense'],
            ['6400', 'ค่ายาและวัสดุสิ้นเปลือง', 'expense'],
            ['6500', 'ค่าโฆษณา/การตลาด', 'expense'],
            ['6900', 'ค่าใช้จ่ายอื่นๆ', 'expense'],
        ];

        $rows = [];
        foreach ($defaults as [$code, $name, $type]) {
            $rows[$code] = ChartOfAccount::firstOrCreate(
                ['branch_id' => $branchId, 'code' => $code],
                ['name' => $name, 'type' => $type, 'is_system' => true, 'is_active' => true],
            );
        }

        return $rows;
    }

    /**
     * Map expense category names to CoA codes (best-effort heuristic).
     */
    public function mapExpenseCategoryCode(?string $categoryName): string
    {
        if (! $categoryName) {
            return '6900';
        }
        $lower = mb_strtolower($categoryName);
        if (str_contains($lower, 'เช่า') || str_contains($lower, 'rent')) {
            return '6100';
        }
        if (str_contains($lower, 'น้ำ') || str_contains($lower, 'ไฟ') || str_contains($lower, 'utilit')) {
            return '6200';
        }
        if (str_contains($lower, 'สะอาด') || str_contains($lower, 'clean')) {
            return '6300';
        }
        if (str_contains($lower, 'ยา') || str_contains($lower, 'วัสดุ') || str_contains($lower, 'supply')) {
            return '6400';
        }
        if (str_contains($lower, 'โฆษณา') || str_contains($lower, 'การตลาด') || str_contains($lower, 'market') || str_contains($lower, 'ad')) {
            return '6500';
        }

        return '6900';
    }

    public function mapDisbursementCode(string $type): string
    {
        return match ($type) {
            'salary' => '5300',
            'utilities' => '6200',
            'rent' => '6100',
            'tax' => '2110',
            'supplier' => '2100',
            default => '6900',
        };
    }
}
