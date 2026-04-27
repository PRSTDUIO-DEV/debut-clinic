@extends('layouts.app')
@section('title', 'ปิดยอดประจำวัน')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">📒 ปิดยอดประจำวัน (Daily Closing)</h1>
            <a href="/expenses" class="ml-auto text-sm text-cyan-700 hover:underline">รายจ่าย</a>
            <a href="/reports/daily-pl" class="text-sm text-cyan-700 hover:underline">P/L Report</a>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6 grid md:grid-cols-3 gap-4">
        <section class="md:col-span-1 bg-white rounded-xl shadow p-4">
            <h3 class="font-semibold mb-3">เลือกวันที่</h3>
            <div class="flex gap-2 mb-3">
                <input id="date" type="date" class="flex-1 border rounded px-2 py-1">
                <button id="btn-prepare" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">เตรียมยอด</button>
            </div>
            <h3 class="font-semibold mb-2 mt-4 text-sm">ปิดยอดล่าสุด</h3>
            <ul id="list" class="text-sm space-y-1 max-h-[500px] overflow-y-auto"></ul>
        </section>
        <section class="md:col-span-2 bg-white rounded-xl shadow p-4">
            <div id="detail" class="text-slate-500">เลือกวันแล้วกด "เตรียมยอด"</div>
        </section>
    </main>
</div>
@endsection

@section('scripts')
<script>
const SL = { draft: 'ร่าง', closed: 'ปิดแล้ว', reopened: 'เปิดใหม่' };
const SC = {
    draft: 'bg-amber-100 text-amber-800',
    closed: 'bg-emerald-100 text-emerald-800',
    reopened: 'bg-blue-100 text-blue-800',
};

let current = null;

async function loadList() {
    const r = await api.call('/closings');
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('list').innerHTML = rows.map(c => `
      <li class="border rounded px-2 py-1 cursor-pointer hover:bg-cyan-50" data-id="${c.id}">
        <div class="flex justify-between">
          <span>${c.closing_date}</span>
          <span class="text-xs px-1.5 py-0.5 rounded ${SC[c.status]||''}">${SL[c.status]||c.status}</span>
        </div>
        <div class="text-xs text-slate-500">รายได้ ${c.total_revenue.toLocaleString()} • กำไร ${c.net_profit.toLocaleString()}</div>
      </li>`).join('') || '<em class="text-slate-400">ยังไม่มี</em>';
    document.querySelectorAll('#list li').forEach(li => li.addEventListener('click', () => showById(+li.dataset.id)));
}

async function showById(id) {
    const r = await api.call(`/closings/${id}`);
    if (!r.ok) return;
    show(r.data.data);
}

