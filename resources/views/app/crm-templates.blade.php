@extends('layouts.app')
@section('title', 'CRM Templates')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">📨 CRM Templates</h1>
            <a href="/crm/segments" class="ml-auto text-sm text-cyan-700 hover:underline">Segments</a>
            <a href="/crm/campaigns" class="text-sm text-cyan-700 hover:underline">Campaigns</a>
            <button id="btn-new" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">+ Template ใหม่</button>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6">
        <section class="bg-white rounded-xl shadow">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="px-3 py-2 text-left">Code</th>
                        <th class="px-3 py-2 text-left">Name</th>
                        <th class="px-3 py-2 text-left">Channel</th>
                        <th class="px-3 py-2 text-left">Subject</th>
                        <th class="px-3 py-2 text-center">Active</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody id="rows"></tbody>
            </table>
        </section>
        <div class="mt-3 text-xs text-slate-500">Body รองรับ placeholder: <code>@{{first_name}}</code>, <code>@{{nickname}}</code>, <code>@{{hn}}</code>, <code>@{{phone}}</code>, <code>@{{total_spent}}</code>, <code>@{{line_id}}</code>, <code>@{{email}}</code></div>
    </main>
</div>

<dialog id="form-dialog" class="rounded-xl p-0 w-[640px] max-w-full">
    <form id="form" class="p-4 space-y-3">
        <h3 id="form-title" class="font-semibold text-lg">Template ใหม่</h3>
        <input type="hidden" name="id">
        <div class="grid grid-cols-2 gap-2">
            <label class="text-sm">Code
                <input name="code" required maxlength="30" class="w-full border rounded px-2 py-1 mt-1 font-mono">
            </label>
            <label class="text-sm">Channel
                <select name="channel" required class="w-full border rounded px-2 py-1 mt-1">
                    <option value="line">LINE</option>
                    <option value="sms">SMS</option>
                    <option value="email">Email</option>
                </select>
            </label>
            <label class="text-sm col-span-2">Name
                <input name="name" required maxlength="150" class="w-full border rounded px-2 py-1 mt-1">
            </label>
            <label class="text-sm col-span-2">Subject (เฉพาะ Email)
                <input name="subject" maxlength="200" class="w-full border rounded px-2 py-1 mt-1">
            </label>
            <label class="text-sm col-span-2">Body
                <textarea name="body" rows="6" required class="w-full border rounded px-2 py-1 mt-1 font-mono text-sm"></textarea>
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
const CHANNEL_LABEL = { line: 'LINE', sms: 'SMS', email: 'Email' };
const CHANNEL_COLOR = {
    line: 'bg-emerald-100 text-emerald-800',
    sms: 'bg-amber-100 text-amber-800',
    email: 'bg-blue-100 text-blue-800',
};

async function loadList() {
    const r = await api.call('/crm/templates');
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('rows').innerHTML = rows.map(t => `
      <tr class="border-t">
        <td class="px-3 py-2 font-mono text-xs">${t.code}</td>
        <td class="px-3 py-2">${t.name}</td>
        <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs ${CHANNEL_COLOR[t.channel]||''}">${CHANNEL_LABEL[t.channel]||t.channel}</span></td>
        <td class="px-3 py-2 text-slate-600 text-xs">${t.subject||'-'}</td>
        <td class="px-3 py-2 text-center">${t.is_active ? '<span class="text-emerald-700">●</span>' : '<span class="text-slate-400">○</span>'}</td>
        <td class="px-3 py-2 text-right whitespace-nowrap">
          <button class="edit text-cyan-700 hover:underline text-sm" data-id="${t.id}">แก้ไข</button>
          <button class="del text-rose-600 hover:underline text-sm ml-2" data-id="${t.id}">ลบ</button>
        </td>
      </tr>`).join('') || '<tr><td colspan="6" class="text-center py-6 text-slate-500">ยังไม่มี template</td></tr>';

    document.querySelectorAll('.edit').forEach(b => b.addEventListener('click', () => openEdit(rows.find(x => x.id == b.dataset.id))));
    document.querySelectorAll('.del').forEach(b => b.addEventListener('click', async () => {
        if (!confirm('ลบ template นี้?')) return;
        const r = await api.call(`/crm/templates/${b.dataset.id}`, { method: 'DELETE' });
        if (!r.ok) return alert((r.data && r.data.message) || 'ลบไม่ได้');
        loadList();
    }));
}

function openEdit(row) {
    const f = document.getElementById('form');
    document.getElementById('form-title').textContent = row ? 'แก้ไข Template' : 'Template ใหม่';
    f.id.value = row?.id || '';
    f.code.value = row?.code || '';
    f.code.disabled = !!row;
    f.channel.value = row?.channel || 'line';
    f.channel.disabled = !!row;
    f.name.value = row?.name || '';
    f.subject.value = row?.subject || '';
    f.body.value = row?.body || '';
    f.is_active.checked = row ? !!row.is_active : true;
    document.getElementById('form-dialog').showModal();
}

document.getElementById('btn-new').addEventListener('click', () => openEdit(null));

document.getElementById('form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const body = {
        name: f.name.value,
        subject: f.subject.value || null,
        body: f.body.value,
        is_active: f.is_active.checked,
    };
    let r;
    if (f.id.value) {
        r = await api.call(`/crm/templates/${f.id.value}`, { method: 'PUT', body: JSON.stringify(body) });
    } else {
        body.code = f.code.value;
        body.channel = f.channel.value;
        r = await api.call('/crm/templates', { method: 'POST', body: JSON.stringify(body) });
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
