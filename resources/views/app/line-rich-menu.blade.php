@extends('layouts.app')
@section('title', 'LINE Rich Menu')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">📱 LINE Rich Menu Builder</h1>
            <button id="btn-create" class="ml-auto bg-cyan-600 text-white px-3 py-1.5 rounded text-sm">+ สร้าง Rich Menu</button>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6">
        <div id="list" class="grid md:grid-cols-2 gap-4"></div>
    </main>
</div>

<dialog id="dlg" class="rounded-xl p-0 w-[640px] max-w-full">
    <form method="dialog" id="form" class="p-4 space-y-2">
        <h3 class="font-bold">+ สร้าง Rich Menu</h3>
        <input name="name" required placeholder="ชื่อเมนู" class="w-full border rounded px-2 py-1.5">
        <select id="layout" name="layout" class="w-full border rounded px-2 py-1.5">
            <option value="compact_4">Compact 4 (1×4)</option>
            <option value="compact_6" selected>Compact 6 (2×3)</option>
            <option value="full_4">Full 4 (2×2)</option>
            <option value="full_6">Full 6 (2×3)</option>
            <option value="full_12">Full 12 (3×4)</option>
        </select>
        <div id="buttons-grid" class="grid gap-2 mt-2"></div>
        <div class="flex gap-2 pt-2"><button type="submit" class="bg-cyan-600 text-white px-3 py-1.5 rounded">บันทึก</button>
            <button type="button" onclick="document.getElementById('dlg').close()" class="px-3 py-1.5">ยกเลิก</button></div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
const LAYOUTS = { compact_4: 4, compact_6: 6, full_4: 4, full_6: 6, full_12: 12 };

function renderButtonInputs() {
    const layout = document.getElementById('layout').value;
    const n = LAYOUTS[layout];
    const grid = document.getElementById('buttons-grid');
    grid.className = 'grid gap-2 mt-2 grid-cols-' + (n === 4 ? 2 : (n === 6 ? 3 : 4));
    grid.innerHTML = Array.from({length: n}, (_, i) => `
        <div class="border rounded p-2 space-y-1 bg-slate-50">
            <div class="text-xs text-slate-500">ปุ่ม #${i+1}</div>
            <input data-i="${i}" data-k="label" placeholder="Label" class="w-full border rounded px-1.5 py-1 text-sm">
            <select data-i="${i}" data-k="action" class="w-full border rounded px-1.5 py-1 text-sm">
                <option value="url">URL</option><option value="message">Message</option><option value="postback">Postback</option>
            </select>
            <input data-i="${i}" data-k="value" placeholder="value" class="w-full border rounded px-1.5 py-1 text-sm">
        </div>`).join('');
}

document.getElementById('layout').onchange = renderButtonInputs;

async function loadList() {
    const r = await api.call('/line/rich-menus');
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('list').innerHTML = rows.map(m => `
        <div class="bg-white rounded-xl shadow p-4 ${m.is_active ? 'border-2 border-emerald-500' : ''}">
            <div class="flex justify-between items-center"><b>${m.name}</b>
                ${m.is_active ? '<span class="text-emerald-700 text-xs">● Active</span>' : ''}
            </div>
            <div class="text-xs text-slate-500 mt-1">Layout: ${m.layout} • Buttons: ${(m.buttons||[]).length}</div>
            <div class="grid gap-1 mt-2 grid-cols-${m.layout === 'compact_4' ? 4 : (m.layout.includes('6') ? 3 : (m.layout === 'full_12' ? 4 : 2))}">
                ${(m.buttons || []).map(b => `<div class="bg-cyan-50 text-cyan-700 text-xs px-2 py-1 rounded text-center truncate">${b.label}</div>`).join('')}
            </div>
            <div class="flex gap-2 mt-3">
                <button data-id="${m.id}" data-action="sync" class="text-cyan-700 text-xs hover:underline">🔄 Sync to LINE</button>
                <button data-id="${m.id}" data-action="delete" class="text-rose-700 text-xs hover:underline ml-auto">🗑 ลบ</button>
            </div>
            ${m.line_rich_menu_id ? `<div class="text-xs text-slate-400 mt-1 font-mono">${m.line_rich_menu_id}</div>` : ''}
        </div>`).join('') || '<div class="text-slate-400 text-sm col-span-2">ยังไม่มี Rich Menu</div>';

    document.querySelectorAll('[data-action]').forEach(b => b.onclick = async () => {
        if (b.dataset.action === 'sync') {
            const r = await api.call(`/line/rich-menus/${b.dataset.id}/sync`, { method: 'POST' });
            if (r.ok) { alert('Synced'); loadList(); }
        } else if (b.dataset.action === 'delete') {
            if (!confirm('ลบเมนูนี้?')) return;
            const r = await api.call(`/line/rich-menus/${b.dataset.id}`, { method: 'DELETE' });
            if (r.ok) loadList();
        }
    });
}

document.getElementById('btn-create').onclick = () => { renderButtonInputs(); document.getElementById('dlg').showModal(); };

document.getElementById('form').onsubmit = async (e) => {
    e.preventDefault();
    const layout = document.getElementById('layout').value;
    const n = LAYOUTS[layout];
    const buttons = [];
    for (let i = 0; i < n; i++) {
        buttons.push({
            label: document.querySelector(`[data-i="${i}"][data-k="label"]`).value,
            action: document.querySelector(`[data-i="${i}"][data-k="action"]`).value,
            value: document.querySelector(`[data-i="${i}"][data-k="value"]`).value,
        });
    }
    const data = { name: e.target.name.value, layout, buttons };
    const r = await api.call('/line/rich-menus', { method: 'POST', body: data });
    if (r.ok) { document.getElementById('dlg').close(); e.target.reset(); loadList(); }
    else alert(JSON.stringify(r.data?.errors || r.data));
};

(async function () { if (!api.token()) return location.href = '/login'; await loadList(); })();
</script>
@endsection
