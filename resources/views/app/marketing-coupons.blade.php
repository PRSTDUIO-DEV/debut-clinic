@extends('layouts.app')
@section('title', 'Coupons')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">🎟️ Coupons</h1>
            <a href="/marketing/promotions" class="text-sm text-cyan-700 hover:underline ml-2">Promotions</a>
            <a href="/marketing/influencers" class="text-sm text-cyan-700 hover:underline">Influencers</a>
            <a href="/marketing/reviews" class="text-sm text-cyan-700 hover:underline">Reviews</a>
            <button id="btn-create" class="ml-auto bg-cyan-600 text-white px-3 py-1.5 rounded text-sm">+ สร้างคูปอง</button>
            <button id="btn-bulk" class="bg-violet-600 text-white px-3 py-1.5 rounded text-sm">+ Bulk generate</button>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <div class="bg-white rounded-xl shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr><th class="px-3 py-2 text-left">Code</th><th class="text-left">ชื่อ</th><th>Type</th><th>Value</th>
                        <th>ใช้ไป/รวม</th><th>Period</th><th>Active</th></tr>
                </thead>
                <tbody id="rows"></tbody>
            </table>
        </div>
    </main>
</div>

<dialog id="dlg-create" class="rounded-xl p-0 w-[420px] max-w-full">
    <form method="dialog" id="form-create" class="p-4 space-y-2">
        <h3 class="font-bold">สร้างคูปอง</h3>
        <input name="name" required placeholder="ชื่อคูปอง" class="w-full border rounded px-2 py-1.5">
        <input name="code" placeholder="โค้ด (เว้นว่าง=สุ่ม)" class="w-full border rounded px-2 py-1.5 font-mono uppercase">
        <div class="grid grid-cols-2 gap-2">
            <select name="type" class="border rounded px-2 py-1.5"><option value="percent">% ลด</option><option value="fixed">บาทลด</option></select>
            <input name="value" required type="number" step="0.01" placeholder="มูลค่า" class="border rounded px-2 py-1.5">
            <input name="min_amount" type="number" step="0.01" placeholder="ขั้นต่ำ" class="border rounded px-2 py-1.5">
            <input name="max_per_customer" type="number" placeholder="จำกัด/ลูกค้า" value="1" class="border rounded px-2 py-1.5">
            <input name="valid_from" required type="date" class="border rounded px-2 py-1.5">
            <input name="valid_to" required type="date" class="border rounded px-2 py-1.5">
        </div>
        <div class="flex gap-2 pt-2"><button type="submit" class="bg-cyan-600 text-white px-3 py-1.5 rounded">บันทึก</button>
            <button type="button" onclick="document.getElementById('dlg-create').close()" class="px-3 py-1.5">ยกเลิก</button></div>
    </form>
</dialog>

<dialog id="dlg-bulk" class="rounded-xl p-0 w-[420px] max-w-full">
    <form method="dialog" id="form-bulk" class="p-4 space-y-2">
        <h3 class="font-bold">Bulk generate</h3>
        <input name="name" required placeholder="ชื่อ template" class="w-full border rounded px-2 py-1.5">
        <input name="prefix" placeholder="prefix (เช่น BTX)" class="w-full border rounded px-2 py-1.5 uppercase">
        <input name="count" required type="number" min="1" max="200" value="10" placeholder="จำนวน" class="w-full border rounded px-2 py-1.5">
        <div class="grid grid-cols-2 gap-2">
            <select name="type" class="border rounded px-2 py-1.5"><option value="percent">% ลด</option><option value="fixed">บาทลด</option></select>
            <input name="value" required type="number" step="0.01" placeholder="มูลค่า" class="border rounded px-2 py-1.5">
            <input name="valid_from" required type="date" class="border rounded px-2 py-1.5">
            <input name="valid_to" required type="date" class="border rounded px-2 py-1.5">
        </div>
        <div class="flex gap-2 pt-2"><button type="submit" class="bg-violet-600 text-white px-3 py-1.5 rounded">Generate</button>
            <button type="button" onclick="document.getElementById('dlg-bulk').close()" class="px-3 py-1.5">ยกเลิก</button></div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
async function load() {
    const r = await api.call('/marketing/coupons');
    if (!r.ok) return;
    const rows = r.data.data?.data || [];
    document.getElementById('rows').innerHTML = rows.map(c => `
        <tr class="border-t">
            <td class="px-3 py-1.5 font-mono">${c.code}</td>
            <td>${c.name}</td>
            <td class="text-center">${c.type}</td>
            <td class="text-right">${c.type === 'percent' ? c.value + '%' : '฿' + (+c.value).toLocaleString()}</td>
            <td class="text-center">${c.used_count}/${c.max_uses ?? '∞'}</td>
            <td class="text-center text-xs">${c.valid_from} → ${c.valid_to}</td>
            <td class="text-center">${c.is_active ? '✅' : '⛔'}</td>
        </tr>`).join('');
}

document.getElementById('btn-create').onclick = () => document.getElementById('dlg-create').showModal();
document.getElementById('btn-bulk').onclick = () => document.getElementById('dlg-bulk').showModal();

document.getElementById('form-create').onsubmit = async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    if (!data.code) delete data.code;
    if (!data.min_amount) delete data.min_amount;
    const r = await api.call('/marketing/coupons', { method: 'POST', body: data });
    if (r.ok) { document.getElementById('dlg-create').close(); e.target.reset(); load(); }
    else alert(JSON.stringify(r.data?.errors || r.data));
};

document.getElementById('form-bulk').onsubmit = async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const r = await api.call('/marketing/coupons/generate', { method: 'POST', body: data });
    if (r.ok) { document.getElementById('dlg-bulk').close(); e.target.reset(); alert('สร้าง '+(r.data.data?.length||0)+' คูปอง'); load(); }
    else alert(JSON.stringify(r.data?.errors || r.data));
};

(async function () { if (!api.token()) return location.href = '/login'; await load(); })();
</script>
@endsection
