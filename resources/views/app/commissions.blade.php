@extends('layouts.app')
@section('title', 'ค่าคอม / ค่ามือแพทย์')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">ค่าคอม / ค่ามือแพทย์</h1>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6 space-y-4">
        <div class="bg-white rounded-xl shadow p-4 flex flex-wrap gap-3 items-center">
            <label class="text-sm">เดือน <input id="month" type="month" class="border rounded px-2 py-1.5 text-sm"></label>
            <button id="apply" class="bg-cyan-600 hover:bg-cyan-700 text-white px-3 py-1.5 rounded text-sm font-semibold">โหลดสรุป</button>
            <span id="grand" class="ml-auto text-sm font-semibold text-slate-700"></span>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3" id="totals"></div>

        <div class="bg-white rounded-xl shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left px-3 py-2">ผู้รับ</th>
                        <th class="text-right px-3 py-2">ค่ามือแพทย์</th>
                        <th class="text-right px-3 py-2">คอมพนักงาน</th>
                        <th class="text-right px-3 py-2">Referral</th>
                        <th class="text-right px-3 py-2">รวม</th>
                        <th class="text-right px-3 py-2">รายการ</th>
                    </tr>
                </thead>
                <tbody id="rows"><tr><td colspan="6" class="text-center text-slate-500 p-4">โหลด...</td></tr></tbody>
            </table>
        </div>
    </main>
</div>
@endsection

@section('scripts')
<script>
async function load() {
    const m = document.getElementById('month').value || new Date().toISOString().slice(0,7);
    const r = await api.call('/commissions/summary?month=' + m);
    if (!r.ok) { document.getElementById('rows').innerHTML = '<tr><td colspan="6" class="text-red-600 p-4">โหลดไม่สำเร็จ</td></tr>'; return; }
    const d = r.data.data;
    const t = d.totals;
    document.getElementById('grand').textContent = `เดือน ${d.month} รวมทั้งสิ้น ${t.grand_total.toLocaleString()}`;
    document.getElementById('totals').innerHTML = `
        <div class="rounded-lg bg-blue-50 p-3 text-center"><div class="text-xs text-blue-700">ค่ามือแพทย์</div><div class="font-bold text-xl">${t.doctor_fee.toLocaleString()}</div></div>
        <div class="rounded-lg bg-emerald-50 p-3 text-center"><div class="text-xs text-emerald-700">คอมพนักงาน</div><div class="font-bold text-xl">${t.staff_commission.toLocaleString()}</div></div>
        <div class="rounded-lg bg-amber-50 p-3 text-center"><div class="text-xs text-amber-700">Referral</div><div class="font-bold text-xl">${t.referral.toLocaleString()}</div></div>
        <div class="rounded-lg bg-slate-100 p-3 text-center"><div class="text-xs text-slate-700">รวม</div><div class="font-bold text-xl">${t.grand_total.toLocaleString()}</div></div>
    `;
    document.getElementById('rows').innerHTML = d.rows.length ? d.rows.map(row => `
        <tr class="border-b">
            <td class="px-3 py-2 font-semibold">${row.user_name || '—'}</td>
            <td class="px-3 py-2 text-right">${row.doctor_fee.toLocaleString()}</td>
            <td class="px-3 py-2 text-right">${row.staff_commission.toLocaleString()}</td>
            <td class="px-3 py-2 text-right">${row.referral.toLocaleString()}</td>
            <td class="px-3 py-2 text-right font-semibold">${row.total.toLocaleString()}</td>
            <td class="px-3 py-2 text-right">${row.count}</td>
        </tr>`).join('') : '<tr><td colspan="6" class="text-center text-slate-500 p-4">ไม่มีข้อมูล</td></tr>';
}

document.getElementById('month').value = new Date().toISOString().slice(0, 7);
document.getElementById('apply').addEventListener('click', load);
if (!api.token()) window.location.href = '/login';
else load();
</script>
@endsection
