@extends('layouts.app')
@section('title', 'Lab Orders')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">🧪 Lab Orders</h1>
            <button id="btn-new" class="ml-auto bg-cyan-600 text-white px-3 py-1 rounded text-sm">+ Order ใหม่</button>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6 grid md:grid-cols-3 gap-4">
        <section class="md:col-span-1 bg-white rounded-xl shadow p-4">
            <div id="status-filter" class="flex gap-1 flex-wrap mb-3">
                <button data-st="" class="text-xs px-2 py-1 rounded bg-cyan-600 text-white">ทั้งหมด</button>
                <button data-st="draft" class="text-xs px-2 py-1 rounded bg-white border">ร่าง</button>
                <button data-st="sent" class="text-xs px-2 py-1 rounded bg-white border">ส่งแลบ</button>
                <button data-st="completed" class="text-xs px-2 py-1 rounded bg-white border">ผลออกแล้ว</button>
                <button data-st="cancelled" class="text-xs px-2 py-1 rounded bg-white border">ยกเลิก</button>
            </div>
            <ul id="list" class="space-y-2 max-h-[600px] overflow-y-auto"></ul>
        </section>

        <section class="md:col-span-2 bg-white rounded-xl shadow p-4">
            <div id="detail" class="text-slate-500">เลือก order จากด้านซ้าย</div>
        </section>
    </main>
</div>

<dialog id="new-dialog" class="rounded-xl p-0 w-[600px] max-w-full">
    <form id="new-form" class="p-4 space-y-3">
        <h3 class="font-semibold text-lg">สร้าง Lab Order</h3>
        <label class="block text-sm">Patient HN/UUID
            <input name="patient_uuid" required class="w-full border rounded px-2 py-1 mt-1 font-mono text-xs" placeholder="UUID ผู้ป่วย">
        </label>
        <div>
            <div class="text-sm font-medium mb-1">เลือกการตรวจ</div>
            <div id="test-pick" class="grid grid-cols-2 gap-1 max-h-60 overflow-y-auto border rounded p-2"></div>
        </div>
        <label class="block text-sm">หมายเหตุ
            <input name="notes" class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <div class="flex justify-end gap-2">
            <button type="button" class="dlg-cancel px-3 py-1 rounded bg-slate-100">ยกเลิก</button>
            <button type="submit" class="px-3 py-1 rounded bg-cyan-600 text-white">สร้าง Order</button>
        </div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
const SL = { draft: 'ร่าง', sent: 'ส่งแลบ', completed: 'ผลออกแล้ว', cancelled: 'ยกเลิก' };
const SC = { draft: 'bg-slate-100 text-slate-700', sent: 'bg-amber-100 text-amber-800', completed: 'bg-emerald-100 text-emerald-800', cancelled: 'bg-red-100 text-red-800' };
const FL = { normal: 'ปกติ', low: 'ต่ำ', high: 'สูง', critical: 'วิกฤต' };
const FC = { normal: 'bg-emerald-100 text-emerald-800', low: 'bg-blue-100 text-blue-800', high: 'bg-amber-100 text-amber-800', critical: 'bg-red-100 text-red-800' };

let currentStatus = '';
let currentOrderId = null;
let tests = [];

async function loadList() {
    const params = new URLSearchParams();
    if (currentStatus) params.set('status', currentStatus);
    const r = await api.call('/lab-orders?'+params.toString());
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('list').innerHTML = rows.map(o => `
      <li class="border rounded p-2 cursor-pointer hover:bg-cyan-50" data-id="${o.id}">
        <div class="flex justify-between items-start">
          <div>
            <div class="font-mono text-xs">${o.order_no}</div>
            <div class="text-sm font-medium">${o.patient?.name||'-'}</div>
            <div class="text-xs text-slate-500">${o.patient?.hn||''} • ${o.item_count} test</div>
          </div>
          <span class="text-xs px-1.5 py-0.5 rounded ${SC[o.status]||''}">${SL[o.status]||o.status}</span>
        </div>
      </li>`).join('') || '<em class="text-slate-400 text-sm">ยังไม่มี order</em>';
    document.querySelectorAll('#list li').forEach(li => li.addEventListener('click', () => loadDetail(+li.dataset.id)));
}

