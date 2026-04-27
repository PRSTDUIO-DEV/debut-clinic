@extends('layouts.app')
@section('title', 'Follow-up Rules')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">🚨 Follow-up Rules (จัดลำดับความเร่งด่วน)</h1>
            <a href="/admin/birthday-campaigns" class="ml-auto text-sm text-cyan-700 hover:underline">Birthday</a>
            <button id="btn-new" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">+ Rule ใหม่</button>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-6">
        <section class="bg-white rounded-xl shadow p-4">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="px-3 py-2 text-left">ชื่อ</th>
                        <th class="px-3 py-2 text-center">Priority</th>
                        <th class="px-3 py-2 text-left">เงื่อนไข</th>
                        <th class="px-3 py-2 text-center">Notify</th>
                        <th class="px-3 py-2 text-center">เปิด</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="rows"></tbody>
            </table>
        </section>
    </main>
</div>

<dialog id="form-dialog" class="rounded-xl p-0 w-[640px] max-w-full">
    <form id="form" class="p-4 space-y-3">
        <h3 id="form-title" class="font-semibold text-lg">Rule ใหม่</h3>
        <input type="hidden" name="id">
        <div class="grid grid-cols-2 gap-2">
            <label class="text-sm col-span-2">ชื่อ
                <input name="name" required maxlength="150" class="w-full border rounded px-2 py-1 mt-1">
            </label>
            <label class="text-sm">Priority
                <select name="priority" required class="w-full border rounded px-2 py-1 mt-1">
                    <option value="critical">วิกฤต (critical)</option>
                    <option value="high">สูง (high)</option>
                    <option value="normal">ปกติ (normal)</option>
                    <option value="low">ต่ำ (low)</option>
                </select>
            </label>
            <label class="text-sm">Channel
                <select name="preferred_channel" class="w-full border rounded px-2 py-1 mt-1">
                    <option value="in_app">In-app</option>
                    <option value="line">LINE</option>
                    <option value="email">Email</option>
                </select>
            </label>
            <label class="text-sm col-span-2">เงื่อนไข
                <select name="condition_type" class="w-full border rounded px-2 py-1 mt-1">
                    <option value="overdue_days">เลยกำหนดนัด N วัน</option>
                    <option value="vip_overdue_days">VIP เลยนัด N วัน</option>
                    <option value="course_expiring_days">คอร์สใกล้หมดอายุ N วัน</option>
                    <option value="wallet_low_amount">Wallet ต่ำกว่า N บาท</option>
                    <option value="dormant_days">ไม่มาคลินิก N วัน</option>
                </select>
            </label>
            <label class="text-sm" id="lbl-days">ค่า (วัน/บาท)
                <input name="cv_value" type="number" min="0" required class="w-full border rounded px-2 py-1 mt-1" value="3">
            </label>
            <div></div>
            <label class="text-sm flex items-center gap-2">
                <input type="checkbox" name="notify_branch_admin" checked> แจ้งผู้จัดการสาขา
            </label>
            <label class="text-sm flex items-center gap-2">
                <input type="checkbox" name="notify_doctor"> แจ้งหมอ (เฉพาะ critical)
            </label>
            <label class="text-sm flex items-center gap-2 col-span-2">
                <input type="checkbox" name="is_active" checked> เปิดใช้งาน
            </label>
        </div>
        <div class="flex justify-end gap-2">
            <button type="button" class="dlg-cancel px-3 py-1 rounded bg-slate-100">ยกเลิก</button>
            <button type="submit" class="px-3 py-1 rounded bg-cyan-600 text-white">บันทึก</button>
        </div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
const PRIO_LABEL = { critical: 'วิกฤต', high: 'สูง', normal: 'ปกติ', low: 'ต่ำ' };
const PRIO_COLOR = {
    critical: 'bg-rose-100 text-rose-800',
    high: 'bg-amber-100 text-amber-800',
    normal: 'bg-cyan-100 text-cyan-800',
    low: 'bg-slate-100 text-slate-700',
};
const COND_LABEL = {
    overdue_days: 'เลยนัด N วัน',
    vip_overdue_days: 'VIP เลยนัด N วัน',
    course_expiring_days: 'คอร์สใกล้หมดอายุ N วัน',
    wallet_low_amount: 'Wallet ต่ำ N บาท',
    dormant_days: 'ไม่มา N วัน',
};

