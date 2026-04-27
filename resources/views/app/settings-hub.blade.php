@extends('layouts.app')
@section('title', 'การตั้งค่า')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">⚙️ การตั้งค่าระบบ</h1>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6">
        @php
            $sections = [
                '🏢 องค์กร' => [
                    ['/admin/branches', '🏥', 'สาขา', 'จัดการสาขา (super_admin)'],
                    ['/admin/rooms', '🚪', 'ห้องตรวจ', 'ห้องในแต่ละสาขา'],
                    ['/admin/customer-groups', '🎯', 'กลุ่มลูกค้า', 'VIP / สมาชิก / ทั่วไป + ส่วนลด'],
                ],
                '💰 การเงิน' => [
                    ['/admin/banks', '🏦', 'ธนาคาร', 'บัญชี + MDR rate'],
                    ['/admin/suppliers', '🚚', 'ผู้ขาย', 'ข้อมูลผู้ขาย + เครดิต'],
                    ['/admin/expense-categories', '💸', 'หมวดรายจ่าย', 'จัดหมวด expense'],
                ],
                '💉 บริการ + สินค้า' => [
                    ['/admin/procedures', '💉', 'หัตถการ', 'รายการบริการ + ราคา + คอมมิชชั่น'],
                    ['/admin/products', '📦', 'สินค้า', 'รายการสินค้า + คลัง'],
                    ['/admin/product-categories', '📂', 'หมวดสินค้า', 'จัดหมวดสินค้า + คอมมิชชั่น'],
                    ['/admin/lab-tests', '🧪', 'แบบ Lab Test', 'ค่าอ้างอิง + flag'],
                    ['/admin/consent-templates', '📝', 'ฟอร์มยินยอม', 'Template สำหรับเซ็น'],
                ],
                '👥 พนักงาน + ระบบ' => [
                    ['/admin/staff', '👥', 'พนักงาน', 'List + profile + role + payroll'],
                    ['/admin/permissions', '🔐', 'Roles & Permissions', 'จัดการ role'],
                    ['/admin/messaging-providers', '📱', 'ผู้ให้บริการส่งข้อความ', 'LINE / SMS config'],
                    ['/admin/birthday-campaigns', '🎂', 'แคมเปญวันเกิด', 'จัดการ template'],
                    ['/admin/follow-up-rules', '⏰', 'Rule Follow-up', 'priority calc'],
                    ['/admin/line-rich-menu', '📋', 'LINE Rich Menu', 'Builder UI'],
                ],
            ];
        @endphp

        @foreach ($sections as $title => $items)
            <h2 class="text-lg font-semibold mt-6 mb-3">{{ $title }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @foreach ($items as [$url, $icon, $name, $desc])
                    <a href="{{ $url }}" class="bg-white rounded-xl shadow p-4 hover:bg-cyan-50 hover:shadow-md transition">
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
