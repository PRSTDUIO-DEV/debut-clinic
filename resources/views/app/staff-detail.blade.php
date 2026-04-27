@extends('layouts.app')
@section('title', 'พนักงาน')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/admin/staff" class="text-cyan-700 hover:underline">← พนักงาน</a>
            <h1 class="font-bold" id="staff-name">…</h1>
            <span id="staff-code" class="text-xs text-slate-500 font-mono"></span>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6 space-y-4">
        <div class="bg-white rounded-xl shadow">
            <div class="border-b flex flex-wrap">
                @foreach (['profile' => 'ข้อมูลทั่วไป', 'roles' => 'Role', 'compensation' => 'ค่าตอบแทน', 'time-clock' => 'Time Clock', 'commission' => 'Commission'] as $k => $name)
                    <button data-tab="{{ $k }}" class="px-4 py-2 hover:bg-slate-50 tab-btn">{{ $name }}</button>
                @endforeach
            </div>
            <div id="tab-content" class="p-4"></div>
        </div>
    </main>
</div>
@endsection

@section('scripts')
<script>
const STAFF_UUID = window.location.pathname.split('/').pop();
let staff = null;
let STAFF_ID = null;

async function loadStaff() {
    const r = await api.call('/admin/staff/'+STAFF_UUID);
    if (!r.ok) return location.href = '/admin/staff';
    staff = r.data.data;
    STAFF_ID = staff.id;
    document.getElementById('staff-name').textContent = staff.name;
    document.getElementById('staff-code').textContent = staff.employee_code || '';
}

function fmt(n) { return (+n||0).toLocaleString(undefined, {maximumFractionDigits:2}); }

const TABS = {
    'profile': () => {
        const p = staff.employee_profile || {};
        return `<form id="form-profile" class="grid grid-cols-2 gap-3 max-w-3xl">
            <label class="block">รหัสพนักงาน<input name="employee_no" value="${p.employee_no || ''}" class="w-full border rounded px-2 py-1.5"></label>
            <label class="block">ตำแหน่ง<input name="position" value="${p.position || staff.position || ''}" class="w-full border rounded px-2 py-1.5"></label>
            <label class="block">แผนก<input name="department" value="${p.department || ''}" class="w-full border rounded px-2 py-1.5"></label>
            <label class="block">วันเริ่มงาน<input name="hire_date" type="date" value="${p.hire_date || ''}" class="w-full border rounded px-2 py-1.5"></label>
            <label class="block">ธนาคาร<input name="bank_name" value="${p.bank_name || ''}" class="w-full border rounded px-2 py-1.5"></label>
            <label class="block">เลขบัญชี<input name="bank_account" value="${p.bank_account || ''}" class="w-full border rounded px-2 py-1.5"></label>
            <label class="block col-span-2">เลขบัตรประชาชน<input name="national_id" value="${p.national_id || ''}" class="w-full border rounded px-2 py-1.5"></label>
            <label class="block">ผู้ติดต่อฉุกเฉิน<input name="emergency_contact" value="${p.emergency_contact || ''}" class="w-full border rounded px-2 py-1.5"></label>
            <label class="block">เบอร์ฉุกเฉิน<input name="emergency_phone" value="${p.emergency_phone || ''}" class="w-full border rounded px-2 py-1.5"></label>
            <label class="block col-span-2">ที่อยู่<textarea name="address" class="w-full border rounded px-2 py-1.5">${p.address || ''}</textarea></label>
            <button type="submit" class="bg-cyan-600 text-white px-3 py-1.5 rounded col-span-2 w-fit">บันทึก</button>
        </form>`;
    },

    'roles': () => `
        <div class="space-y-3">
            <div><b>Role ปัจจุบัน:</b> ${(staff.roles || []).map(r => `<span class="bg-cyan-100 text-cyan-800 px-2 py-1 rounded text-xs">${r.display_name || r.name}</span>`).join(' ') || '—'}</div>
            <form id="form-roles" class="space-y-2">
                <select name="role_ids" multiple size="6" class="w-full border rounded px-2 py-1.5" id="all-roles"></select>
                <span class="text-xs text-slate-400">Ctrl-click เพื่อเลือกหลาย role</span>
                <button type="submit" class="bg-cyan-600 text-white px-3 py-1.5 rounded">บันทึก</button>
            </form>
            <hr>
            <div><b>สาขา:</b> ${(staff.branches || []).map(b => b.name).join(', ') || '—'}</div>
        </div>`,

    'compensation': () => `
        <div class="space-y-3">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100"><tr>
                    <th class="text-left p-2">ประเภท</th><th>Base</th><th>%</th><th>Period</th><th>Active</th><th></th>
                </tr></thead>
                <tbody>${(staff.compensation_rules || []).map(r => `
                    <tr class="border-t">
                        <td class="p-2">${r.type}</td>
                        <td class="text-right">${fmt(r.base_amount)}</td>
                        <td class="text-right">${r.commission_rate || '—'}</td>
                        <td class="text-xs">${r.valid_from} → ${r.valid_to || '∞'}</td>
                        <td class="text-center">${r.is_active ? '✅' : '⛔'}</td>
                        <td><button data-rule="${r.id}" class="text-rose-600 text-xs hover:underline">ลบ</button></td>
                    </tr>`).join('') || '<tr><td colspan="6" class="text-center py-4 text-slate-400">ยังไม่มี</td></tr>'}</tbody>
            </table>
            <form id="form-comp" class="grid grid-cols-3 gap-2 mt-3 max-w-3xl">
                <select name="type" class="border rounded px-2 py-1.5" required>
                    <option value="monthly">รายเดือน</option><option value="hourly">รายชั่วโมง</option><option value="daily">รายวัน</option>
                    <option value="per_procedure">ตามหัตถการ</option><option value="commission">Commission</option>
                </select>
                <input name="base_amount" required type="number" step="0.01" placeholder="Base amount" class="border rounded px-2 py-1.5">
                <input name="commission_rate" type="number" step="0.01" placeholder="% (optional)" class="border rounded px-2 py-1.5">
                <input name="valid_from" required type="date" value="${new Date().toISOString().slice(0, 10)}" class="border rounded px-2 py-1.5">
                <input name="valid_to" type="date" placeholder="(ไม่จำกัด)" class="border rounded px-2 py-1.5">
                <button type="submit" class="bg-cyan-600 text-white px-3 py-1.5 rounded">+ เพิ่ม Rule</button>
            </form>
        </div>`,

    'time-clock': () => `
        <div class="space-y-3">
            <div class="flex gap-2 items-end">
                <label>ปี<input id="tc-year" type="number" value="${new Date().getFullYear()}" class="border rounded px-2 py-1.5 w-24"></label>
                <label>เดือน<input id="tc-month" type="number" min="1" max="12" value="${new Date().getMonth() + 1}" class="border rounded px-2 py-1.5 w-20"></label>
                <button id="btn-tc-load" class="bg-cyan-600 text-white px-3 py-1.5 rounded">โหลด</button>
            </div>
            <div id="tc-summary"></div>
            <div id="tc-table" class="overflow-x-auto"></div>
        </div>`,

    'commission': () => `<div id="comm-content" class="text-sm text-slate-500">โหลด...</div>`,
};

