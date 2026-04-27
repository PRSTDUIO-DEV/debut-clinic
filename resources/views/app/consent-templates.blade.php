@extends('layouts.app')
@section('title', 'Consent Templates')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">📜 Consent Templates</h1>
            <button id="btn-new" class="ml-auto bg-cyan-600 text-white px-3 py-1 rounded text-sm">+ Template ใหม่</button>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-6">
        <section class="bg-white rounded-xl shadow">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="px-3 py-2 text-left">Code</th>
                        <th class="px-3 py-2 text-left">Title</th>
                        <th class="px-3 py-2 text-right">Validity (days)</th>
                        <th class="px-3 py-2 text-center">Sign required</th>
                        <th class="px-3 py-2 text-center">Active</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody id="rows"></tbody>
            </table>
        </section>
    </main>
</div>

<dialog id="form-dialog" class="rounded-xl p-0 w-[600px] max-w-full">
    <form id="form" class="p-4 space-y-3">
        <h3 id="form-title" class="font-semibold text-lg">Template ใหม่</h3>
        <input type="hidden" name="id">
        <div class="grid grid-cols-2 gap-2">
            <label class="text-sm">Code
                <input name="code" required maxlength="30" class="w-full border rounded px-2 py-1 mt-1 font-mono">
            </label>
            <label class="text-sm">Validity (วัน)
                <input name="validity_days" type="number" min="0" max="9999" value="365" class="w-full border rounded px-2 py-1 mt-1">
            </label>
            <label class="text-sm col-span-2">Title
                <input name="title" required maxlength="200" class="w-full border rounded px-2 py-1 mt-1">
            </label>
            <label class="text-sm col-span-2">Body (HTML)
                <textarea name="body_html" rows="6" class="w-full border rounded px-2 py-1 mt-1 font-mono text-xs"></textarea>
            </label>
            <label class="text-sm flex items-center gap-2">
                <input type="checkbox" name="require_signature" checked> ต้องเซ็น
            </label>
            <label class="text-sm flex items-center gap-2">
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
async function loadList() {
    const r = await api.call('/consent-templates?only_active=0');
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('rows').innerHTML = rows.map(t => `
      <tr class="border-t">
        <td class="px-3 py-2 font-mono text-xs">${t.code}</td>
        <td class="px-3 py-2">${t.title}</td>
        <td class="px-3 py-2 text-right">${t.validity_days}</td>
        <td class="px-3 py-2 text-center">${t.require_signature ? '✅' : '—'}</td>
        <td class="px-3 py-2 text-center">${t.is_active ? '<span class="text-emerald-700">●</span>' : '<span class="text-slate-400">○</span>'}</td>
        <td class="px-3 py-2 text-right">
          <button class="edit text-cyan-700 hover:underline text-sm" data-id="${t.id}">แก้ไข</button>
          <button class="del text-rose-600 hover:underline text-sm ml-2" data-id="${t.id}">ลบ</button>
        </td>
      </tr>`).join('') || '<tr><td colspan="6" class="text-center py-6 text-slate-500">ยังไม่มี template</td></tr>';

    document.querySelectorAll('.edit').forEach(b => b.addEventListener('click', () => openEdit(rows.find(x => x.id == b.dataset.id))));
    document.querySelectorAll('.del').forEach(b => b.addEventListener('click', async () => {
        if (!confirm('ลบ template นี้?')) return;
        const r = await api.call(`/consent-templates/${b.dataset.id}`, { method: 'DELETE' });
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
    f.title.value = row?.title || '';
    f.body_html.value = row?.body_html || '';
    f.validity_days.value = row?.validity_days ?? 365;
    f.require_signature.checked = row ? !!row.require_signature : true;
    f.is_active.checked = row ? !!row.is_active : true;
    document.getElementById('form-dialog').showModal();
}

document.getElementById('btn-new').addEventListener('click', () => openEdit(null));

document.getElementById('form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const body = {
        title: f.title.value,
        body_html: f.body_html.value || null,
        validity_days: +f.validity_days.value,
        require_signature: f.require_signature.checked,
        is_active: f.is_active.checked,
    };
    let r;
    if (f.id.value) {
        r = await api.call(`/consent-templates/${f.id.value}`, { method: 'PUT', body: JSON.stringify(body) });
    } else {
        body.code = f.code.value;
        r = await api.call('/consent-templates', { method: 'POST', body: JSON.stringify(body) });
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
