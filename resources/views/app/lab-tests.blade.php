@extends('layouts.app')
@section('title', 'Lab Tests Catalog')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">🧪 Lab Tests Catalog</h1>
            <button id="btn-new" class="ml-auto bg-cyan-600 text-white px-3 py-1 rounded text-sm">+ Test ใหม่</button>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6">
        <section class="bg-white rounded-xl shadow">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="px-3 py-2 text-left">Code</th>
                        <th class="px-3 py-2 text-left">Name</th>
                        <th class="px-3 py-2 text-left">Category</th>
                        <th class="px-3 py-2 text-left">Unit</th>
                        <th class="px-3 py-2 text-left">Reference</th>
                        <th class="px-3 py-2 text-right">Price</th>
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
        <h3 id="form-title" class="font-semibold text-lg">Test ใหม่</h3>
        <input type="hidden" name="id">
        <div class="grid grid-cols-2 gap-2">
            <label class="text-sm">Code
                <input name="code" required maxlength="30" class="w-full border rounded px-2 py-1 mt-1 font-mono">
            </label>
            <label class="text-sm">Category
                <input name="category" maxlength="60" class="w-full border rounded px-2 py-1 mt-1">
            </label>
            <label class="text-sm col-span-2">Name
                <input name="name" required maxlength="200" class="w-full border rounded px-2 py-1 mt-1">
            </label>
            <label class="text-sm">Unit
                <input name="unit" maxlength="30" class="w-full border rounded px-2 py-1 mt-1">
            </label>
            <label class="text-sm">Price
                <input name="price" type="number" step="0.01" min="0" class="w-full border rounded px-2 py-1 mt-1">
            </label>
            <label class="text-sm">Ref Min
                <input name="ref_min" type="number" step="0.0001" class="w-full border rounded px-2 py-1 mt-1">
            </label>
            <label class="text-sm">Ref Max
                <input name="ref_max" type="number" step="0.0001" class="w-full border rounded px-2 py-1 mt-1">
            </label>
            <label class="text-sm col-span-2">Reference Text (เช่น "Negative" / range อย่างอื่น)
                <input name="ref_text" maxlength="200" class="w-full border rounded px-2 py-1 mt-1">
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
async function loadList() {
    const r = await api.call('/lab-tests?only_active=0');
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('rows').innerHTML = rows.map(t => {
        const ref = t.ref_min !== null && t.ref_max !== null ? `${t.ref_min} - ${t.ref_max}` : (t.ref_text || '—');
        return `
          <tr class="border-t">
            <td class="px-3 py-2 font-mono text-xs">${t.code}</td>
            <td class="px-3 py-2">${t.name}</td>
            <td class="px-3 py-2 text-slate-600">${t.category||'-'}</td>
            <td class="px-3 py-2">${t.unit||'-'}</td>
            <td class="px-3 py-2 text-xs">${ref}</td>
            <td class="px-3 py-2 text-right">${(+t.price).toFixed(2)}</td>
            <td class="px-3 py-2 text-center">${t.is_active ? '<span class="text-emerald-700">●</span>' : '<span class="text-slate-400">○</span>'}</td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
              <button class="edit text-cyan-700 hover:underline text-sm" data-id="${t.id}">แก้ไข</button>
              <button class="del text-rose-600 hover:underline text-sm ml-2" data-id="${t.id}">ลบ</button>
            </td>
          </tr>`;
    }).join('') || '<tr><td colspan="8" class="text-center py-6 text-slate-500">ยังไม่มี test</td></tr>';

    document.querySelectorAll('.edit').forEach(b => b.addEventListener('click', () => openEdit(rows.find(x => x.id == b.dataset.id))));
    document.querySelectorAll('.del').forEach(b => b.addEventListener('click', async () => {
        if (!confirm('ลบ test นี้?')) return;
        const r = await api.call(`/lab-tests/${b.dataset.id}`, { method: 'DELETE' });
        if (!r.ok) return alert((r.data && r.data.message) || 'ลบไม่ได้');
        loadList();
    }));
}

function openEdit(row) {
    const f = document.getElementById('form');
    document.getElementById('form-title').textContent = row ? 'แก้ไข Test' : 'Test ใหม่';
    f.id.value = row?.id || '';
    f.code.value = row?.code || '';
    f.code.disabled = !!row;
    f.name.value = row?.name || '';
    f.category.value = row?.category || '';
    f.unit.value = row?.unit || '';
    f.price.value = row?.price ?? 0;
    f.ref_min.value = row?.ref_min ?? '';
    f.ref_max.value = row?.ref_max ?? '';
    f.ref_text.value = row?.ref_text || '';
    f.is_active.checked = row ? !!row.is_active : true;
    document.getElementById('form-dialog').showModal();
}

document.getElementById('btn-new').addEventListener('click', () => openEdit(null));

document.getElementById('form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const body = {
        name: f.name.value,
        category: f.category.value || null,
        unit: f.unit.value || null,
        price: +f.price.value || 0,
        ref_min: f.ref_min.value !== '' ? +f.ref_min.value : null,
        ref_max: f.ref_max.value !== '' ? +f.ref_max.value : null,
        ref_text: f.ref_text.value || null,
        is_active: f.is_active.checked,
    };
    let r;
    if (f.id.value) {
        r = await api.call(`/lab-tests/${f.id.value}`, { method: 'PUT', body: JSON.stringify(body) });
    } else {
        body.code = f.code.value;
        r = await api.call('/lab-tests', { method: 'POST', body: JSON.stringify(body) });
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
