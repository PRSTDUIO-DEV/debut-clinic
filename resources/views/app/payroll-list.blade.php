@extends('layouts.app')
@section('title', 'Payroll')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">💰 Payroll</h1>
            <a href="/admin/staff" class="text-sm text-cyan-700 hover:underline">พนักงาน</a>
            <button id="btn-preview" class="ml-auto bg-cyan-600 text-white px-3 py-1.5 rounded text-sm">+ Preview รอบใหม่</button>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6">
        <div class="bg-white rounded-xl shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100"><tr>
                    <th class="px-3 py-2 text-left">ปี</th><th>เดือน</th><th>Status</th><th class="text-right">รวม</th>
                    <th>Finalized at</th><th>Paid at</th><th></th>
                </tr></thead>
                <tbody id="rows"></tbody>
            </table>
        </div>
    </main>
</div>

<dialog id="dlg" class="rounded-xl p-0 w-[400px]">
    <form method="dialog" id="form" class="p-4 space-y-2">
        <h3 class="font-bold">+ Preview Payroll</h3>
        <div class="grid grid-cols-2 gap-2">
            <input name="year" required type="number" value="{{ now()->year }}" class="border rounded px-2 py-1.5">
            <input name="month" required type="number" min="1" max="12" value="{{ now()->month }}" class="border rounded px-2 py-1.5">
        </div>
        <div class="flex gap-2 pt-2"><button type="submit" class="bg-cyan-600 text-white px-3 py-1.5 rounded">Preview</button>
            <button type="button" onclick="document.getElementById('dlg').close()" class="px-3 py-1.5">ยกเลิก</button></div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
function fmt(n) { return (+n||0).toLocaleString(undefined, {maximumFractionDigits:2}); }

async function load() {
    const r = await api.call('/admin/payrolls');
    if (!r.ok) return;
    const rows = r.data.data?.data || [];
    document.getElementById('rows').innerHTML = rows.map(p => `
        <tr class="border-t hover:bg-slate-50 cursor-pointer" data-id="${p.id}">
            <td class="px-3 py-2">${p.period_year}</td>
            <td class="text-center">${String(p.period_month).padStart(2,'0')}</td>
            <td class="text-center"><span class="px-2 py-0.5 rounded text-xs ${
                p.status === 'paid' ? 'bg-emerald-100 text-emerald-700' :
                p.status === 'finalized' ? 'bg-cyan-100 text-cyan-700' : 'bg-amber-100 text-amber-700'
            }">${p.status}</span></td>
            <td class="text-right">฿${fmt(p.total_amount)}</td>
            <td class="text-xs">${p.finalized_at ? new Date(p.finalized_at).toLocaleString('th-TH') : '—'}</td>
            <td class="text-xs">${p.paid_at ? new Date(p.paid_at).toLocaleString('th-TH') : '—'}</td>
            <td><a href="/admin/payroll/${p.period_year}/${p.period_month}" class="text-cyan-700 hover:underline text-xs">รายละเอียด →</a></td>
        </tr>`).join('');
}

document.getElementById('btn-preview').onclick = () => document.getElementById('dlg').showModal();

document.getElementById('form').onsubmit = async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const r = await api.call('/admin/payrolls/preview', { method: 'POST', body: data });
    if (r.ok) {
        document.getElementById('dlg').close();
        location.href = `/admin/payroll/${data.year}/${data.month}`;
    } else alert(JSON.stringify(r.data?.errors || r.data));
};

(async function () { if (!api.token()) return location.href = '/login'; await load(); })();
</script>
@endsection
