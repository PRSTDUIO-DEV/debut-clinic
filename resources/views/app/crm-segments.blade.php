@extends('layouts.app')
@section('title', 'CRM Segments')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">🎯 CRM Segments</h1>
            <a href="/crm/templates" class="ml-auto text-sm text-cyan-700 hover:underline">Templates</a>
            <a href="/crm/campaigns" class="text-sm text-cyan-700 hover:underline">Campaigns</a>
            <button id="btn-new" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">+ Segment ใหม่</button>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6 grid md:grid-cols-3 gap-4">
        <section class="md:col-span-1 bg-white rounded-xl shadow p-4">
            <ul id="list" class="space-y-2 max-h-[600px] overflow-y-auto"></ul>
        </section>

        <section class="md:col-span-2 bg-white rounded-xl shadow p-4">
            <div id="detail" class="text-slate-500">เลือก segment จากด้านซ้าย</div>
        </section>
    </main>
</div>

<dialog id="form-dialog" class="rounded-xl p-0 w-[640px] max-w-full">
    <form id="form" class="p-4 space-y-3">
        <h3 id="form-title" class="font-semibold text-lg">Segment ใหม่</h3>
        <input type="hidden" name="id">
        <div class="grid grid-cols-2 gap-2">
            <label class="text-sm col-span-2">ชื่อ
                <input name="name" required maxlength="150" class="w-full border rounded px-2 py-1 mt-1">
            </label>
            <label class="text-sm col-span-2">คำอธิบาย
                <input name="description" maxlength="255" class="w-full border rounded px-2 py-1 mt-1">
            </label>

            <fieldset class="text-sm col-span-2 border rounded p-2">
                <legend class="font-medium px-1 text-xs">เงื่อนไข</legend>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-xs">customer_group_ids (comma)
                        <input name="customer_group_ids" placeholder="1,2" class="w-full border rounded px-2 py-1 mt-1 font-mono">
                    </label>
                    <label class="text-xs">gender
                        <select name="gender" class="w-full border rounded px-2 py-1 mt-1">
                            <option value="">—</option><option value="male">ชาย</option><option value="female">หญิง</option><option value="other">อื่น</option>
                        </select>
                    </label>
                    <label class="text-xs">last_visit_days_max (มาภายใน N วัน)
                        <input name="last_visit_days_max" type="number" min="0" class="w-full border rounded px-2 py-1 mt-1">
                    </label>
                    <label class="text-xs">last_visit_days_min (ไม่มา ≥ N วัน)
                        <input name="last_visit_days_min" type="number" min="0" class="w-full border rounded px-2 py-1 mt-1">
                    </label>
                    <label class="text-xs">total_spent_min
                        <input name="total_spent_min" type="number" step="0.01" class="w-full border rounded px-2 py-1 mt-1">
                    </label>
                    <label class="text-xs">total_spent_max
                        <input name="total_spent_max" type="number" step="0.01" class="w-full border rounded px-2 py-1 mt-1">
                    </label>
                    <label class="text-xs">age_min
                        <input name="age_min" type="number" min="0" class="w-full border rounded px-2 py-1 mt-1">
                    </label>
                    <label class="text-xs">age_max
                        <input name="age_max" type="number" min="0" class="w-full border rounded px-2 py-1 mt-1">
                    </label>
                    <label class="text-xs flex items-center gap-2 mt-3">
                        <input type="checkbox" name="has_member_account"> มี member account
                    </label>
                    <label class="text-xs flex items-center gap-2 mt-3">
                        <input type="checkbox" name="has_active_course"> มี course active
                    </label>
                </div>
            </fieldset>

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
let segments = [];

function rulesFromForm(f) {
    const r = {};
    if (f.customer_group_ids.value) r.customer_group_ids = f.customer_group_ids.value.split(',').map(s => +s.trim()).filter(Boolean);
    if (f.gender.value) r.gender = f.gender.value;
    ['last_visit_days_max','last_visit_days_min','age_min','age_max'].forEach(k => {
        if (f[k].value !== '') r[k] = +f[k].value;
    });
    ['total_spent_min','total_spent_max'].forEach(k => {
        if (f[k].value !== '') r[k] = +f[k].value;
    });
    if (f.has_member_account.checked) r.has_member_account = true;
    if (f.has_active_course.checked) r.has_active_course = true;
    return r;
}

function rulesToForm(f, rules) {
    f.customer_group_ids.value = (rules.customer_group_ids || []).join(',');
    f.gender.value = rules.gender || '';
    ['last_visit_days_max','last_visit_days_min','age_min','age_max','total_spent_min','total_spent_max'].forEach(k => {
        f[k].value = rules[k] ?? '';
    });
    f.has_member_account.checked = !!rules.has_member_account;
    f.has_active_course.checked = !!rules.has_active_course;
}

