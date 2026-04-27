@extends('layouts.app')
@section('title', 'Messaging Providers')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">📡 Messaging Providers</h1>
            <a href="/admin/messaging-logs" class="ml-auto text-sm text-cyan-700 hover:underline">📋 Logs</a>
            <button id="btn-new" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">+ Provider ใหม่</button>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6 space-y-4">
        <div class="text-xs text-slate-500 bg-amber-50 border border-amber-200 rounded p-2">
            <b>MESSAGING_LIVE flag</b>: ตั้งค่า <code>MESSAGING_LIVE=true</code> ใน <code>.env</code> เพื่อให้ Notification + Broadcast ส่งจริงผ่าน providers ด้านล่าง<br>
            ถ้า false (default) ระบบจะ log อย่างเดียวเพื่อ dev/test
        </div>

        <section class="bg-white rounded-xl shadow">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="px-3 py-2 text-left">Type</th>
                        <th class="px-3 py-2 text-left">Name</th>
                        <th class="px-3 py-2 text-center">Default</th>
                        <th class="px-3 py-2 text-center">Active</th>
                        <th class="px-3 py-2 text-center">Status</th>
                        <th class="px-3 py-2 text-left">Webhook URL</th>
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
        <h3 id="form-title" class="font-semibold text-lg">Provider ใหม่</h3>
        <input type="hidden" name="id">
        <div class="grid grid-cols-2 gap-2">
            <label class="text-sm">Type
                <select name="type" required class="w-full border rounded px-2 py-1 mt-1">
                    <option value="line">LINE</option>
                    <option value="sms">SMS</option>
                    <option value="email">Email</option>
                </select>
            </label>
            <label class="text-sm">Name
                <input name="name" required maxlength="100" class="w-full border rounded px-2 py-1 mt-1">
            </label>
            <label class="text-sm col-span-2">Config (JSON)
                <textarea name="config" rows="6" class="w-full border rounded px-2 py-1 mt-1 font-mono text-xs"></textarea>
            </label>
            <div class="text-xs text-slate-500 col-span-2">
                LINE: <code>{"channel_id":"...","channel_secret":"...","channel_access_token":"..."}</code><br>
                SMS Twilio: <code>{"mode":"twilio","account_sid":"...","auth_token":"...","from":"+1..."}</code><br>
                SMS ThaiBulk: <code>{"mode":"thai_bulk_sms","api_key":"...","sender":"Debut"}</code><br>
                SMS Sandbox: <code>{"mode":"sandbox"}</code><br>
                Email: <code>{"from_address":"noreply@...","from_name":"Debut Clinic"}</code>
            </div>
            <label class="text-sm flex items-center gap-2">
                <input type="checkbox" name="is_active" checked> Active
            </label>
            <label class="text-sm flex items-center gap-2">
                <input type="checkbox" name="is_default"> Default for channel
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
const TYPE_COLOR = { line: 'bg-emerald-100 text-emerald-800', sms: 'bg-amber-100 text-amber-800', email: 'bg-blue-100 text-blue-800' };
const STATUS_COLOR = { ok: 'bg-emerald-100 text-emerald-800', warning: 'bg-amber-100 text-amber-800', error: 'bg-rose-100 text-rose-800', unknown: 'bg-slate-100 text-slate-700' };

async function loadList() {
    const r = await api.call('/messaging-providers');
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('rows').innerHTML = rows.map(p => `
      <tr class="border-t" data-id="${p.id}">
        <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs ${TYPE_COLOR[p.type]||''}">${p.type.toUpperCase()}</span></td>
        <td class="px-3 py-2 font-medium">${p.name}</td>
        <td class="px-3 py-2 text-center">${p.is_default ? '⭐' : ''}</td>
        <td class="px-3 py-2 text-center">${p.is_active ? '<span class="text-emerald-700">●</span>' : '<span class="text-slate-400">○</span>'}</td>
        <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded text-xs ${STATUS_COLOR[p.status]||''}">${p.status}</span></td>
        <td class="px-3 py-2 text-xs font-mono">${p.webhook_url ? `<code>${p.webhook_url}</code>` : '-'}</td>
        <td class="px-3 py-2 text-right whitespace-nowrap">
          ${p.type === 'line' ? `<button class="test text-cyan-700 text-sm hover:underline" data-id="${p.id}">🩺 Test</button>` : ''}
          <button class="edit text-cyan-700 text-sm hover:underline ml-2" data-id="${p.id}">แก้ไข</button>
          <button class="del text-rose-600 text-sm hover:underline ml-2" data-id="${p.id}">ลบ</button>
        </td>
      </tr>`).join('') || '<tr><td colspan="7" class="text-center py-6 text-slate-500">ยังไม่มี provider</td></tr>';

    document.querySelectorAll('.edit').forEach(b => b.addEventListener('click', () => openEdit(rows.find(x => x.id == b.dataset.id))));
    document.querySelectorAll('.del').forEach(b => b.addEventListener('click', async () => {
        if (!confirm('ลบ provider นี้?')) return;
        const r = await api.call(`/messaging-providers/${b.dataset.id}`, { method: 'DELETE' });
        if (!r.ok) return alert((r.data && r.data.message) || 'ลบไม่ได้');
        loadList();
    }));
    document.querySelectorAll('.test').forEach(b => b.addEventListener('click', async () => {
        const r = await api.call(`/messaging-providers/${b.dataset.id}/test`, { method: 'POST', body: JSON.stringify({}) });
        alert('Test result:\n'+JSON.stringify(r.data?.data?.test_result || r.data, null, 2));
        loadList();
    }));
}

function openEdit(row) {
    const f = document.getElementById('form');
    document.getElementById('form-title').textContent = row ? `แก้ไข Provider (${row.type.toUpperCase()})` : 'Provider ใหม่';
    f.id.value = row?.id || '';
    f.type.value = row?.type || 'line';
    f.type.disabled = !!row;
    f.name.value = row?.name || '';
    // Display masked config but allow re-entry
    f.config.value = row ? JSON.stringify(row.config || {}, null, 2) : '';
    f.is_active.checked = row ? !!row.is_active : true;
    f.is_default.checked = row ? !!row.is_default : false;
    document.getElementById('form-dialog').showModal();
}

document.getElementById('btn-new').addEventListener('click', () => openEdit(null));

document.getElementById('form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    let config = null;
    try { config = f.config.value ? JSON.parse(f.config.value) : null; } catch (err) { return alert('Config JSON invalid'); }
    const body = {
        name: f.name.value,
        config,
        is_active: f.is_active.checked,
        is_default: f.is_default.checked,
    };
    let r;
    if (f.id.value) {
        r = await api.call(`/messaging-providers/${f.id.value}`, { method: 'PUT', body: JSON.stringify(body) });
    } else {
        body.type = f.type.value;
        r = await api.call('/messaging-providers', { method: 'POST', body: JSON.stringify(body) });
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
