@extends('layouts.app')
@section('title', 'QC Runs')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">✅ QC Runs</h1>
            <a href="/qc/checklists" class="text-sm text-cyan-700 hover:underline">Checklists</a>
            <select id="status" class="ml-auto border rounded px-2 py-1 text-sm">
                <option value="">ทุกสถานะ</option><option>pending</option><option>in_progress</option><option>completed</option>
            </select>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6 space-y-3">
        <section id="summary" class="grid grid-cols-2 md:grid-cols-5 gap-3"></section>
        <div class="bg-white rounded-xl shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100"><tr>
                    <th class="px-3 py-2 text-left">วันที่</th>
                    <th class="text-left">Checklist</th>
                    <th>Status</th>
                    <th>ผ่าน/ทั้งหมด</th>
                    <th>Failed</th>
                    <th>โดย</th>
                    <th></th>
                </tr></thead>
                <tbody id="rows"></tbody>
            </table>
        </div>
    </main>
</div>
@endsection

@section('scripts')
<script>
async function loadSummary() {
    const r = await api.call('/qc/summary');
    if (!r.ok) return;
    const d = r.data.data;
    document.getElementById('summary').innerHTML = `
        <div class="bg-white rounded-xl shadow p-3"><div class="text-xs text-slate-500">รอบ 30 วัน</div><div class="text-2xl font-bold">${d.runs}</div></div>
        <div class="bg-emerald-50 rounded-xl p-3"><div class="text-xs text-emerald-700">เสร็จ</div><div class="text-xl font-bold text-emerald-700">${d.completed}</div></div>
        <div class="bg-cyan-50 rounded-xl p-3"><div class="text-xs text-cyan-700">รวมข้อ</div><div class="text-xl font-bold">${d.total_items}</div></div>
        <div class="bg-rose-50 rounded-xl p-3"><div class="text-xs text-rose-700">Failed</div><div class="text-xl font-bold text-rose-700">${d.failed}</div></div>
        <div class="bg-violet-50 rounded-xl p-3"><div class="text-xs text-violet-700">Pass Rate</div><div class="text-xl font-bold text-violet-700">${d.pass_rate_pct}%</div></div>`;
}

async function loadRuns() {
    const status = document.getElementById('status').value;
    const r = await api.call('/qc/runs' + (status ? '?status='+status : ''));
    if (!r.ok) return;
    const rows = r.data.data?.data || [];
    document.getElementById('rows').innerHTML = rows.map(r => `
        <tr class="border-t hover:bg-slate-50">
            <td class="px-3 py-1.5">${r.run_date}</td>
            <td>${r.checklist?.name || '?'}</td>
            <td class="text-center"><span class="px-2 py-0.5 rounded text-xs ${
                r.status === 'completed' ? 'bg-emerald-100 text-emerald-700' :
                r.status === 'in_progress' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100'
            }">${r.status}</span></td>
            <td class="text-center">${r.passed_count}/${r.total_items}</td>
            <td class="text-center ${r.failed_count > 0 ? 'text-rose-600 font-bold' : ''}">${r.failed_count}</td>
            <td class="text-xs">${r.performer?.name || '—'}</td>
            <td><a href="/qc/runs/${r.id}" class="text-cyan-700 text-xs hover:underline">เปิด →</a></td>
        </tr>`).join('') || `<tr><td colspan="7" class="text-center py-6 text-slate-400">ไม่มี run</td></tr>`;
}

document.getElementById('status').onchange = loadRuns;
(async function () { if (!api.token()) return location.href = '/login'; await Promise.all([loadSummary(), loadRuns()]); })();
</script>
@endsection