async function loadDetail(id) {
    currentOrderId = id;
    const r = await api.call(`/lab-orders/${id}`);
    if (!r.ok) return;
    const o = r.data.data;
    const editable = o.status !== 'cancelled';
    document.getElementById('detail').innerHTML = `
      <div class="flex items-start justify-between mb-3">
        <div>
          <h2 class="font-mono text-lg">${o.order_no}</h2>
          <div class="text-sm">${o.patient?.name||''} (${o.patient?.hn||''}) • โดย ${o.ordered_by||'-'}</div>
          <div class="text-xs text-slate-500">${o.ordered_at?.replace('T',' ').slice(0,16)}${o.result_date ? ' • ผลออก '+o.result_date : ''}</div>
        </div>
        <span class="text-xs px-2 py-1 rounded ${SC[o.status]||''}">${SL[o.status]||o.status}</span>
      </div>
      ${o.notes ? `<div class="text-sm bg-slate-50 p-2 rounded mb-3">${o.notes}</div>` : ''}
      <table class="min-w-full text-sm mb-3">
        <thead class="bg-slate-100"><tr>
          <th class="px-2 py-1 text-left">รายการ</th>
          <th class="px-2 py-1 text-right">ค่าที่วัด</th>
          <th class="px-2 py-1 text-left">หน่วย</th>
          <th class="px-2 py-1 text-left">ค่าอ้างอิง</th>
          <th class="px-2 py-1 text-left">สถานะ</th>
        </tr></thead>
        <tbody id="result-rows">${(o.rows||[]).map(r => {
          const ref = r.ref_min !== null && r.ref_max !== null ? `${r.ref_min} - ${r.ref_max}` : (r.ref_text || '—');
          return `<tr class="border-t" data-test="${r.lab_test_id}">
            <td class="px-2 py-1">${r.code ? `<span class="font-mono text-xs text-slate-500 mr-1">${r.code}</span>` : ''}${r.name||''}</td>
            <td class="px-2 py-1 text-right"><input type="number" step="0.0001" class="result-input w-24 border rounded px-1 py-0.5 text-right" value="${r.value_numeric ?? ''}" ${editable ? '' : 'disabled'}></td>
            <td class="px-2 py-1 text-xs text-slate-500">${r.unit||''}</td>
            <td class="px-2 py-1 text-xs text-slate-500">${ref}</td>
            <td class="px-2 py-1">${r.abnormal_flag ? `<span class="px-2 py-0.5 rounded text-xs font-semibold ${FC[r.abnormal_flag]||''}">${FL[r.abnormal_flag]||r.abnormal_flag}</span>` : '<span class="text-slate-400 text-xs">รอบันทึก</span>'}</td>
          </tr>`;
        }).join('')}</tbody>
      </table>
      <div class="flex gap-2">
        ${editable ? `<button id="btn-save-results" class="bg-emerald-600 text-white px-3 py-1 rounded text-sm">บันทึกผล (auto-flag)</button>` : ''}
        ${o.status !== 'completed' && o.status !== 'cancelled' ? `<button id="btn-cancel" class="bg-rose-600 text-white px-3 py-1 rounded text-sm">ยกเลิก order</button>` : ''}
        <a href="/patients/${o.patient?.uuid}" class="bg-slate-100 px-3 py-1 rounded text-sm">📋 OPD Card</a>
        ${o.report_url ? `<a href="${o.report_url}" target="_blank" class="bg-slate-100 px-3 py-1 rounded text-sm">📎 ดูใบรายงาน</a>` : ''}
        ${editable ? `<label class="bg-cyan-50 px-3 py-1 rounded text-sm cursor-pointer">📤 แนบใบรายงาน<input type="file" id="report-upload" class="hidden" accept="image/jpeg,image/png,image/webp,application/pdf"></label>` : ''}
      </div>`;

    document.getElementById('btn-save-results')?.addEventListener('click', async () => {
        const rows = Array.from(document.querySelectorAll('#result-rows tr')).map(tr => ({
            lab_test_id: +tr.dataset.test,
            value_numeric: tr.querySelector('.result-input').value !== '' ? +tr.querySelector('.result-input').value : null,
        })).filter(r => r.value_numeric !== null);
        if (rows.length === 0) return alert('โปรดกรอกค่าอย่างน้อย 1 รายการ');
        const r = await api.call(`/lab-orders/${id}/results`, { method: 'POST', body: JSON.stringify({ rows }) });
        if (!r.ok) return alert((r.data && r.data.message) || 'บันทึกไม่ได้');
        await loadList();
        await loadDetail(id);
    });
    document.getElementById('btn-cancel')?.addEventListener('click', async () => {
        const reason = prompt('เหตุผลที่ยกเลิก:');
        if (!reason) return;
        const r = await api.call(`/lab-orders/${id}/cancel`, { method: 'POST', body: JSON.stringify({ reason }) });
        if (!r.ok) return alert((r.data && r.data.message) || 'ยกเลิกไม่ได้');
        await loadList();
        await loadDetail(id);
    });
    document.getElementById('report-upload')?.addEventListener('change', async e => {
        const f = e.target.files[0];
        if (!f) return;
        const fd = new FormData();
        fd.append('file', f);
        const r = await api.upload(`/lab-orders/${id}/report`, fd);
        if (!r.ok) return alert((r.data && r.data.message) || 'อัปโหลดไม่ได้');
        await loadDetail(id);
    });
}

