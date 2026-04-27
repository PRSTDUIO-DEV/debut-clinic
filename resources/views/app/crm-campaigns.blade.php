@extends('layouts.app')
@section('title', 'CRM Campaigns')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">📣 CRM Campaigns</h1>
            <a href="/crm/segments" class="ml-auto text-sm text-cyan-700 hover:underline">Segments</a>
            <a href="/crm/templates" class="text-sm text-cyan-700 hover:underline">Templates</a>
            <button id="btn-new" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">+ Campaign ใหม่</button>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6 grid md:grid-cols-3 gap-4">
        <section class="md:col-span-1 bg-white rounded-xl shadow p-4">
            <ul id="list" class="space-y-2 max-h-[700px] overflow-y-auto"></ul>
        </section>
        <section class="md:col-span-2 bg-white rounded-xl shadow p-4">
            <div id="detail" class="text-slate-500">เลือก campaign จากด้านซ้าย</div>
        </section>
    </main>
</div>

<dialog id="new-dialog" class="rounded-xl p-0 w-[480px] max-w-full">
    <form id="new-form" class="p-4 space-y-3">
        <h3 class="font-semibold text-lg">Campaign ใหม่</h3>
        <label class="block text-sm">ชื่อ
            <input name="name" required maxlength="200" class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <label class="block text-sm">Segment
            <select name="segment_id" required class="w-full border rounded px-2 py-1 mt-1"></select>
        </label>
        <label class="block text-sm">Template
            <select name="template_id" required class="w-full border rounded px-2 py-1 mt-1"></select>
        </label>
        <label class="block text-sm">เวลาส่ง (เว้นว่าง = draft)
            <input name="scheduled_at" type="datetime-local" class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <div class="flex justify-end gap-2">
            <button type="button" class="dlg-cancel px-3 py-1 rounded bg-slate-100">ยกเลิก</button>
            <button type="submit" class="px-3 py-1 rounded bg-cyan-600 text-white">สร้าง</button>
        </div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
const SL = { draft: 'ร่าง', scheduled: 'นัดส่ง', sending: 'กำลังส่ง', completed: 'ส่งเสร็จ', failed: 'ล้มเหลว', cancelled: 'ยกเลิก' };
const SC = {
    draft: 'bg-slate-100 text-slate-700',
    scheduled: 'bg-amber-100 text-amber-800',
    sending: 'bg-blue-100 text-blue-800',
    completed: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-red-100 text-red-800',
    cancelled: 'bg-stone-200 text-stone-700',
};
const CHL = { line: 'LINE', sms: 'SMS', email: 'Email' };
const MS = { sent: 'ส่งแล้ว', failed: 'ล้มเหลว', skipped: 'ข้าม', pending: 'รอ' };
const MC = {
    sent: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-red-100 text-red-800',
    skipped: 'bg-amber-100 text-amber-800',
    pending: 'bg-slate-100 text-slate-700',
};

async function loadList() {
    const r = await api.call('/crm/campaigns');
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('list').innerHTML = rows.map(c => `
      <li class="border rounded p-2 cursor-pointer hover:bg-cyan-50" data-id="${c.id}">
        <div class="flex justify-between items-start">
          <div>
            <div class="font-medium">${c.name}</div>
            <div class="text-xs text-slate-500">${c.segment?.name||'-'} → ${CHL[c.template?.channel]||''}</div>
            ${c.scheduled_at ? `<div class="text-xs text-slate-500">⏰ ${c.scheduled_at.replace('T',' ').slice(0,16)}</div>` : ''}
          </div>
          <span class="text-xs px-1.5 py-0.5 rounded ${SC[c.status]||''}">${SL[c.status]||c.status}</span>
        </div>
        <div class="text-xs text-slate-500 mt-1">${c.sent_count}/${c.total_recipients} ส่ง • ${c.failed_count} ล้ม • ${c.skipped_count} ข้าม</div>
      </li>`).join('') || '<em class="text-slate-400 text-sm">ยังไม่มี campaign</em>';
    document.querySelectorAll('#list li').forEach(li => li.addEventListener('click', () => loadDetail(+li.dataset.id)));
}

