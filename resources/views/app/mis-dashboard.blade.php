@extends('layouts.app')
@section('title', 'MIS Executive Dashboard')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">📊 MIS Executive Dashboard</h1>
            <a href="/reports" class="ml-auto text-sm text-cyan-700 hover:underline">📋 Reports Library</a>
            <select id="period" class="border rounded px-2 py-1 text-sm">
                <option value="today">วันนี้</option>
                <option value="week">สัปดาห์นี้</option>
                <option value="month" selected>เดือนนี้</option>
                <option value="year">ปีนี้</option>
            </select>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6 space-y-6">
        <section id="kpis" class="grid grid-cols-2 md:grid-cols-5 gap-3"></section>
        <section id="snapshot" class="grid grid-cols-2 md:grid-cols-4 gap-3"></section>

        <section class="bg-white rounded-xl shadow p-4">
            <h3 class="font-semibold mb-3">📈 Trend (30 วันล่าสุด)</h3>
            <div id="chart" class="overflow-x-auto"></div>
        </section>

        <div class="grid md:grid-cols-2 gap-4">
            <section class="bg-white rounded-xl shadow p-4">
                <h3 class="font-semibold mb-3">🏆 Top หัตถการ</h3>
                <div id="top-procedures"></div>
            </section>
            <section class="bg-white rounded-xl shadow p-4">
                <h3 class="font-semibold mb-3">👨‍⚕️ Top แพทย์</h3>
                <div id="top-doctors"></div>
            </section>
            <section class="bg-white rounded-xl shadow p-4">
                <h3 class="font-semibold mb-3">💎 Top ลูกค้า</h3>
                <div id="top-patients"></div>
            </section>
            <section class="bg-white rounded-xl shadow p-4">
                <h3 class="font-semibold mb-3">🎯 Top กลุ่มลูกค้า</h3>
                <div id="top-groups"></div>
            </section>
        </div>
    </main>
</div>
@endsection

@section('scripts')
<script>
function fmt(n) { return (+n||0).toLocaleString(undefined, {maximumFractionDigits:2}); }
function fmtPct(p) { return (p === null || p === undefined) ? '—' : (p > 0 ? '+' : '') + p + '%'; }
function deltaClass(p) {
    if (p === null || p === undefined) return 'text-slate-400';
    return p > 0 ? 'text-emerald-700' : (p < 0 ? 'text-rose-700' : 'text-slate-600');
}

async function loadDashboard() {
    const period = document.getElementById('period').value;
    const r = await api.call(`/mis/dashboard?period=${period}`);
    if (!r.ok) return;
    const d = r.data.data;

    document.getElementById('kpis').innerHTML = [
        ['💰 รายได้', d.kpis.revenue, '฿'],
        ['🏥 Visit', d.kpis.visits, ''],
        ['🆕 ลูกค้าใหม่', d.kpis.new_patients, ''],
        ['💵 เฉลี่ย/บิล', d.kpis.avg_ticket, '฿'],
        ['📈 กำไรขั้นต้น', d.kpis.gross_profit, '฿'],
    ].map(([label, k, prefix]) => `
        <div class="bg-white rounded-xl shadow p-4">
            <div class="text-xs text-slate-500">${label}</div>
            <div class="text-2xl font-bold mt-1">${prefix}${fmt(k.current)}</div>
            <div class="text-xs ${deltaClass(k.pct)}">
                ${fmtPct(k.pct)} <span class="text-slate-400">vs ก่อน ${prefix}${fmt(k.previous)}</span>
            </div>
        </div>`).join('');

    document.getElementById('snapshot').innerHTML = `
        <div class="bg-emerald-50 rounded-xl p-3"><div class="text-xs text-emerald-700">💵 เงินสดในมือ</div><div class="text-xl font-bold text-emerald-700">${fmt(d.snapshot.cash_on_hand)}</div></div>
        <div class="bg-amber-50 rounded-xl p-3"><div class="text-xs text-amber-700">💎 หนี้สินสมาชิก</div><div class="text-xl font-bold text-amber-700">${fmt(d.snapshot.wallet_liability)}</div></div>
        <div class="bg-cyan-50 rounded-xl p-3"><div class="text-xs text-cyan-700">📦 มูลค่าสต็อก</div><div class="text-xl font-bold text-cyan-700">${fmt(d.snapshot.stock_value)}</div></div>
        <div class="bg-violet-50 rounded-xl p-3"><div class="text-xs text-violet-700">🎫 คอร์สใช้งาน</div><div class="text-xl font-bold text-violet-700">${d.snapshot.active_courses}</div></div>`;
}