async function activateTab(name) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('border-b-2', b.dataset.tab === name));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('border-cyan-600', b.dataset.tab === name));
    document.getElementById('tab-content').innerHTML = TABS[name]();

    if (name === 'profile') {
        document.getElementById('form-profile').onsubmit = async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target));
            Object.keys(data).forEach(k => { if (!data[k]) delete data[k]; });
            const r = await api.call(`/admin/staff/${STAFF_UUID}/profile`, { method: 'PUT', body: data });
            if (r.ok) { alert('บันทึกแล้ว'); await loadStaff(); }
            else alert(JSON.stringify(r.data?.errors || r.data));
        };
    } else if (name === 'roles') {
        const r = await api.call('/admin/roles');
        if (r.ok) {
            const sel = (staff.roles || []).map(x => x.id);
            document.getElementById('all-roles').innerHTML = (r.data.data || []).map(role =>
                `<option value="${role.id}" ${sel.includes(role.id) ? 'selected' : ''}>${role.display_name || role.name}</option>`).join('');
        }
        document.getElementById('form-roles').onsubmit = async (e) => {
            e.preventDefault();
            const ids = Array.from(e.target.role_ids.selectedOptions).map(o => +o.value);
            const r = await api.call(`/admin/staff/${STAFF_UUID}/assign-roles`, { method: 'POST', body: { role_ids: ids } });
            if (r.ok) { alert('บันทึกแล้ว'); await loadStaff(); activateTab('roles'); }
        };
    } else if (name === 'compensation') {
        document.getElementById('form-comp').onsubmit = async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target));
            if (!data.commission_rate) delete data.commission_rate;
            if (!data.valid_to) delete data.valid_to;
            const r = await api.call(`/admin/staff/${STAFF_UUID}/compensation-rules`, { method: 'POST', body: data });
            if (r.ok) { e.target.reset(); await loadStaff(); activateTab('compensation'); }
            else alert(JSON.stringify(r.data?.errors || r.data));
        };
        document.querySelectorAll('[data-rule]').forEach(b => b.onclick = async () => {
            if (!confirm('ลบ rule นี้?')) return;
            const r = await api.call(`/admin/staff/${STAFF_UUID}/compensation-rules/${b.dataset.rule}`, { method: 'DELETE' });
            if (r.ok) { await loadStaff(); activateTab('compensation'); }
        });
    } else if (name === 'time-clock') {
        document.getElementById('btn-tc-load').onclick = loadTimeClock;
        await loadTimeClock();
    } else if (name === 'commission') {
        const r = await api.call(`/commissions?user_id=${STAFF_ID}`);
        if (r.ok) {
            const rows = r.data.data?.data || r.data.data || [];
            const list = (Array.isArray(rows) ? rows : []).slice(0, 50);
            document.getElementById('comm-content').innerHTML = list.length ?
                '<table class="min-w-full text-sm"><thead class="bg-slate-100"><tr><th class="px-2 py-1 text-left">วันที่</th><th>จำนวน</th><th>Paid</th></tr></thead><tbody>' +
                list.map(x => `<tr class="border-t"><td class="px-2 py-1">${x.commission_date || x.created_at}</td><td class="text-right">${fmt(x.amount)}</td><td class="text-center">${x.is_paid ? '✅' : '⏳'}</td></tr>`).join('') + '</tbody></table>'
                : '<div class="text-slate-400">ไม่มีรายการ</div>';
        }
    }
}