async function loadList() {
    const r = await api.call('/follow-up-rules');
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('rows').innerHTML = rows.map(x => {
        const cv = x.condition_value || {};
        const v = cv.days ?? cv.amount ?? '?';
        return `
        <tr class="border-t">
          <td class="px-3 py-2">${x.name}</td>
          <td class="px-3 py-2 text-center"><span class="text-xs px-2 py-0.5 rounded ${PRIO_COLOR[x.priority]||''}">${PRIO_LABEL[x.priority]||x.priority}</span></td>
          <td class="px-3 py-2 text-xs">${COND_LABEL[x.condition_type]||x.condition_type} = ${v}</td>
          <td class="px-3 py-2 text-center text-xs">
            ${x.notify_branch_admin ? '<div>BA</div>' : ''}
            ${x.notify_doctor ? '<div>Dr</div>' : ''}
          </td>
          <td class="px-3 py-2 text-center">${x.is_active ? '<span class="text-emerald-700">●</span>' : '<span class="text-slate-400">○</span>'}</td>
          <td class="px-3 py-2 text-right whitespace-nowrap">
            <button class="edit text-cyan-700 text-sm hover:underline" data-id="${x.id}">แก้ไข</button>
            <button class="del text-rose-600 text-sm hover:underline ml-2" data-id="${x.id}">ลบ</button>
          </td>
        </tr>`;
    }).join('') || '<tr><td colspan="6" class="text-center py-6 text-slate-500">ยังไม่มี rule</td></tr>';

    document.querySelectorAll('.edit').forEach(b => b.addEventListener('click', () => openEdit(rows.find(x => x.id == b.dataset.id))));
    document.querySelectorAll('.del').forEach(b => b.addEventListener('click', async () => {
        if (!confirm('ลบ rule นี้?')) return;
        const r = await api.call(`/follow-up-rules/${b.dataset.id}`, { method: 'DELETE' });
        if (!r.ok) return alert((r.data && r.data.message) || 'ลบไม่ได้');
        loadList();
    }));
}

function openEdit(row) {
    const f = document.getElementById('form');
    document.getElementById('form-title').textContent = row ? 'แก้ไข Rule' : 'Rule ใหม่';
    f.id.value = row?.id || '';
    f.name.value = row?.name || '';
    f.priority.value = row?.priority || 'high';
    f.preferred_channel.value = row?.preferred_channel || 'in_app';
    f.condition_type.value = row?.condition_type || 'overdue_days';
    const cv = row?.condition_value || {};
    f.cv_value.value = cv.days ?? cv.amount ?? 3;
    f.notify_branch_admin.checked = row ? !!row.notify_branch_admin : true;
    f.notify_doctor.checked = row ? !!row.notify_doctor : false;
    f.is_active.checked = row ? !!row.is_active : true;
    document.getElementById('form-dialog').showModal();
}

document.getElementById('btn-new').addEventListener('click', () => openEdit(null));

document.getElementById('form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const valueKey = f.condition_type.value === 'wallet_low_amount' ? 'amount' : 'days';
    const body = {
        name: f.name.value,
        priority: f.priority.value,
        condition_type: f.condition_type.value,
        condition_value: { [valueKey]: +f.cv_value.value },
        notify_doctor: f.notify_doctor.checked,
        notify_branch_admin: f.notify_branch_admin.checked,
        preferred_channel: f.preferred_channel.value,
        is_active: f.is_active.checked,
    };
    let r;
    if (f.id.value) {
        r = await api.call(`/follow-up-rules/${f.id.value}`, { method: 'PUT', body: JSON.stringify(body) });
    } else {
        r = await api.call('/follow-up-rules', { method: 'POST', body: JSON.stringify(body) });
    }
    if (!r.ok) return alert((r.data && r.data.message) || 'บันทึกไม่ได้');
    document.getElementById('form-dialog').close();
    loadList();
});

document.querySelectorAll('.dlg-cancel').forEach(b => b.addEventListener('click', e => e.target.closest('dialog').close()));

(async function () {
    if (!api.token()) return window.location.href = '/login';
    await loadList();
})();
</script>
@endsection