async function loadCharts() {
    const r = await api.call('/mis/charts?days=30');
    if (!r.ok) return;
    const rows = r.data.data.rows || [];
    const max = Math.max(1, ...rows.map(r => r.revenue));
    document.getElementById('chart').innerHTML = `
        <div class="flex gap-1 items-end h-40 min-w-[800px]">
            ${rows.map(r => `
                <div class="flex-1 flex flex-col items-center group">
                    <div class="text-[10px] text-slate-400 opacity-0 group-hover:opacity-100">${fmt(r.revenue)}</div>
                    <div class="w-full bg-cyan-500 hover:bg-cyan-600 rounded-t transition" style="height: ${Math.max(2, (r.revenue / max) * 100)}%"
                         title="${r.date}: ฿${fmt(r.revenue)} • ${r.visits} visits"></div>
                </div>`).join('')}
        </div>
        <div class="flex gap-1 mt-2 min-w-[800px] text-[10px] text-slate-400">
            ${rows.map((r, i) => i % 5 === 0 ? `<div class="flex-1 text-center">${r.date.slice(5)}</div>` : '<div class="flex-1"></div>').join('')}
        </div>`;
}

async function loadTops() {
    const params = '?from='+new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10)+'&to='+new Date().toISOString().slice(0, 10);
    const [tp, td, tg, tt] = await Promise.all([
        api.call('/mis/top-procedures'+params),
        api.call('/mis/top-doctors'+params),
        api.call('/mis/top-customer-groups'+params),
        api.call('/mis/top-patients'+params),
    ]);

    document.getElementById('top-procedures').innerHTML = topTable(tp.data?.data || [], r => `<a class="text-cyan-700 hover:underline" href="/reports/procedure-performance">${r.name}</a>`, r => `${r.count} ครั้ง • ฿${fmt(r.revenue)}`, r => `gp ${fmt(r.gross)}`);
    document.getElementById('top-doctors').innerHTML = topTable(td.data?.data || [], r => r.name, r => `${r.visits} visit`, r => `฿${fmt(r.revenue)}`);
    document.getElementById('top-groups').innerHTML = topTable(tg.data?.data || [], r => r.name, r => `${r.patient_count} คน`, r => `฿${fmt(r.revenue)}`);
    document.getElementById('top-patients').innerHTML = topTable(tt.data?.data || [], r => `<a class="text-cyan-700 hover:underline" href="/patients/${r.uuid}">${r.name}</a> <span class="text-xs text-slate-400 font-mono">${r.hn}</span>`, r => `${r.visit_count} ครั้ง`, r => `฿${fmt(r.revenue)}`);
}

function topTable(rows, leftFn, midFn, rightFn) {
    if (rows.length === 0) return '<div class="text-slate-400 text-sm">ไม่มีข้อมูล</div>';

    return '<ul class="divide-y text-sm">' + rows.slice(0, 5).map(r => `
        <li class="py-2 flex justify-between items-center gap-2">
            <span>${leftFn(r)}</span>
            <span class="text-xs text-slate-500">${midFn(r)}</span>
            <span class="font-semibold">${rightFn(r)}</span>
        </li>`).join('') + '</ul>';
}

document.getElementById('period').addEventListener('change', loadDashboard);

(async function () {
    if (!api.token()) return window.location.href = '/login';
    await Promise.all([loadDashboard(), loadCharts(), loadTops()]);
})();
</script>
@endsection