async function loadDetail(id) {
    const r = await api.call(`/crm/campaigns/${id}`);
    if (!r.ok) return;
    const c = r.data.data;
    const canSend = c.status === 'draft' || c.status === 'scheduled';
    const canCancel = c.status === 'draft' || c.status === 'scheduled' || c.status === 'sending';
    document.getElementById('detail').innerHTML = `
      <div class="flex items-start justify-between mb-3">
        <div>
          <h2 class="text-xl font-bold">${c.name}</h2>
          <div class="text-sm text-slate-600">${c.segment?.name||''} → ${CHL[c.template?.channel]||''} ${c.template?.name||''}</div>
          <div class="text-xs text-slate-500">โดย ${c.created_by||'-'} ${c.scheduled_at ? '• ⏰ '+c.scheduled_at.replace('T',' ').slice(0,16) : ''}</div>
        </div>
        <span class="px-2 py-1 rounded text-xs ${SC[c.status]||''}">${SL[c.status]||c.status}</span>
      </div>
      <div class="grid grid-cols-4 gap-2 mb-4 text-center text-sm">
        <div class="p-2 bg-slate-50 rounded">รวม<br><b class="text-lg">${c.total_recipients}</b></div>
        <div class="p-2 bg-emerald-50 rounded">ส่งแล้ว<br><b class="text-lg text-emerald-700">${c.sent_count}</b></div>
        <div class="p-2 bg-rose-50 rounded">ล้มเหลว<br><b class="text-lg text-rose-700">${c.failed_count}</b></div>
        <div class="p-2 bg-amber-50 rounded">ข้าม<br><b class="text-lg text-amber-700">${c.skipped_count}</b></div>
      </div>
      <div class="mb-4 flex gap-2">
        ${canSend ? `<button id="btn-send" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">▶︎ ส่งทันที</button>` : ''}
        ${canCancel ? `<button id="btn-cancel" class="bg-rose-600 text-white px-3 py-1 rounded text-sm">ยกเลิก</button>` : ''}
      </div>
      <h3 class="font-medium text-sm mb-2">Recipients (${(c.messages||[]).length})</h3>
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100"><tr>
          <th class="px-2 py-1 text-left">ผู้ป่วย</th>
          <th class="px-2 py-1 text-left">Channel</th>
          <th class="px-2 py-1 text-left">Address</th>
          <th class="px-2 py-1 text-left">Status</th>
          <th class="px-2 py-1 text-left">Sent At</th>
        </tr></thead>
        <tbody>${(c.messages||[]).map(m => `
          <tr class="border-t">
            <td class="px-2 py-1">${m.patient?.name||''} <span class="text-xs text-slate-500 font-mono">${m.patient?.hn||''}</span></td>
            <td class="px-2 py-1">${CHL[m.channel]||m.channel}</td>
            <td class="px-2 py-1 text-xs">${m.recipient_address||'-'}</td>
            <td class="px-2 py-1"><span class="px-2 py-0.5 rounded text-xs ${MC[m.status]||''}">${MS[m.status]||m.status}</span></td>
            <td class="px-2 py-1 text-xs text-slate-500">${m.sent_at ? m.sent_at.replace('T',' ').slice(0,16) : '-'}</td>
          </tr>`).join('') || '<tr><td colspan="5" class="text-center py-4 text-slate-500">ยังไม่ส่ง</td></tr>'}</tbody>
      </table>`;

    document.getElementById('btn-send')?.addEventListener('click', async () => {
        if (!confirm('ส่ง campaign นี้ทันที?')) return;
        const r = await api.call(`/crm/campaigns/${id}/send`, { method: 'POST', body: JSON.stringify({}) });
        if (!r.ok) return alert((r.data && r.data.message) || 'ส่งไม่ได้');
        await loadList();
        loadDetail(id);
    });
    document.getElementById('btn-cancel')?.addEventListener('click', async () => {
        const reason = prompt('เหตุผลยกเลิก:');
        if (!reason) return;
        const r = await api.call(`/crm/campaigns/${id}/cancel`, { method: 'POST', body: JSON.stringify({ reason }) });
        if (!r.ok) return alert((r.data && r.data.message) || 'ยกเลิกไม่ได้');
        await loadList();
        loadDetail(id);
    });
}

document.getElementById('btn-new').addEventListener('click', async () => {
    const [seg, tpl] = await Promise.all([api.call('/crm/segments?only_active=1'), api.call('/crm/templates?only_active=1')]);
    if (!seg.ok || !tpl.ok) return;
    const ssel = document.querySelector('[name=segment_id]');
    const tsel = document.querySelector('[name=template_id]');
    ssel.innerHTML = (seg.data.data||[]).map(s => `<option value="${s.id}">${s.name} (${s.last_resolved_count} คน)</option>`).join('');
    tsel.innerHTML = (tpl.data.data||[]).map(t => `<option value="${t.id}">[${t.channel.toUpperCase()}] ${t.name}</option>`).join('');
    document.getElementById('new-dialog').showModal();
});

document.getElementById('new-form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const body = {
        name: f.name.value,
        segment_id: +f.segment_id.value,
        template_id: +f.template_id.value,
        scheduled_at: f.scheduled_at.value || null,
    };
    const r = await api.call('/crm/campaigns', { method: 'POST', body: JSON.stringify(body) });
    if (!r.ok) return alert((r.data && r.data.message) || 'สร้างไม่ได้');
    f.reset();
    document.getElementById('new-dialog').close();
    await loadList();
    loadDetail(r.data.data.id);
});

document.querySelectorAll('.dlg-cancel').forEach(b => b.addEventListener('click', e => e.target.closest('dialog').close()));

(async function () {
    if (!api.token()) return window.location.href = '/login';
    await loadList();
})();
</script>
@endsection
