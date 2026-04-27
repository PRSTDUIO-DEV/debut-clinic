@extends('layouts.app')
@section('title', 'Dashboard')

@section('body')
<main class="max-w-7xl mx-auto px-3 sm:px-4 py-4 sm:py-6 space-y-5">
    {{-- Welcome + Alerts grid --}}
    <section class="bg-white rounded-xl shadow p-4 sm:p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <div>
                <h2 class="text-base sm:text-lg font-semibold">ยินดีต้อนรับ <span id="user-name">…</span></h2>
                <div class="text-xs text-slate-500 mt-0.5"><span id="user-email"></span></div>
            </div>
            <div class="flex flex-wrap gap-1.5" id="role-badges"></div>
        </div>
    </section>

    {{-- Alert KPIs (today's signal) --}}
    <section id="alerts-grid" class="grid grid-cols-2 md:grid-cols-4 gap-2 sm:gap-3"></section>

    {{-- Detail panels --}}
    <section id="alerts-detail" class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4"></section>

    {{-- Main menu — grouped sections --}}
    <section class="space-y-5" id="menu-sections">
        {{-- 1. Daily Operations --}}
        <div data-section data-perms="patients.view,visits.view,appointments.view">
            <h3 class="text-sm font-semibold text-slate-600 mb-2 px-1">📋 ใช้งานประจำวัน</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-3">
                <a href="/pos" class="tile bg-cyan-50 hover:bg-cyan-100" data-perms="visits.checkout">
                    <div class="tile-icon">💳</div>
                    <div class="tile-name">POS</div>
                    <div class="tile-desc">เปิด Visit + ขาย/ปิดบิล</div>
                </a>
                <a href="/patients" class="tile bg-blue-50 hover:bg-blue-100" data-perms="patients.view">
                    <div class="tile-icon">👤</div>
                    <div class="tile-name">ผู้ป่วย</div>
                    <div class="tile-desc">รายการ + OPD Card</div>
                </a>
                <a href="/appointments" class="tile bg-amber-50 hover:bg-amber-100" data-perms="appointments.view">
                    <div class="tile-icon">📅</div>
                    <div class="tile-name">นัดหมาย</div>
                    <div class="tile-desc">ปฏิทินรายวัน/สัปดาห์</div>
                </a>
                <a href="/follow-ups" class="tile bg-rose-50 hover:bg-rose-100" data-perms="patients.view">
                    <div class="tile-icon">📞</div>
                    <div class="tile-name">ติดตามผล</div>
                    <div class="tile-desc">Priority + Quick Book</div>
                </a>
                <a href="/lab/orders" class="tile bg-sky-50 hover:bg-sky-100" data-perms="lab.view">
                    <div class="tile-icon">🧪</div>
                    <div class="tile-name">Lab Orders</div>
                    <div class="tile-desc">สั่งตรวจ + บันทึกผล</div>
                </a>
                <a href="/members" class="tile bg-fuchsia-50 hover:bg-fuchsia-100" data-perms="member.view">
                    <div class="tile-icon">💎</div>
                    <div class="tile-name">สมาชิกเงินฝาก</div>
                    <div class="tile-desc">เติม/ใช้/คืนเงิน</div>
                </a>
                <a href="/courses" class="tile bg-violet-50 hover:bg-violet-100" data-perms="course.view">
                    <div class="tile-icon">🎫</div>
                    <div class="tile-name">คอร์สรักษา</div>
                    <div class="tile-desc">Session tracker</div>
                </a>
                <a href="/qc/runs" class="tile bg-teal-50 hover:bg-teal-100" data-perms="qc.view">
                    <div class="tile-icon">✅</div>
                    <div class="tile-name">QC Checklist</div>
                    <div class="tile-desc">ตรวจคุณภาพรายวัน</div>
                </a>
            </div>
        </div>

        {{-- 2. Inventory --}}
        <div data-section data-perms="inventory.view">
            <h3 class="text-sm font-semibold text-slate-600 mb-2 px-1">📦 คลังสินค้า</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-3">
                <a href="/inventory" class="tile bg-teal-50 hover:bg-teal-100">
                    <div class="tile-icon">📦</div>
                    <div class="tile-name">คลังสินค้า</div>
                    <div class="tile-desc">สต็อก + รับเข้า + เบิก</div>
                </a>
                <a href="/inventory#expiry" class="tile bg-orange-50 hover:bg-orange-100">
                    <div class="tile-icon">⏰</div>
                    <div class="tile-name">วันหมดอายุ</div>
                    <div class="tile-desc">เตือน 4 ระดับ</div>
                </a>
                <a href="/inventory/receiving" class="tile bg-emerald-50 hover:bg-emerald-100" data-perms="inventory.receive">
                    <div class="tile-icon">📥</div>
                    <div class="tile-name">รับเข้า</div>
                    <div class="tile-desc">Goods Receiving</div>
                </a>
                <a href="/inventory/requisitions" class="tile bg-cyan-50 hover:bg-cyan-100" data-perms="inventory.requisition.create">
                    <div class="tile-icon">📤</div>
                    <div class="tile-name">เบิกจ่าย</div>
                    <div class="tile-desc">Requisitions</div>
                </a>
            </div>
        </div>

        {{-- 3. Finance + Accounting --}}
        <div data-section data-perms="finance.view,accounting.coa.view">
            <h3 class="text-sm font-semibold text-slate-600 mb-2 px-1">💰 การเงิน + บัญชี</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-3">
                <a href="/closing" class="tile bg-yellow-50 hover:bg-yellow-100" data-perms="finance.closing.view">
                    <div class="tile-icon">📒</div>
                    <div class="tile-name">ปิดยอดประจำวัน</div>
                    <div class="tile-desc">นับเงินสด + variance</div>
                </a>
                <a href="/expenses" class="tile bg-red-50 hover:bg-red-100" data-perms="finance.expense.view">
                    <div class="tile-icon">💸</div>
                    <div class="tile-name">รายจ่าย</div>
                    <div class="tile-desc">บันทึก expense</div>
                </a>
                <a href="/commissions" class="tile bg-emerald-50 hover:bg-emerald-100" data-perms="finance.commission.view">
                    <div class="tile-icon">💸</div>
                    <div class="tile-name">ค่าคอม / ค่ามือ</div>
                    <div class="tile-desc">สรุปรายเดือน</div>
                </a>
                <a href="/accounting/ledger" class="tile bg-stone-100 hover:bg-stone-200" data-perms="accounting.ledger.view">
                    <div class="tile-icon">📚</div>
                    <div class="tile-name">บัญชี + งบ</div>
                    <div class="tile-desc">Ledger / Trial Balance</div>
                </a>
                <a href="/accounting/pr" class="tile bg-amber-50 hover:bg-amber-100" data-perms="accounting.pr.view">
                    <div class="tile-icon">📝</div>
                    <div class="tile-name">PR / PO</div>
                    <div class="tile-desc">ใบขอ + สั่งซื้อ</div>
                </a>
                <a href="/accounting/disbursements" class="tile bg-sky-50 hover:bg-sky-100" data-perms="accounting.disbursement.view">
                    <div class="tile-icon">💵</div>
                    <div class="tile-name">เบิกจ่าย</div>
                    <div class="tile-desc">Disbursements</div>
                </a>
                <a href="/accounting/tax-invoices" class="tile bg-indigo-50 hover:bg-indigo-100" data-perms="accounting.tax.view">
                    <div class="tile-icon">🧾</div>
                    <div class="tile-name">ใบกำกับภาษี</div>
                    <div class="tile-desc">Tax Invoices</div>
                </a>
            </div>
        </div>

        {{-- 4. Reports + MIS --}}
        <div data-section data-perms="finance.reports.view">
            <h3 class="text-sm font-semibold text-slate-600 mb-2 px-1">📊 รายงาน + MIS</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-3">
                <a href="/mis" class="tile text-white" style="background: linear-gradient(to bottom right, #06b6d4, #a855f7);">
                    <div class="tile-icon">📊</div>
                    <div class="tile-name">MIS Executive</div>
                    <div class="tile-desc text-cyan-100">KPI + trend + top performers</div>
                </a>
                <a href="/reports" class="tile bg-slate-100 hover:bg-slate-200">
                    <div class="tile-icon">📋</div>
                    <div class="tile-name">Reports Library</div>
                    <div class="tile-desc">20+ รายงาน</div>
                </a>
                <a href="/reports/daily-pl" class="tile bg-blue-50 hover:bg-blue-100">
                    <div class="tile-icon">📊</div>
                    <div class="tile-name">P/L รายวัน</div>
                    <div class="tile-desc">รายได้ - ต้นทุน</div>
                </a>
                <a href="/reports/payment-mix" class="tile bg-purple-50 hover:bg-purple-100">
                    <div class="tile-icon">💳</div>
                    <div class="tile-name">Payment Mix</div>
                    <div class="tile-desc">เงินสด/บัตร + MDR</div>
                </a>
                <a href="/reports/doctor-performance" class="tile bg-green-50 hover:bg-green-100">
                    <div class="tile-icon">👨‍⚕️</div>
                    <div class="tile-name">ผลงานหมอ</div>
                    <div class="tile-desc">Visit/รายได้/ค่ามือ</div>
                </a>
                <a href="/reports/procedure-performance" class="tile bg-purple-50 hover:bg-purple-100">
                    <div class="tile-icon">💉</div>
                    <div class="tile-name">ผลงานหัตถการ</div>
                    <div class="tile-desc">หัตถการขายดี + กำไร</div>
                </a>
            </div>
        </div>

        {{-- 5. Marketing + CRM --}}
        <div data-section data-perms="crm.view,marketing.coupon.view">
            <h3 class="text-sm font-semibold text-slate-600 mb-2 px-1">🎟️ การตลาด + CRM</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-3">
                <a href="/crm/campaigns" class="tile bg-pink-50 hover:bg-pink-100" data-perms="crm.broadcast.send">
                    <div class="tile-icon">📣</div>
                    <div class="tile-name">CRM Campaigns</div>
                    <div class="tile-desc">Broadcast LINE/SMS</div>
                </a>
                <a href="/crm/segments" class="tile bg-rose-50 hover:bg-rose-100" data-perms="crm.segments.manage">
                    <div class="tile-icon">🎯</div>
                    <div class="tile-name">CRM Segments</div>
                    <div class="tile-desc">กลุ่มเป้าหมาย</div>
                </a>
                <a href="/marketing/coupons" class="tile bg-pink-50 hover:bg-pink-100" data-perms="marketing.coupon.view">
                    <div class="tile-icon">🎟️</div>
                    <div class="tile-name">คูปอง</div>
                    <div class="tile-desc">สร้าง + bulk generate</div>
                </a>
                <a href="/marketing/promotions" class="tile bg-fuchsia-50 hover:bg-fuchsia-100" data-perms="marketing.promotion.view">
                    <div class="tile-icon">🎁</div>
                    <div class="tile-name">โปรโมชั่น</div>
                    <div class="tile-desc">Rule engine</div>
                </a>
                <a href="/marketing/influencers" class="tile bg-violet-50 hover:bg-violet-100" data-perms="marketing.influencer.view">
                    <div class="tile-icon">📢</div>
                    <div class="tile-name">Influencer</div>
                    <div class="tile-desc">UTM + ROI report</div>
                </a>
                <a href="/marketing/reviews" class="tile bg-amber-50 hover:bg-amber-100" data-perms="marketing.review.view">
                    <div class="tile-icon">⭐</div>
                    <div class="tile-name">รีวิว</div>
                    <div class="tile-desc">Aggregate + Moderate</div>
                </a>
                <a href="/admin/birthday-campaigns" class="tile bg-pink-50 hover:bg-pink-100" data-perms="crm.broadcast.send">
                    <div class="tile-icon">🎂</div>
                    <div class="tile-name">วันเกิด</div>
                    <div class="tile-desc">Campaign templates</div>
                </a>
            </div>
        </div>

        {{-- 6. Admin --}}
        <div data-section data-perms="users.view,settings.view,system.health.view">
            <h3 class="text-sm font-semibold text-slate-600 mb-2 px-1">⚙️ จัดการระบบ</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-3">
                <a href="/admin/staff" class="tile bg-indigo-50 hover:bg-indigo-100" data-perms="users.view">
                    <div class="tile-icon">👥</div>
                    <div class="tile-name">พนักงาน + Payroll</div>
                    <div class="tile-desc">HR / Time Clock / เงินเดือน</div>
                </a>
                <a href="/admin/settings" class="tile bg-slate-100 hover:bg-slate-200" data-perms="settings.view">
                    <div class="tile-icon">⚙️</div>
                    <div class="tile-name">ตั้งค่าระบบ</div>
                    <div class="tile-desc">สาขา / ห้อง / ราคา / กลุ่ม</div>
                </a>
                <a href="/admin/permissions" class="tile bg-amber-50 hover:bg-amber-100" data-perms="roles.manage">
                    <div class="tile-icon">🔐</div>
                    <div class="tile-name">Roles + Perms</div>
                    <div class="tile-desc">Permission matrix</div>
                </a>
                <a href="/admin/messaging-providers" class="tile bg-cyan-50 hover:bg-cyan-100" data-perms="messaging.providers.view">
                    <div class="tile-icon">📡</div>
                    <div class="tile-name">LINE / SMS</div>
                    <div class="tile-desc">Provider config</div>
                </a>
                <a href="/audit-logs" class="tile bg-stone-100 hover:bg-stone-200" data-perms="audit.view">
                    <div class="tile-icon">📜</div>
                    <div class="tile-name">Audit Log</div>
                    <div class="tile-desc">Diff + Export CSV</div>
                </a>
                <a href="/admin/system-health" class="tile bg-rose-50 hover:bg-rose-100" data-perms="system.health.view">
                    <div class="tile-icon">🩺</div>
                    <div class="tile-name">System Health</div>
                    <div class="tile-desc">DB / Queue / Cron</div>
                </a>
                <a href="/qc/checklists" class="tile bg-teal-50 hover:bg-teal-100" data-perms="qc.manage">
                    <div class="tile-icon">📋</div>
                    <div class="tile-name">QC Checklists</div>
                    <div class="tile-desc">ตั้งค่า checklist</div>
                </a>
                <a href="/admin/consent-templates" class="tile bg-stone-100 hover:bg-stone-200" data-perms="consent.template.manage">
                    <div class="tile-icon">📜</div>
                    <div class="tile-name">Consent Templates</div>
                    <div class="tile-desc">แม่แบบเอกสาร</div>
                </a>
            </div>
        </div>
    </section>
</main>

<style>
.tile {
    display: block;
    padding: 0.875rem 0.625rem;
    border-radius: 0.75rem;
    text-align: center;
    transition: transform 0.1s, box-shadow 0.1s;
    min-height: 90px;
}
.tile:hover { box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); }
.tile:active { transform: scale(0.97); }
.tile-icon { font-size: 1.75rem; margin-bottom: 0.25rem; }
.tile-name { font-weight: 600; font-size: 0.875rem; }
.tile-desc { font-size: 0.7rem; color: #64748b; margin-top: 0.125rem; line-height: 1.3; }
.tile[style*="gradient"] .tile-desc { color: rgba(255,255,255,0.85); }
@media (min-width: 640px) {
    .tile { padding: 1rem 0.75rem; min-height: 110px; }
    .tile-icon { font-size: 2rem; }
    .tile-name { font-size: 0.95rem; }
    .tile-desc { font-size: 0.75rem; }
}
</style>
@endsection

@section('scripts')
<script>
async function bootstrap() {
    if (!api.token()) { window.location.href = '/login'; return; }
    const me = await api.call('/auth/me');
    if (!me.ok) { api.clear(); window.location.href = '/login'; return; }

    const u = me.data.data.user;
    const perms = new Set(me.data.data.permissions || []);
    const hasPerm = (p) => {
        if (perms.has(p)) return true;
        const parts = p.split('.');
        for (let i = parts.length - 1; i > 0; i--) {
            if (perms.has(parts.slice(0, i).join('.') + '.*')) return true;
        }
        return false;
    };

    document.getElementById('user-name').textContent = u.name;
    document.getElementById('user-email').textContent = u.email;
    document.getElementById('role-badges').innerHTML = (u.roles || []).map(r =>
        `<span class="px-2 py-0.5 rounded-full bg-cyan-100 text-cyan-800 text-xs font-medium">${r}</span>`).join('');

    // Permission-aware tile + section visibility
    document.querySelectorAll('[data-perms]').forEach(el => {
        const required = el.dataset.perms.split(',').map(p => p.trim()).filter(Boolean);
        const allowed = required.length === 0 || required.some(p => hasPerm(p));
        if (!allowed) el.style.display = 'none';
    });
    // Hide section if no visible tiles
    document.querySelectorAll('[data-section]').forEach(section => {
        const visibleTiles = Array.from(section.querySelectorAll('.tile')).filter(t => t.style.display !== 'none');
        if (visibleTiles.length === 0) section.style.display = 'none';
    });

    // Dashboard widgets summary
    const sumRes = await api.call('/dashboard/summary');
    if (sumRes.ok) {
        const s = sumRes.data.data;
        document.getElementById('alerts-grid').innerHTML = `
            <a href="/notifications" class="block p-3 rounded-xl ${s.unread_notifications > 0 ? 'bg-cyan-100 hover:bg-cyan-200' : 'bg-slate-100 hover:bg-slate-200'} text-center transition">
                <div class="text-xs text-slate-600">การแจ้งเตือนยังไม่อ่าน</div>
                <div class="text-2xl font-bold ${s.unread_notifications > 0 ? 'text-cyan-700' : 'text-slate-700'}">${s.unread_notifications}</div>
                <div class="text-xs">🔔</div>
            </a>
            <a href="/follow-ups" class="block p-3 rounded-xl bg-rose-50 hover:bg-rose-100 text-center transition">
                <div class="text-xs text-rose-600">ติดตามด่วน</div>
                <div class="text-2xl font-bold text-rose-700">${s.urgent_follow_ups.count}</div>
                <div class="text-xs">🚨</div>
            </a>
            <a href="/inventory#expiry" class="block p-3 rounded-xl bg-amber-50 hover:bg-amber-100 text-center transition">
                <div class="text-xs text-amber-700">ใกล้/หมดอายุ</div>
                <div class="text-2xl font-bold text-amber-700">${s.expired_stock + s.red_expiry_stock}</div>
                <div class="text-xs">⏰ หมด ${s.expired_stock} • ใกล้ ${s.red_expiry_stock}</div>
            </a>
            <a href="/reports/birthday-this-month" class="block p-3 rounded-xl bg-pink-50 hover:bg-pink-100 text-center transition">
                <div class="text-xs text-pink-700">วันเกิดเดือนนี้</div>
                <div class="text-2xl font-bold text-pink-700">${s.birthday_this_month.count}</div>
                <div class="text-xs">🎂</div>
            </a>`;

        const urgentTop = (s.urgent_follow_ups.top || []).slice(0, 5);
        const bdayTop = (s.birthday_this_month.top || []).slice(0, 5);
        document.getElementById('alerts-detail').innerHTML = `
            <div class="bg-white rounded-xl shadow p-4">
                <h3 class="font-semibold mb-2 text-sm">🚨 ติดตามด่วนล่าสุด</h3>
                ${urgentTop.length === 0 ? '<div class="text-slate-400 text-sm text-center py-3">ไม่มีรายการ</div>' :
                  urgentTop.map(u => `
                    <a href="/patients/${u.patient_uuid}" class="border-t flex justify-between items-center py-2 text-sm hover:bg-slate-50 -mx-1 px-1 rounded">
                        <span class="text-cyan-700">${u.patient_name} <span class="text-xs text-slate-500 font-mono">${u.patient_hn}</span></span>
                        <span class="text-xs px-2 py-0.5 rounded-full ${u.priority==='critical'?'bg-rose-100 text-rose-800':'bg-amber-100 text-amber-800'}">${u.priority}</span>
                    </a>`).join('')}
            </div>
            <div class="bg-white rounded-xl shadow p-4">
                <h3 class="font-semibold mb-2 text-sm">🎂 วันเกิดเดือนนี้</h3>
                ${bdayTop.length === 0 ? '<div class="text-slate-400 text-sm text-center py-3">ไม่มีรายการ</div>' :
                  bdayTop.map(b => `
                    <a href="/patients/${b.uuid}" class="border-t flex justify-between items-center py-2 text-sm hover:bg-slate-50 -mx-1 px-1 rounded">
                        <span class="text-cyan-700">${b.name} <span class="text-xs text-slate-500 font-mono">${b.hn}</span></span>
                        <span class="text-xs text-slate-500">${b.birthday}</span>
                    </a>`).join('')}
            </div>`;
    }
}
bootstrap();
</script>
@endsection
