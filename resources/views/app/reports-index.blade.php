@extends('layouts.app')
@section('title', 'Reports Library')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">📋 Reports Library</h1>
            <a href="/mis" class="ml-auto text-sm text-cyan-700 hover:underline">📊 MIS Dashboard</a>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6">
        @php
            $sections = [
                'การเงิน (Finance)' => [
                    ['/reports/payment-mix', '💳', 'Payment Mix', 'แยกเงินสด/บัตร/โอน + MDR'],
                    ['/reports/daily-pl', '📊', 'P/L รายวัน', 'รายได้ - ต้นทุน - รายจ่าย'],
                    ['/reports/refund-history', '↩️', 'ประวัติคืนเงิน', 'รายการ refund ใน wallet'],
                    ['/accounting/tax-invoices', '🧾', 'ใบกำกับภาษี', 'Output VAT รายเดือน'],
                    ['/accounting/ledger', '📚', 'บัญชี + งบ', 'Ledger / Trial Balance / Cash Flow / Tax'],
                ],
                'ลูกค้า + CRM' => [
                    ['/reports/cohort-retention', '🔁', 'Cohort Retention', 'อัตรากลับมาใช้บริการ'],
                    ['/reports/demographics', '👥', 'Demographics', 'เพศ / อายุ / กลุ่ม'],
                    ['/reports/revenue-by-customer-group', '🎯', 'รายได้ตามกลุ่มลูกค้า', 'แยก VIP / สมาชิก / ทั่วไป'],
                    ['/reports/revenue-by-source', '📥', 'รายได้ตามแหล่งที่มา', 'walk_in / referral / online'],
                    ['/reports/birthday-this-month', '🎂', 'วันเกิดเดือนนี้', 'Patient list with birthday'],
                    ['/reports/wallet-outstanding', '💎', 'Wallet ค้างจ่าย', 'หนี้สินสมาชิก aging'],
                    ['/reports/member-topup-trend', '📈', 'Member Top-up Trend', 'รายเดือน 12 เดือนล่าสุด'],
                ],
                'การให้บริการ' => [
                    ['/reports/doctor-performance', '👨‍⚕️', 'ผลงานหมอ', 'Visit/รายได้/ค่ามือ'],
                    ['/reports/procedure-performance', '💉', 'ผลงานหัตถการ', 'หัตถการขายดี + กำไร'],
                    ['/reports/doctor-utilization', '⏰', 'Doctor Utilization', 'Visit per day per doctor'],
                    ['/reports/room-utilization', '🚪', 'Room Utilization', 'การใช้ห้องตรวจ'],
                    ['/reports/course-outstanding', '🎫', 'คอร์ส Outstanding', 'Session ค้างใช้ + ใกล้หมดอายุ'],
                    ['/reports/lab-turnaround', '🧪', 'Lab Turnaround', 'เวลาออกผลแล็บเฉลี่ย'],
                    ['/reports/photo-upload-frequency', '📷', 'Photo Upload', 'ความถี่อัปโหลดต่อผู้ป่วย'],
                ],
                'คลังสินค้า' => [
                    ['/inventory#low', '⚠️', 'Low Stock', 'ใต้ระดับ reorder'],
                    ['/inventory#expiry', '⏰', 'Expiry Alert', 'ใกล้หมดอายุ 4 ระดับ'],
                    ['/reports/stock-value', '📦', 'Stock Value Snapshot', 'มูลค่าสต็อกแยก warehouse + category'],
                    ['/reports/receiving-history', '📥', 'Receiving History', 'รับเข้าตามผู้ขาย'],
                ],
                'พนักงาน + ค่าคอม' => [
                    ['/commissions', '💸', 'Commission Summary', 'สรุปค่าคอมรายเดือน'],
                    ['/reports/commission-pending-vs-paid', '⏳', 'Commission Pending vs Paid', 'แยก paid + pending'],
                ],
            ];
        @endphp

        @foreach ($sections as $title => $items)
            <h2 class="text-lg font-semibold mt-6 mb-3">{{ $title }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @foreach ($items as [$url, $icon, $name, $desc])
                    <a href="{{ $url }}" class="bg-white rounded-xl shadow p-4 hover:bg-cyan-50">
                        <div class="text-2xl mb-1">{{ $icon }}</div>
                        <div class="font-semibold">{{ $name }}</div>
                        <div class="text-xs text-slate-500 mt-1">{{ $desc }}</div>
                    </a>
                @endforeach
            </div>
        @endforeach
    </main>
</div>
@endsection
