@extends('layouts.app')
@section('title', 'รายงานผลงานหมอ')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">👨‍⚕️ ผลงานหมอ</h1>
            <div class="ml-auto flex gap-1">
                <a href="/reports/daily-pl" class="text-sm text-cyan-700 hover:underline">P/L</a>
                <a href="/reports/procedure-performance" class="text-sm text-cyan-700 hover:underline">Procedure</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <section class="bg-white rounded-xl shadow p-4">
            <div class="flex flex-wrap gap-2 items-end mb-3">
                <label class="text-sm">จาก
                    <input id="date_from" type="date" class="border rounded px-2 py-1 mt-1">
                </label>
                <label class="text-sm">ถึง
                    <input id="date_to" type="date" class="border rounded px-2 py-1 mt-1">
                </label>
                <button id="btn-load" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">โหลด</button>
                <span id="period" class="text-xs text-slate-500"></span>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100"><tr>
                    <th class="px-3 py-2 text-left">หมอ</th>
                    <th class="px-3 py-2 text-right">Visit</th>
                    <th class="px-3 py-2 text-right">รายได้</th>
                    <th class="px-3 py-2 text-right">COGS</th>
                    <th class="px-3 py-2 text-right">กำไรขั้นต้น</th>
                    <th class="px-3 py-2 text-right">ค่ามือหมอ (DF)</th>
                    <th class="px-3 py-2 text-right">รวมค่าคอม</th>
                </tr></thead>
                <tbody id="rows"></tbody>
                <tfoot id="totals" class="bg-slate-50 font-bold"></tfoot>
            </table>
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
    const r = await api.call(`/reports/doctor-performance?date_from=${f}&date_to=${t}`);
    if (!r.ok) return;
    const d = r.data.data;
    const rows = d.rows || [];
    document.getElementById('rows').innerHTML = rows.map(x => `
      <tr class="border-t">
        <td class="px-3 py-1.5">${x.name||'-'}</td>
        <td class="px-3 py-1.5 text-right">${x.visits}</td>
        <td class="px-3 py-1.5 text-right">${fmt(x.revenue)}</td>
        <td class="px-3 py-1.5 text-right text-amber-700">${fmt(x.cogs)}</td>
        <td class="px-3 py-1.5 text-right text-emerald-700 font-semibold">${fmt(x.gross)}</td>
        <td class="px-3 py-1.5 text-right text-blue-700">${fmt(x.doctor_fee)}</td>
        <td class="px-3 py-1.5 text-right">${fmt(x.total_commission)}</td>
      </tr>`).join('') || '<tr><td colspan="7" class="text-center py-6 text-slate-500">ไม่มีข้อมูล</td></tr>';

    const T = d.totals || {};
    document.getElementById('totals').innerHTML = `
      <tr class="border-t">
        <td class="px-3 py-2">รวม</td>
        <td class="px-3 py-2 text-right">${T.visits||0}</td>
        <td class="px-3 py-2 text-right">${fmt(T.revenue)}</td>
        <td class="px-3 py-2 text-right text-amber-700">${fmt(T.cogs)}</td>
        <td class="px-3 py-2 text-right text-emerald-700">${fmt(T.gross)}</td>
        <td class="px-3 py-2 text-right text-blue-700">${fmt(T.doctor_fee)}</td>
        <td class="px-3 py-2 text-right">${fmt(T.total_commission)}</td>
      </tr>`;
    document.getElementById('period').textContent = `${d.date_from} → ${d.date_to}`;
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
