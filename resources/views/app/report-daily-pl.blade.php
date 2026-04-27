@extends('layouts.app')
@section('title', 'รายงาน P/L รายวัน')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">📊 P/L รายวัน</h1>
            <div class="ml-auto flex gap-1">
                <a href="/reports/payment-mix" class="text-sm text-cyan-700 hover:underline">Payment Mix</a>
                <a href="/reports/doctor-performance" class="text-sm text-cyan-700 hover:underline">Doctor</a>
                <a href="/reports/procedure-performance" class="text-sm text-cyan-700 hover:underline">Procedure</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6 space-y-4">
        <section class="bg-white rounded-xl shadow p-4">
            <div class="flex flex-wrap gap-2 items-end mb-3">
                <label class="text-sm">จาก
                    <input id="date_from" type="date" class="border rounded px-2 py-1 mt-1">
                </label>
                <label class="text-sm">ถึง
                    <input id="date_to" type="date" class="border rounded px-2 py-1 mt-1">
                </label>
                <button id="btn-load" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">โหลด</button>
            </div>
            <div id="totals" class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-3"></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100"><tr>
                        <th class="px-3 py-2 text-left">วันที่</th>
                        <th class="px-3 py-2 text-right">รายได้</th>
                        <th class="px-3 py-2 text-right">COGS</th>
                        <th class="px-3 py-2 text-right">ค่าคอม</th>
                        <th class="px-3 py-2 text-right">MDR</th>
                        <th class="px-3 py-2 text-right">กำไรขั้นต้น</th>
                        <th class="px-3 py-2 text-right">รายจ่าย</th>
                        <th class="px-3 py-2 text-right">กำไรสุทธิ</th>
                    </tr></thead>
                    <tbody id="rows"></tbody>
                </table>
            </div>
        </section>
    </main>
</div>
@endsection

@section('scripts')
<script>
function fmt(n) { return (+n||0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}); }

async function load() {
    const f = document.getElementById('date_from').value;
    const t = document.getElementById('date_to').value;
    const r = await api.call(`/reports/daily-pl?date_from=${f}&date_to=${t}`);
    if (!r.ok) return;
    const d = r.data.data;
    const rows = d.rows || [];
    document.getElementById('rows').innerHTML = rows.map(x => `
      <tr class="border-t">
        <td class="px-3 py-1.5">${x.date}</td>
        <td class="px-3 py-1.5 text-right">${fmt(x.revenue)}</td>
        <td class="px-3 py-1.5 text-right text-amber-700">${fmt(x.cogs)}</td>
        <td class="px-3 py-1.5 text-right text-amber-700">${fmt(x.commission)}</td>
        <td class="px-3 py-1.5 text-right text-amber-700">${fmt(x.mdr)}</td>
        <td class="px-3 py-1.5 text-right font-semibold text-emerald-700">${fmt(x.gross_profit)}</td>
        <td class="px-3 py-1.5 text-right text-rose-700">${fmt(x.expenses)}</td>
        <td class="px-3 py-1.5 text-right font-bold ${x.net_profit >= 0 ? 'text-blue-700' : 'text-rose-700'}">${fmt(x.net_profit)}</td>
      </tr>`).join('') || '<tr><td colspan="8" class="text-center py-6 text-slate-500">ไม่มีข้อมูลในช่วงที่เลือก</td></tr>';

    const T = d.totals || {};
    document.getElementById('totals').innerHTML = `
      <div class="p-3 bg-slate-50 rounded">
        <div class="text-xs text-slate-500">รายได้รวม</div>
        <div class="text-lg font-bold">฿${fmt(T.revenue)}</div>
      </div>
      <div class="p-3 bg-emerald-50 rounded">
        <div class="text-xs text-emerald-600">กำไรขั้นต้น</div>
        <div class="text-lg font-bold text-emerald-700">฿${fmt(T.gross_profit)}</div>
      </div>
      <div class="p-3 bg-rose-50 rounded">
        <div class="text-xs text-rose-600">รายจ่ายรวม</div>
        <div class="text-lg font-bold text-rose-700">฿${fmt(T.expenses)}</div>
      </div>
      <div class="p-3 bg-blue-50 rounded">
        <div class="text-xs text-blue-600">กำไรสุทธิรวม</div>
        <div class="text-lg font-bold text-blue-700">฿${fmt(T.net_profit)}</div>
      </div>`;
}

document.getElementById('btn-load').addEventListener('click', load);

(async function () {
    if (!api.token()) return window.location.href = '/login';
    const today = new Date();
    const start = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('date_from').value = start.toISOString().slice(0, 10);
    document.getElementById('date_to').value = today.toISOString().slice(0, 10);
    await load();
})();
</script>
@endsection
