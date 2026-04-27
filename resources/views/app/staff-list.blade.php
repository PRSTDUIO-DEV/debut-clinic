@extends('layouts.app')
@section('title', 'พนักงาน')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">👥 พนักงาน</h1>
            <a href="/admin/payroll" class="text-sm text-cyan-700 hover:underline ml-2">Payroll</a>
            <a href="/time-clock" class="text-sm text-cyan-700 hover:underline">Time Clock Kiosk</a>
            <input id="search" placeholder="ค้นหา ชื่อ/อีเมล/รหัส..." class="border rounded px-2 py-1 text-sm">
            <select id="role-filter" class="border rounded px-2 py-1 text-sm">
                <option value="">ทุก role</option>
                <option>doctor</option><option>nurse</option><option>receptionist</option>
                <option>pharmacist</option><option>accountant</option><option>marketing_staff</option>
                <option>branch_admin</option><option>super_admin</option>
            </select>
            <button id="btn-create" class="ml-auto bg-cyan-600 text-white px-3 py-1.5 rounded text-sm">+ พนักงานใหม่</button>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <div class="bg-white rounded-xl shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100"><tr>
                    <th class="px-3 py-2 text-left">รหัส</th>
                    <th class="text-left">ชื่อ</th>
                    <th class="text-left">ตำแหน่ง</th>
                    <th class="text-left">Roles</th>
                    <th class="text-left">อีเมล</th>
                    <th>Active</th>
                </tr></thead>
                <tbody id="rows"></tbody>
            </table>
        </div>
    </main>
</div>

<dialog id="dlg" class="rounded-xl p-0 w-[480px] max-w-full">
    <form method="dialog" id="form" class="p-4 space-y-2">
        <h3 class="font-bold">+ พนักงานใหม่</h3>
        <input name="name" required placeholder="ชื่อ" class="w-full border rounded px-2 py-1.5">
        <input name="employee_code" placeholder="รหัสพนักงาน" class="w-full border rounded px-2 py-1.5 font-mono">
        <input name="email" required type="email" placeholder="อีเมล" class="w-full border rounded px-2 py-1.5">
        <input name="position" placeholder="ตำแหน่ง" class="w-full border rounded px-2 py-1.5">
        <input name="phone" placeholder="เบอร์" class="w-full border rounded px-2 py-1.5">
        <div class="grid grid-cols-2 gap-2">
            <input name="password" required type="password" placeholder="Password (≥8 ตัว)" class="border rounded px-2 py-1.5">
            <input name="pin" type="password" inputmode="numeric" pattern="[0-9]*" placeholder="PIN (4-6 หลัก)" class="border rounded px-2 py-1.5 font-mono">
        </div>
        <label class="text-sm">Roles
            <select name="role_ids[]" multiple size="5" class="w-full border rounded px-2 py-1.5" id="role-select"></select>
            <span class="text-xs text-slate-400">กด Ctrl เพื่อเลือกหลาย role</span>
        </label>
        <label class="flex items-center gap-2"><input name="is_doctor" type="checkbox"> เป็นแพทย์</label>
        <div class="flex gap-2 pt-2"><button type="submit" class="bg-cyan-600 text-white px-3 py-1.5 rounded">บันทึก</button>
            <button type="button" onclick="document.getElementById('dlg').close()" class="px-3 py-1.5">ยกเลิก</button></div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
async function loadRoles() {
    const r = await api.call('/admin/roles');
    if (!r.ok) return;
    const opts = (r.data.data || []).map(role => `<option value="${role.id}">${role.display_name || role.name}</option>`).join('');
    document.getElementById('role-select').innerHTML = opts;
}

async function loadStaff() {
    const params = new URLSearchParams();
    if (document.getElementById('search').value) params.set('search', document.getElementById('search').value);
    if (document.getElementById('role-filter').value) params.set('role', document.getElementById('role-filter').value);
    const r = await api.call('/admin/staff?'+params);
    if (!r.ok) return;
    const rows = r.data.data?.data || [];
    document.getElementById('rows').innerHTML = rows.map(u => `
        <tr class="border-t">
            <td class="px-3 py-1.5 font-mono text-xs">${u.employee_code || '—'}</td>
            <td><a href="/admin/staff/${u.uuid}" class="text-cyan-700 hover:underline font-semibold">${u.name}</a></td>
            <td class="text-xs">${u.position || '—'}</td>
            <td class="text-xs">${(u.roles || []).map(r => `<span class="bg-slate-100 px-2 py-0.5 rounded mr-1">${r.display_name || r.name}</span>`).join('')}</td>
            <td class="text-xs">${u.email}</td>
            <td class="text-center">${u.is_active ? '✅' : '⛔'}</td>
        </tr>`).join('');
}

document.getElementById('btn-create').onclick = () => document.getElementById('dlg').showModal();
document.getElementById('search').oninput = () => { clearTimeout(window.__t); window.__t = setTimeout(loadStaff, 300); };
document.getElementById('role-filter').onchange = loadStaff;

document.getElementById('form').onsubmit = async (e) => {
    e.preventDefault();
    const f = new FormData(e.target);
    const data = {
        name: f.get('name'), email: f.get('email'),
        password: f.get('password'),
        pin: f.get('pin') || null,
        employee_code: f.get('employee_code') || null,
        position: f.get('position') || null,
        phone: f.get('phone') || null,
        is_doctor: f.get('is_doctor') === 'on',
        role_ids: f.getAll('role_ids[]').map(x => +x).filter(Boolean),
    };
    const r = await api.call('/admin/staff', { method: 'POST', body: data });
    if (r.ok) { document.getElementById('dlg').close(); e.target.reset(); loadStaff(); }
    else alert(JSON.stringify(r.data?.errors || r.data));
};

(async function () {
    if (!api.token()) return location.href = '/login';
    await Promise.all([loadRoles(), loadStaff()]);
})();
</script>
@endsection