async function loadTimeClock() {
    const y = +document.getElementById('tc-year').value;
    const m = +document.getElementById('tc-month').value;
    const sum = await api.call(`/admin/time-clocks/summary?user_id=${STAFF_ID}&year=${y}&month=${m}`);
    if (sum.ok) {
        const d = sum.data.data;
        document.getElementById('tc-summary').innerHTML = `
            <div class="grid grid-cols-4 gap-2 text-sm">
                <div class="bg-cyan-50 rounded p-2"><div class="text-xs text-slate-500">รวมชั่วโมง</div><div class="font-bold text-lg">${d.total_hours} h</div></div>
                <div class="bg-emerald-50 rounded p-2"><div class="text-xs text-slate-500">วันทำงาน</div><div class="font-bold text-lg">${d.days_worked}</div></div>
                <div class="bg-amber-50 rounded p-2"><div class="text-xs text-slate-500">มาสาย (ครั้ง)</div><div class="font-bold text-lg">${d.late_count}</div></div>
                <div class="bg-violet-50 rounded p-2"><div class="text-xs text-slate-500">OT</div><div class="font-bold text-lg">${d.overtime_hours} h</div></div>
            </div>`;
    }
    const list = await api.call(`/admin/time-clocks?user_id=${STAFF_ID}&from=${y}-${String(m).padStart(2, '0')}-01&to=${y}-${String(m).padStart(2, '0')}-31`);
    if (list.ok) {
        const rows = list.data.data?.data || [];
        document.getElementById('tc-table').innerHTML = `
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100"><tr><th class="text-left p-2">Clock In</th><th>Clock Out</th><th>นาทีรวม</th><th>สาย</th><th>OT</th><th>Source</th></tr></thead>
                <tbody>${rows.map(r => `
                    <tr class="border-t">
                        <td class="p-2 text-xs">${r.clock_in?.replace('T', ' ').slice(0, 16) || '—'}</td>
                        <td class="text-xs">${r.clock_out?.replace('T', ' ').slice(0, 16) || '—'}</td>
                        <td class="text-right">${r.total_minutes ?? '—'}</td>
                        <td class="text-right ${r.late_minutes > 0 ? 'text-amber-600 font-bold' : ''}">${r.late_minutes}</td>
                        <td class="text-right">${r.overtime_minutes}</td>
                        <td class="text-center text-xs">${r.source}</td>
                    </tr>`).join('') || '<tr><td colspan="6" class="text-center py-4 text-slate-400">ไม่มีรายการ</td></tr>'}</tbody>
            </table>`;
    }
}

document.querySelectorAll('.tab-btn').forEach(b => b.onclick = () => activateTab(b.dataset.tab));

(async function () {
    if (!api.token()) return location.href = '/login';
    await loadStaff();
    activateTab('profile');
})();
</script>
@endsection
