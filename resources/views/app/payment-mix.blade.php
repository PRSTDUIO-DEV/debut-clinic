@extends('layouts.app')
@section('title', 'รายงาน Payment Mix')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">รายงาน Payment Mix</h1>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6 space-y-4">
        <div class="bg-white rounded-xl shadow p-4 flex flex-wrap gap-3 items-center">
            <label class="text-sm">ตั้งแต่ <input id="from" type="date" class="border rounded px-2 py-1.5 text-sm"></label>
            <label class="text-sm">ถึง <input id="to" type="date" class="border rounded px-2 py-1.5 text-sm"></label>
            <button id="apply" class="bg-cyan-600 hover:bg-cyan-700 text-white px-3 py-1.5 rounded text-sm font-semibold">ดูรายงาน</button>
            <span id="grand" class="ml-auto text-sm font-semibold text-slate-700"></span>
        </div>

        <div class="grid lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl shadow p-5">
                <h3 class="font-semibold mb-3">สัดส่วนวิธีชำระเงิน</h3>
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50"><tr>
                        <th class="text-left px-3 py-2">วิธีชำระ</th>
                        <th class="text-right px-3 py-2">จำนวน</th>
                        <th class="text-right px-3 py-2">ยอดรวม</th>
                        <th class="text-right px-3 py-2">%</th>
                    </tr></thead>
                    <tbody id="method-rows"><tr><td colspan="4" class="text-center text-slate-500 p-4">กดดูรายงาน</td></tr></tbody>
                </table>
            </div>

            <div class="bg-white rounded-xl shadow p-5">
                <h3 class="font-semibold mb-3">บัตรเครดิต แยกตามธนาคาร</h3>
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50"><tr>
                        <th class="text-left px-3 py-2">ธนาคาร</th>
                        <th class="text-right px-3 py-2">ยอดรวม</th>
                        <th class="text-right px-3 py-2">MDR</th>
                        <th class="text-right px-3 py-2">สุทธิ</th>
                    </tr></thead>
                    <tbody id="bank-rows"><tr><td colspan="4" class="text-center text-slate-500 p-4">—</td></tr></tbody>
                </table>
            </div>
        </div>
    </main>
</div>
@endsection

@section('scripts')
<script>
const METHOD_LABEL = { cash: 'เงินสด', credit_card: 'บัตรเครดิต', transfer: 'โอน', member_credit: 'Member', coupon: 'คูปอง' };
const METHOD_COLOR = {
    cash: 'bg-emerald-100 text-emerald-800',
    credit_card: 'bg-blue-100 text-blue-800',
    transfer: 'bg-cyan-100 text-cyan-800',
    member_credit: 'bg-purple-100 text-purple-800',
    coupon: 'bg-amber-100 text-amber-800',
};

async function load() {
    const f = document.getElementById('from').value;
    const t = document.getElementById('to').value;
    const params = new URLSearchParams();
    if (f) params.set('date_from', f);
    if (t) params.set('date_to', t);

    const r = await api.call('/reports/payment-mix?' + params.toString());
    if (!r.ok) {
        document.getElementById('method-rows').innerHTML = '<tr><td colspan="4" class="text-red-600 p-4">โหลดไม่สำเร็จ</td></tr>';
        return;
    }
    const d = r.data.data;
    document.getElementById('grand').textContent = 'รวมทั้งสิ้น: ' + d.grand_total.toLocaleString();
    document.getElementById('method-rows').innerHTML = d.by_method.length ? d.by_method.map(m => `
        <tr class="border-b">
            <td class="px-3 py-2"><span class="px-2 py-1 rounded text-xs font-semibold ${METHOD_COLOR[m.method] || ''}">${METHOD_LABEL[m.method] || m.method}</span></td>
            <td class="px-3 py-2 text-right">${m.count}</td>
            <td class="px-3 py-2 text-right">${m.total.toLocaleString()}</td>
            <td class="px-3 py-2 text-right">${m.pct}%</td>
        </tr>`).join('') : '<tr><td colspan="4" class="text-center text-slate-500 p-4">ไม่มีข้อมูล</td></tr>';
    document.getElementById('bank-rows').innerHTML = d.credit_card_by_bank.length ? d.credit_card_by_bank.map(b => `
        <tr class="border-b">
            <td class="px-3 py-2">${b.bank_name || '—'}</td>
            <td class="px-3 py-2 text-right">${b.total.toLocaleString()}</td>
            <td class="px-3 py-2 text-right text-red-600">-${b.mdr_total.toLocaleString()}</td>
            <td class="px-3 py-2 text-right font-semibold">${b.net.toLocaleString()}</td>
        </tr>`).join('') : '<tr><td colspan="4" class="text-center text-slate-500 p-4">—</td></tr>';
}

const today = new Date();
const first = new Date(today.getFullYear(), today.getMonth() - 1, 1);
const last = new Date(today.getFullYear(), today.getMonth() + 1, 0);
document.getElementById('from').value = first.toISOString().slice(0, 10);
document.getElementById('to').value = last.toISOString().slice(0, 10);
document.getElementById('apply').addEventListener('click', load);

if (!api.token()) window.location.href = '/login';
else load();
</script>
@endsection
