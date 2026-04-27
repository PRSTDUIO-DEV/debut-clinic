@extends('layouts.app')
@section('title', 'Birthday Campaigns')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">🎂 Birthday Campaigns</h1>
            <a href="/admin/follow-up-rules" class="ml-auto text-sm text-cyan-700 hover:underline">Follow-up Rules</a>
            <button id="btn-new" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">+ Campaign ใหม่</button>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-6">
        <section class="bg-white rounded-xl shadow p-4">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="px-3 py-2 text-left">ชื่อ</th>
                        <th class="px-3 py-2 text-center">เปิดใช้งาน</th>
                        <th class="px-3 py-2 text-right">รวมส่ง</th>
                        <th class="px-3 py-2 text-left">รันล่าสุด</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="rows"></tbody>
            </table>
        </section>
        <div class="mt-3 text-xs text-slate-500">
            Templates รองรับ offset: <code>30</code> (30 วันก่อน), <code>7</code> (7 วันก่อน), <code>0</code> (วันเกิด), <code>+3</code> (3 วันหลังวันเกิด).
            Placeholders: <code>@{{first_name}}</code>, <code>@{{nickname}}</code>, <code>@{{hn}}</code>
        </div>
    </main>
</div>

<dialog id="form-dialog" class="rounded-xl p-0 w-[640px] max-w-full">
    <form id="form" class="p-4 space-y-3">
        <h3 id="form-title" class="font-semibold text-lg">Campaign ใหม่</h3>
        <input type="hidden" name="id">
        <label class="text-sm block">ชื่อ
            <input name="name" required maxlength="150" class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <label class="text-sm block">คำอธิบาย
            <input name="description" maxlength="255" class="w-full border rounded px-2 py-1 mt-1">
        </label>

        <div class="grid grid-cols-1 gap-3" id="tpl-rows"></div>

        <label class="text-sm flex items-center gap-2">
            <input type="checkbox" name="is_active" checked> เปิดใช้งาน
        </label>
        <div class="flex justify-end gap-2">
            <button type="button" class="dlg-cancel px-3 py-1 rounded bg-slate-100">ยกเลิก</button>
            <button type="submit" class="px-3 py-1 rounded bg-cyan-600 text-white">บันทึก</button>
        </div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
const OFFSETS = [
    { key: '30', label: '30 วันก่อนวันเกิด', defaultChannel: 'in_app' },
    { key: '7',  label: '7 วันก่อนวันเกิด',  defaultChannel: 'line' },
    { key: '0',  label: 'วันเกิด',            defaultChannel: 'line' },
    { key: '+3', label: '3 วันหลังวันเกิด',   defaultChannel: 'in_app' },
];

function tplRow(offset, tpl) {
    return `
      <fieldset class="border rounded p-2" data-offset="${offset.key}">
        <legend class="text-xs font-semibold px-1">${offset.label}</legend>
        <div class="grid grid-cols-3 gap-2 text-sm">
          <label>ช่องทาง
            <select name="channel" class="w-full border rounded px-1 py-0.5 mt-1">
              <option value="">— ปิด —</option>
              <option value="in_app">In-app</option>
              <option value="line">LINE</option>
              <option value="sms">SMS</option>
              <option value="email">Email</option>
            </select>
          </label>
          <label class="col-span-2">หัวข้อ
            <input name="title" class="w-full border rounded px-1 py-0.5 mt-1">
          </label>
          <label class="col-span-3">เนื้อหา
            <textarea name="body" rows="2" class="w-full border rounded px-1 py-0.5 mt-1 font-mono text-xs"></textarea>
          </label>
        </div>
      </fieldset>`;
}