function show(c) {
    current = c;
    const editable = c.status !== 'closed';
    document.getElementById('detail').innerHTML = `
      <div class="flex items-start justify-between mb-3">
        <div>
          <h2 class="text-xl font-bold">${c.closing_date}</h2>
          ${c.closed_by ? `<div class="text-sm text-slate-500">ปิดโดย ${c.closed_by} • ${c.closed_at?.replace('T',' ').slice(0,16)}</div>` : ''}
        </div>
        <span class="text-xs px-2 py-1 rounded ${SC[c.status]||''}">${SL[c.status]||c.status}</span>
      </div>
      <div class="grid grid-cols-2 gap-3 mb-4">
        <div class="p-3 bg-slate-50 rounded">
          <div class="text-xs text-slate-500">รายได้</div>
          <div class="font-bold text-lg">฿${c.total_revenue.toLocaleString()}</div>
        </div>
        <div class="p-3 bg-slate-50 rounded">
          <div class="text-xs text-slate-500">ต้นทุน + ค่าคอม + MDR</div>
          <div class="font-bold text-lg text-amber-700">฿${(c.total_cogs + c.total_commission + c.total_mdr).toLocaleString()}</div>
          <div class="text-xs text-slate-400">COGS ${c.total_cogs.toLocaleString()} • Com ${c.total_commission.toLocaleString()} • MDR ${c.total_mdr.toLocaleString()}</div>
        </div>
        <div class="p-3 bg-emerald-50 rounded">
          <div class="text-xs text-emerald-600">กำไรขั้นต้น (Gross)</div>
          <div class="font-bold text-lg text-emerald-700">฿${c.gross_profit.toLocaleString()}</div>
        </div>
        <div class="p-3 bg-rose-50 rounded">
          <div class="text-xs text-rose-600">รายจ่ายอื่น</div>
          <div class="font-bold text-lg text-rose-700">฿${c.total_expenses.toLocaleString()}</div>
        </div>
        <div class="p-3 bg-blue-50 rounded col-span-2">
          <div class="text-xs text-blue-600">กำไรสุทธิ (Net Profit)</div>
          <div class="font-bold text-2xl text-blue-700">฿${c.net_profit.toLocaleString()}</div>
        </div>
      </div>

      <h3 class="font-semibold mb-2">เงินสด</h3>
      <div class="grid grid-cols-3 gap-2 mb-3">
        <div class="p-2 bg-slate-50 rounded text-center">
          <div class="text-xs text-slate-500">เงินสดควรจะมี</div>
          <div class="font-bold">${c.expected_cash.toLocaleString()}</div>
        </div>
        <div class="p-2 bg-slate-50 rounded text-center">
          <div class="text-xs text-slate-500">นับจริง</div>
          ${editable ? `<input id="counted" type="number" step="0.01" class="w-full border rounded px-1 py-0.5 text-center font-bold" value="${c.counted_cash}">` : `<div class="font-bold">${c.counted_cash.toLocaleString()}</div>`}
        </div>
        <div class="p-2 bg-slate-50 rounded text-center">
          <div class="text-xs text-slate-500">ส่วนต่าง</div>
          <div id="variance" class="font-bold ${c.variance < 0 ? 'text-rose-700' : (c.variance > 0 ? 'text-emerald-700' : '')}">${c.variance.toLocaleString()}</div>
        </div>
      </div>

      <h3 class="font-semibold mb-2 text-sm">วิธีรับเงินทั้งหมด</h3>
      <div class="grid grid-cols-2 md:grid-cols-5 gap-2 mb-4 text-sm">
        ${Object.entries(c.payment_breakdown||{}).map(([k,v]) => `
          <div class="p-2 bg-slate-50 rounded text-center">
            <div class="text-xs text-slate-500">${k}</div>
            <div class="font-semibold">${(+v).toLocaleString()}</div>
          </div>`).join('')}
      </div>

      ${editable ? `
        <label class="text-sm block mb-2">หมายเหตุ
          <input id="notes" class="w-full border rounded px-2 py-1 mt-1" value="${c.notes||''}">
        </label>
        <div class="flex gap-2">
          <button id="btn-recalc" class="bg-slate-100 px-3 py-1 rounded text-sm">คำนวณใหม่</button>
          <button id="btn-commit" class="bg-emerald-600 text-white px-3 py-1 rounded text-sm">✓ ปิดยอด</button>
        </div>
      ` : `
        <div class="flex gap-2">
          <button id="btn-reopen" class="bg-amber-600 text-white px-3 py-1 rounded text-sm">เปิดใหม่</button>
        </div>
        ${c.notes ? `<div class="mt-3 text-sm bg-slate-50 p-2 rounded">${c.notes}</div>` : ''}
      `}`;

    document.getElementById('counted')?.addEventListener('input', () => {
        const v = +document.getElementById('counted').value || 0;
        const variance = v - c.expected_cash;
        const el = document.getElementById('variance');
        el.textContent = variance.toLocaleString();
        el.className = `font-bold ${variance < 0 ? 'text-rose-700' : (variance > 0 ? 'text-emerald-700' : '')}`;
    });

    document.getElementById('btn-recalc')?.addEventListener('click', async () => {
        const r = await api.call('/closings/prepare', { method: 'POST', body: JSON.stringify({ date: c.closing_date }) });
        if (!r.ok) return alert((r.data && r.data.message) || 'ไม่ได้');
        show(r.data.data);
        loadList();
    });

    document.getElementById('btn-commit')?.addEventListener('click', async () => {
        const counted = +document.getElementById('counted').value || 0;
        if (!confirm(`ปิดยอดวัน ${c.closing_date} ด้วยเงินสดนับ ฿${counted.toLocaleString()}?`)) return;
        const r = await api.call(`/closings/${c.id}/commit`, {
            method: 'POST',
            body: JSON.stringify({ counted_cash: counted, notes: document.getElementById('notes').value || null }),
        });
        if (!r.ok) return alert((r.data && r.data.message) || 'ปิดไม่ได้');
        show(r.data.data);
        loadList();
    });

    document.getElementById('btn-reopen')?.addEventListener('click', async () => {
        const reason = prompt('เหตุผลที่ต้องเปิดใหม่:');
        if (!reason) return;
        const r = await api.call(`/closings/${c.id}/reopen`, { method: 'POST', body: JSON.stringify({ reason }) });
        if (!r.ok) return alert((r.data && r.data.message) || 'เปิดไม่ได้');
        show(r.data.data);
        loadList();
    });
}

document.getElementById('btn-prepare').addEventListener('click', async () => {
    const date = document.getElementById('date').value;
    if (!date) return alert('เลือกวันที่ก่อน');
    const r = await api.call('/closings/prepare', { method: 'POST', body: JSON.stringify({ date }) });
    if (!r.ok) return alert((r.data && r.data.message) || 'ไม่ได้');
    show(r.data.data);
    loadList();
});

(async function () {
    if (!api.token()) return window.location.href = '/login';
    document.getElementById('date').value = new Date().toISOString().slice(0, 10);
    await loadList();
})();
</script>
@endsection
