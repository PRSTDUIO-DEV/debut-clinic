@extends('layouts.app')
@section('title', $config['title'] ?? 'Settings')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
            <a href="/admin/settings" class="text-cyan-700 hover:underline">← Settings</a>
            <h1 class="font-bold" id="page-title">…</h1>
            <input id="search" placeholder="ค้นหา..." class="border rounded px-2 py-1 text-sm">
            <button id="btn-create" class="ml-auto bg-cyan-600 text-white px-3 py-1.5 rounded text-sm">+ เพิ่ม</button>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <div class="bg-white rounded-xl shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100"><tr id="thead-row"></tr></thead>
                <tbody id="rows"></tbody>
            </table>
        </div>
    </main>
</div>

<dialog id="dlg" class="rounded-xl p-0 w-[520px] max-w-full">
    <form method="dialog" id="form" class="p-4 space-y-2">
        <h3 class="font-bold" id="dlg-title">+ เพิ่ม</h3>
        <div id="form-fields"></div>
        <div class="flex gap-2 pt-2">
            <button type="submit" class="bg-cyan-600 text-white px-3 py-1.5 rounded">บันทึก</button>
            <button type="button" onclick="document.getElementById('dlg').close()" class="px-3 py-1.5">ยกเลิก</button>
        </div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
window.CRUD_CONFIG = @json($config);
</script>
<script>
/**
 * Generic settings CRUD page driven by inline config (set via window.CRUD_CONFIG before this script runs).
 *
 * Config shape:
 *   {
 *     title: 'ห้องตรวจ',
 *     endpoint: '/admin/rooms',     // base; CRUD = GET /, POST /, PUT /{id}, DELETE /{id}
 *     fields: [
 *       { key: 'name', label: 'ชื่อ', type: 'text', required: true },
 *       { key: 'is_active', label: 'Active', type: 'boolean', defaultValue: true },
 *       ...
 *     ],
 *     columns: [['name', 'ชื่อ'], ['is_active', 'Active', 'boolean']],
 *   }
 */
const C = window.CRUD_CONFIG;
let editing = null;
let allRows = [];

function fmt(v, type) {
    if (v === null || v === undefined) return '—';
    if (type === 'boolean') return v ? '✅' : '⛔';
    if (type === 'currency') return (+v).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
    if (type === 'pct') return (+v).toFixed(2) + '%';
    if (type === 'color') return `<span class="inline-block w-5 h-5 rounded" style="background:${v}"></span>`;
    return v;
}

function renderTable() {
    const search = document.getElementById('search').value.toLowerCase();
    const rows = !search ? allRows : allRows.filter(r =>
        Object.values(r).some(v => String(v ?? '').toLowerCase().includes(search))
    );

    document.getElementById('thead-row').innerHTML = C.columns.map(([_, l]) => `<th class="px-3 py-2 text-left">${l}</th>`).join('') +
        '<th class="text-right pr-3">การกระทำ</th>';

    document.getElementById('rows').innerHTML = rows.map(r => `
        <tr class="border-t hover:bg-slate-50">
            ${C.columns.map(([key, _, type]) => `<td class="px-3 py-1.5">${fmt(getNested(r, key), type)}</td>`).join('')}
            <td class="text-right pr-3">
                <button data-action="edit" data-id="${r.id}" class="text-cyan-700 text-xs hover:underline">แก้ไข</button>
                <button data-action="delete" data-id="${r.id}" class="text-rose-700 text-xs hover:underline ml-2">ลบ</button>
            </td>
        </tr>`).join('') || `<tr><td colspan="${C.columns.length + 1}" class="text-center py-6 text-slate-400">ไม่มีข้อมูล</td></tr>`;

    document.querySelectorAll('[data-action="edit"]').forEach(b => b.onclick = () => openDlg(allRows.find(x => x.id == b.dataset.id)));
    document.querySelectorAll('[data-action="delete"]').forEach(b => b.onclick = () => doDelete(b.dataset.id));
}

function getNested(o, k) { return k.split('.').reduce((x, p) => x?.[p], o); }

async function load() {
    const r = await api.call(C.endpoint);
    if (!r.ok) return;
    allRows = r.data.data?.data || r.data.data || [];
    renderTable();
}

function buildForm(row = null) {
    const html = C.fields.map(f => {
        const val = row ? (row[f.key] ?? f.defaultValue ?? '') : (f.defaultValue ?? '');
        if (f.type === 'boolean') {
            return `<label class="flex items-center gap-2"><input name="${f.key}" type="checkbox" ${val ? 'checked' : ''}> ${f.label}</label>`;
        }
        if (f.type === 'select') {
            return `<label class="block text-sm">${f.label}${f.required ? ' *' : ''}
                <select name="${f.key}" class="w-full border rounded px-2 py-1.5 mt-1" ${f.required ? 'required' : ''}>
                    ${(f.options || []).map(o => `<option value="${o.value}" ${val == o.value ? 'selected' : ''}>${o.label}</option>`).join('')}
                </select></label>`;
        }
        if (f.type === 'textarea') {
            return `<label class="block text-sm">${f.label}<textarea name="${f.key}" class="w-full border rounded px-2 py-1.5 mt-1" rows="3">${val}</textarea></label>`;
        }
        const inputType = f.type === 'number' || f.type === 'pct' || f.type === 'currency' ? 'number' : (f.type === 'color' ? 'color' : (f.type === 'email' ? 'email' : 'text'));
        const step = (f.type === 'pct' || f.type === 'currency' || f.type === 'number') ? 'step="0.01"' : '';

        return `<label class="block text-sm">${f.label}${f.required ? ' *' : ''}
            <input name="${f.key}" type="${inputType}" ${step} value="${val}" class="w-full border rounded px-2 py-1.5 mt-1" ${f.required ? 'required' : ''}>
        </label>`;
    }).join('');
    document.getElementById('form-fields').innerHTML = html;
}

function openDlg(row = null) {
    editing = row;
    document.getElementById('dlg-title').textContent = row ? 'แก้ไข' : '+ เพิ่ม';
    buildForm(row);
    document.getElementById('dlg').showModal();
}

async function doDelete(id) {
    if (!confirm('ยืนยันการลบ?')) return;
    const r = await api.call(`${C.endpoint}/${id}`, { method: 'DELETE' });
    if (r.ok) load();
}

document.getElementById('btn-create').onclick = () => openDlg(null);
document.getElementById('search').oninput = () => { clearTimeout(window.__t); window.__t = setTimeout(renderTable, 200); };

document.getElementById('form').onsubmit = async (e) => {
    e.preventDefault();
    const f = new FormData(e.target);
    const data = {};
    C.fields.forEach(field => {
        if (field.type === 'boolean') {
            data[field.key] = f.get(field.key) === 'on';
        } else {
            const v = f.get(field.key);
            if (v === null || v === '') {
                if (!editing) return;
                data[field.key] = null;

                return;
            }
            data[field.key] = (field.type === 'number' || field.type === 'pct' || field.type === 'currency') ? +v : v;
        }
    });
    const url = editing ? `${C.endpoint}/${editing.id}` : C.endpoint;
    const method = editing ? 'PUT' : 'POST';
    const r = await api.call(url, { method, body: data });
    if (r.ok) { document.getElementById('dlg').close(); load(); }
    else alert(JSON.stringify(r.data?.errors || r.data));
};

(async function () {
    if (!api.token()) return location.href = '/login';
    document.getElementById('page-title').textContent = C.title;
    document.title = C.title + ' - Debut Clinic';
    await load();
})();
</script>
@endsection