async function loadList() {
    const r = await api.call('/birthday-campaigns');
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('rows').innerHTML = rows.map(c => `
      <tr class="border-t">
        <td class="px-3 py-2 font-medium">${c.name}<div class="text-xs text-slate-500">${c.description||''}</div></td>
        <td class="px-3 py-2 text-center">${c.is_active ? '<span class="text-emerald-700">●</span>' : '<span class="text-slate-400">○</span>'}</td>
        <td class="px-3 py-2 text-right">${c.total_sent}</td>
        <td class="px-3 py-2 text-xs">${(c.last_run_at || '-').replace('T',' ').slice(0,16)}</td>
        <td class="px-3 py-2 text-right whitespace-nowrap">
          <button class="run text-emerald-700 text-sm hover:underline" data-id="${c.id}">▶︎ ส่งทันที</button>
          <button class="edit text-cyan-700 text-sm hover:underline ml-2" data-id="${c.id}">แก้ไข</button>
          <button class="del text-rose-600 text-sm hover:underline ml-2" data-id="${c.id}">ลบ</button>
        </td>
      </tr>`).join('') || '<tr><td colspan="5" class="text-center py-6 text-slate-500">ยังไม่มี campaign</td></tr>';

    document.querySelectorAll('.edit').forEach(b => b.addEventListener('click', () => openEdit(rows.find(x => x.id == b.dataset.id))));
    document.querySelectorAll('.del').forEach(b => b.addEventListener('click', async () => {
        if (!confirm('ลบ campaign นี้?')) return;
        const r = await api.call(`/birthday-campaigns/${b.dataset.id}`, { method: 'DELETE' });
        if (!r.ok) return alert((r.data && r.data.message) || 'ลบไม่ได้');
        loadList();
    }));
    document.querySelectorAll('.run').forEach(b => b.addEventListener('click', async () => {
        if (!confirm('ส่ง campaign นี้ทันที (ignore last_run_at)?')) return;
        const r = await api.call(`/birthday-campaigns/${b.dataset.id}/send-now`, { method: 'POST', body: JSON.stringify({}) });
        if (!r.ok) return alert((r.data && r.data.message) || 'ส่งไม่ได้');
        alert(`เขียน notification ${r.data.data.written} รายการ`);
        loadList();
    }));
}

function openEdit(row) {
    const f = document.getElementById('form');
    document.getElementById('form-title').textContent = row ? 'แก้ไข Campaign' : 'Campaign ใหม่';
    f.id.value = row?.id || '';
    f.name.value = row?.name || '';
    f.description.value = row?.description || '';
    f.is_active.checked = row ? !!row.is_active : true;

    const wrap = document.getElementById('tpl-rows');
    wrap.innerHTML = OFFSETS.map(o => tplRow(o)).join('');
    OFFSETS.forEach(o => {
        const tpl = (row?.templates || {})[o.key];
        const fs = wrap.querySelector(`fieldset[data-offset="${o.key}"]`);
        fs.querySelector('[name=channel]').value = tpl?.channel || '';
        fs.querySelector('[name=title]').value = tpl?.title || '';
        fs.querySelector('[name=body]').value = tpl?.body || '';
    });

    document.getElementById('form-dialog').showModal();
}

document.getElementById('btn-new').addEventListener('click', () => openEdit(null));

document.getElementById('form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const templates = {};
    document.querySelectorAll('#tpl-rows fieldset').forEach(fs => {
        const ch = fs.querySelector('[name=channel]').value;
        if (ch) {
            templates[fs.dataset.offset] = {
                channel: ch,
                title: fs.querySelector('[name=title]').value,
                body: fs.querySelector('[name=body]').value,
            };
        }
    });
    const body = {
        name: f.name.value,
        description: f.description.value || null,
        templates,
        is_active: f.is_active.checked,
    };
    let r;
    if (f.id.value) {
        r = await api.call(`/birthday-campaigns/${f.id.value}`, { method: 'PUT', body: JSON.stringify(body) });
    } else {
        r = await api.call('/birthday-campaigns', { method: 'POST', body: JSON.stringify(body) });
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