document.getElementById('btn-new').addEventListener('click', async () => {
    if (tests.length === 0) {
        const r = await api.call('/lab-tests');
        if (!r.ok) return;
        tests = r.data.data || [];
    }
    document.getElementById('test-pick').innerHTML = tests.map(t => `
      <label class="text-sm flex items-center gap-1">
        <input type="checkbox" value="${t.id}" class="test-cb">
        <span class="font-mono text-xs text-slate-500">${t.code}</span> ${t.name}
      </label>`).join('');
    document.getElementById('new-dialog').showModal();
});

document.getElementById('new-form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const ids = Array.from(document.querySelectorAll('.test-cb:checked')).map(cb => +cb.value);
    if (ids.length === 0) return alert('โปรดเลือกการตรวจอย่างน้อย 1 รายการ');
    const r = await api.call('/lab-orders', {
        method: 'POST',
        body: JSON.stringify({
            patient_uuid: f.patient_uuid.value.trim(),
            test_ids: ids,
            notes: f.notes.value || null,
            status: 'sent',
        }),
    });
    if (!r.ok) return alert((r.data && r.data.message) || 'สร้างไม่ได้');
    f.reset();
    document.getElementById('new-dialog').close();
    await loadList();
    loadDetail(r.data.data.id);
});

document.querySelectorAll('.dlg-cancel').forEach(b => b.addEventListener('click', e => e.target.closest('dialog').close()));
document.querySelectorAll('#status-filter button').forEach(b => {
    b.addEventListener('click', () => {
        currentStatus = b.dataset.st;
        document.querySelectorAll('#status-filter button').forEach(x => {
            x.className = `text-xs px-2 py-1 rounded ${x.dataset.st === currentStatus ? 'bg-cyan-600 text-white' : 'bg-white border'}`;
        });
        loadList();
    });
});

(async function () {
    if (!api.token()) return window.location.href = '/login';
    await loadList();
})();
</script>
@endsection