async function loadList() {
    const r = await api.call('/crm/segments');
    if (!r.ok) return;
    segments = r.data.data || [];
    document.getElementById('list').innerHTML = segments.map(s => `
      <li class="border rounded p-2 cursor-pointer hover:bg-cyan-50" data-id="${s.id}">
        <div class="flex justify-between items-start">
          <div>
            <div class="font-semibold">${s.name}</div>
            <div class="text-xs text-slate-500">${s.description || ''}</div>
          </div>
          <div class="text-right">
            <div class="text-lg font-bold text-cyan-700">${s.last_resolved_count}</div>
            <div class="text-xs text-slate-400">คน</div>
          </div>
        </div>
      </li>`).join('') || '<em class="text-slate-400 text-sm">ยังไม่มี segment</em>';
    document.querySelectorAll('#list li').forEach(li => li.addEventListener('click', () => showDetail(+li.dataset.id)));
}

async function showDetail(id) {
    const s = segments.find(x => x.id == id);
    if (!s) return;
    const pr = await api.call(`/crm/segments/${id}/preview?limit=20`);
    const samples = pr.ok ? pr.data.data.samples : [];
    const count = pr.ok ? pr.data.data.count : 0;
    document.getElementById('detail').innerHTML = `
      <div class="flex items-start justify-between mb-3">
        <div>
          <h2 class="text-xl font-bold">${s.name}</h2>
          <div class="text-sm text-slate-500">${s.description||''}</div>
        </div>
        <div class="flex gap-2">
          <button id="btn-edit" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">แก้ไข</button>
          <button id="btn-del" class="bg-rose-600 text-white px-3 py-1 rounded text-sm">ลบ</button>
        </div>
      </div>
      <pre class="bg-slate-50 p-2 rounded text-xs mb-3">${JSON.stringify(s.rules, null, 2)}</pre>
      <div class="text-sm font-medium mb-2">ตัวอย่างผู้ป่วย (${count} คนทั้งหมด)</div>
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100"><tr>
          <th class="px-2 py-1 text-left">HN</th>
          <th class="px-2 py-1 text-left">ชื่อ</th>
          <th class="px-2 py-1 text-left">เบอร์</th>
          <th class="px-2 py-1 text-left">LINE</th>
          <th class="px-2 py-1 text-left">ครั้งล่าสุด</th>
          <th class="px-2 py-1 text-right">ใช้รวม</th>
        </tr></thead>
        <tbody>${samples.map(p => `
          <tr class="border-t">
            <td class="px-2 py-1 font-mono text-xs">${p.hn}</td>
            <td class="px-2 py-1">${p.name}</td>
            <td class="px-2 py-1 text-xs">${p.phone||'-'}</td>
            <td class="px-2 py-1 text-xs">${p.line_id||'-'}</td>
            <td class="px-2 py-1 text-xs">${p.last_visit_at||'-'}</td>
            <td class="px-2 py-1 text-right">${p.total_spent.toLocaleString()}</td>
          </tr>`).join('') || '<tr><td colspan="6" class="text-center py-4 text-slate-500">ไม่มีผู้ป่วยตรงเงื่อนไข</td></tr>'}</tbody>
      </table>`;
    document.getElementById('btn-edit').addEventListener('click', () => openEdit(s));
    document.getElementById('btn-del').addEventListener('click', async () => {
        if (!confirm('ลบ segment นี้?')) return;
        const r = await api.call(`/crm/segments/${id}`, { method: 'DELETE' });
        if (!r.ok) return alert((r.data && r.data.message) || 'ลบไม่ได้');
        document.getElementById('detail').innerHTML = '<em class="text-slate-500">เลือก segment</em>';
        loadList();
    });
}

function openEdit(row) {
    const f = document.getElementById('form');
    document.getElementById('form-title').textContent = row ? 'แก้ไข Segment' : 'Segment ใหม่';
    f.id.value = row?.id || '';
    f.name.value = row?.name || '';
    f.description.value = row?.description || '';
    rulesToForm(f, row?.rules || {});
    f.is_active.checked = row ? !!row.is_active : true;
    document.getElementById('form-dialog').showModal();
}

document.getElementById('btn-new').addEventListener('click', () => openEdit(null));

document.getElementById('form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const body = {
        name: f.name.value,
        description: f.description.value || null,
        rules: rulesFromForm(f),
        is_active: f.is_active.checked,
    };
    let r;
    if (f.id.value) {
        r = await api.call(`/crm/segments/${f.id.value}`, { method: 'PUT', body: JSON.stringify(body) });
    } else {
        r = await api.call('/crm/segments', { method: 'POST', body: JSON.stringify(body) });
    }
    if (!r.ok) return alert((r.data && r.data.message) || 'บันทึกไม่ได้');
    document.getElementById('form-dialog').close();
    await loadList();
    showDetail(r.data.data.id);
});

document.querySelectorAll('.dlg-cancel').forEach(b => b.addEventListener('click', e => e.target.closest('dialog').close()));

(async function () {
    if (!api.token()) return window.location.href = '/login';
    await loadList();
})();
</script>
@endsection
