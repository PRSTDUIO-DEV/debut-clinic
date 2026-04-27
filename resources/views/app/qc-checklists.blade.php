@extends('layouts.app')
@section('title', 'QC Checklists')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">📋 QC Checklists</h1>
            <a href="/qc/runs" class="text-sm text-cyan-700 hover:underline">→ การตรวจ (Runs)</a>
            <button id="btn-new" class="ml-auto bg-cyan-600 text-white px-3 py-1.5 rounded text-sm">+ Checklist ใหม่</button>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6 space-y-3" id="rows"></main>
</div>

<dialog id="dlg" class="rounded-xl p-0 w-[640px] max-w-full">
    <form method="dialog" id="form" class="p-4 space-y-2">
        <h3 class="font-bold" id="dlg-title">+ Checklist ใหม่</h3>
        <input name="name" required placeholder="ชื่อ Checklist" class="w-full border rounded px-2 py-1.5">
        <input name="description" placeholder="คำอธิบาย" class="w-full border rounded px-2 py-1.5">
        <div class="grid grid-cols-2 gap-2">
            <select name="frequency" class="border rounded px-2 py-1.5">
                <option value="daily">รายวัน</option><option value="weekly">รายสัปดาห์</option>
                <option value="monthly">รายเดือน</option><option value="per_visit">ต่อ Visit</option>
            </select>
            <input name="applicable_role" placeholder="Role (เช่น nurse)" class="border rounded px-2 py-1.5">
        </div>
        <h4 class="font-semibold mt-3">รายการตรวจ</h4>
        <div id="items-list" class="space-y-1"></div>
        <button type="button" id="btn-add-item" class="text-cyan-600 text-sm hover:underline">+ เพิ่มข้อ</button>
        <div class="flex gap-2 pt-2"><button type="submit" class="bg-cyan-600 text-white px-3 py-1.5 rounded">บันทึก</button>
            <button type="button" onclick="document.getElementById('dlg').close()" class="px-3 py-1.5">ยกเลิก</button></div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
let editing = null;

function itemRow(item = {}) {
    const div = document.createElement('div');
    div.className = 'flex gap-1 items-center';
    div.innerHTML = `
        <input class="flex-1 border rounded px-2 py-1 text-sm" data-k="title" placeholder="หัวข้อ" value="${item.title || ''}">
        <label class="text-xs flex items-center gap-1"><input type="checkbox" data-k="requires_photo" ${item.requires_photo ? 'checked' : ''}>ภาพ</label>
        <label class="text-xs flex items-center gap-1"><input type="checkbox" data-k="requires_note" ${item.requires_note ? 'checked' : ''}>หมายเหตุ</label>
        <button type="button" class="text-rose-600 text-xs">×</button>`;
    div.querySelector('button').onclick = () => div.remove();

    return div;
}

async function load() {
    const r = await api.call('/qc/checklists');
    if (!r.ok) return;
    const items = r.data.data?.data || [];
    document.getElementById('rows').innerHTML = items.map(c => `
        <div class="bg-white rounded-xl shadow p-4 flex items-center gap-4">
            <div class="flex-1">
                <div class="flex items-center gap-2">
                    <b>${c.name}</b>
                    <span class="px-2 py-0.5 bg-slate-100 rounded text-xs">${c.frequency}</span>
                    ${c.applicable_role ? `<span class="px-2 py-0.5 bg-cyan-100 text-cyan-700 rounded text-xs">${c.applicable_role}</span>` : ''}
                    ${c.is_active ? '' : '<span class="px-2 py-0.5 bg-rose-100 text-rose-700 rounded text-xs">inactive</span>'}
                </div>
                <div class="text-xs text-slate-500 mt-1">${c.description || ''}</div>
                <div class="text-xs text-slate-400 mt-1">${(c.items || []).length} รายการ</div>
            </div>
            <button data-id="${c.id}" data-action="run" class="bg-emerald-600 text-white px-3 py-1.5 rounded text-sm">▶ เริ่มตรวจ</button>
            <button data-id="${c.id}" data-action="edit" class="text-cyan-700 text-sm hover:underline">แก้</button>
            <button data-id="${c.id}" data-action="del" class="text-rose-700 text-sm hover:underline">ลบ</button>
        </div>`).join('') || '<div class="text-slate-400 text-center py-8">ยังไม่มี checklist</div>';

    document.querySelectorAll('[data-action="run"]').forEach(b => b.onclick = async () => {
        const r = await api.call('/qc/runs', { method: 'POST', body: { checklist_id: +b.dataset.id } });
        if (r.ok) location.href = '/qc/runs/'+r.data.data.id;
    });
    document.querySelectorAll('[data-action="edit"]').forEach(b => b.onclick = () => openEdit(items.find(x => x.id == b.dataset.id)));
    document.querySelectorAll('[data-action="del"]').forEach(b => b.onclick = async () => {
        if (!confirm('ลบ checklist?')) return;
        const r = await api.call('/qc/checklists/'+b.dataset.id, { method: 'DELETE' });
        if (r.ok) load();
    });
}

function openEdit(checklist = null) {
    editing = checklist;
    document.getElementById('dlg-title').textContent = checklist ? 'แก้ไข Checklist' : '+ Checklist ใหม่';
    document.getElementById('form').reset();
    document.getElementById('items-list').innerHTML = '';
    if (checklist) {
        document.querySelector('[name="name"]').value = checklist.name;
        document.querySelector('[name="description"]').value = checklist.description || '';
        document.querySelector('[name="frequency"]').value = checklist.frequency;
        document.querySelector('[name="applicable_role"]').value = checklist.applicable_role || '';
        (checklist.items || []).forEach(item => document.getElementById('items-list').appendChild(itemRow(item)));
    } else {
        document.getElementById('items-list').appendChild(itemRow());
    }
    document.getElementById('dlg').showModal();
}

document.getElementById('btn-new').onclick = () => openEdit();
document.getElementById('btn-add-item').onclick = () => document.getElementById('items-list').appendChild(itemRow());

document.getElementById('form').onsubmit = async (e) => {
    e.preventDefault();
    const items = Array.from(document.querySelectorAll('#items-list > div')).map(div => ({
        title: div.querySelector('[data-k="title"]').value,
        requires_photo: div.querySelector('[data-k="requires_photo"]').checked,
        requires_note: div.querySelector('[data-k="requires_note"]').checked,
    })).filter(i => i.title);
    const f = new FormData(e.target);
    const data = {
        name: f.get('name'),
        description: f.get('description') || null,
        frequency: f.get('frequency'),
        applicable_role: f.get('applicable_role') || null,
        items,
    };
    const url = editing ? `/qc/checklists/${editing.id}` : '/qc/checklists';
    const method = editing ? 'PUT' : 'POST';
    const r = await api.call(url, { method, body: data });
    if (r.ok) { document.getElementById('dlg').close(); load(); }
    else alert(JSON.stringify(r.data?.errors || r.data));
};

(async function () { if (!api.token()) return location.href = '/login'; await load(); })();
</script>
@endsection
