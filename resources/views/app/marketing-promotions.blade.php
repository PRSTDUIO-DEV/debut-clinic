@extends('layouts.app')
@section('title', 'Promotions')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">🎁 Promotions</h1>
            <a href="/marketing/coupons" class="text-sm text-cyan-700 hover:underline">Coupons</a>
            <a href="/marketing/influencers" class="text-sm text-cyan-700 hover:underline">Influencers</a>
            <button id="btn-create" class="ml-auto bg-cyan-600 text-white px-3 py-1.5 rounded text-sm">+ สร้าง Promotion</button>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <div class="bg-white rounded-xl shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100"><tr>
                    <th class="px-3 py-2 text-left">ชื่อ</th><th>Type</th><th>Rules</th><th>Period</th><th>Active</th><th>Priority</th>
                </tr></thead>
                <tbody id="rows"></tbody>
            </table>
        </div>
    </main>
</div>

<dialog id="dlg" class="rounded-xl p-0 w-[520px] max-w-full">
    <form method="dialog" id="form" class="p-4 space-y-2">
        <h3 class="font-bold">สร้าง Promotion</h3>
        <input name="name" required placeholder="ชื่อ" class="w-full border rounded px-2 py-1.5">
        <select name="type" class="w-full border rounded px-2 py-1.5">
            <option value="percent">% ลดทั้งบิล/หมวด</option>
            <option value="fixed">บาทลด</option>
            <option value="buy_x_get_y">ซื้อ X แถม Y</option>
            <option value="bundle">Bundle ราคาคงที่</option>
        </select>
        <textarea name="rules" required rows="6" class="w-full border rounded px-2 py-1.5 font-mono text-xs"
            placeholder='{"value": 20, "min_amount": 5000, "applicable_category": null}'></textarea>
        <div class="grid grid-cols-2 gap-2">
            <input name="valid_from" required type="date" class="border rounded px-2 py-1.5">
            <input name="valid_to" required type="date" class="border rounded px-2 py-1.5">
            <input name="priority" type="number" placeholder="Priority" value="0" class="border rounded px-2 py-1.5">
            <label class="flex items-center gap-2"><input name="is_active" type="checkbox" checked> Active</label>
        </div>
        <div class="flex gap-2 pt-2"><button type="submit" class="bg-cyan-600 text-white px-3 py-1.5 rounded">บันทึก</button>
            <button type="button" onclick="document.getElementById('dlg').close()" class="px-3 py-1.5">ยกเลิก</button></div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
async function load() {
    const r = await api.call('/marketing/promotions');
    if (!r.ok) return;
    const rows = r.data.data?.data || [];
    document.getElementById('rows').innerHTML = rows.map(p => `
        <tr class="border-t">
            <td class="px-3 py-1.5">${p.name}</td>
            <td class="text-center">${p.type}</td>
            <td class="text-xs font-mono max-w-xs truncate">${JSON.stringify(p.rules)}</td>
            <td class="text-center text-xs">${p.valid_from} → ${p.valid_to}</td>
            <td class="text-center">${p.is_active ? '✅' : '⛔'}</td>
            <td class="text-center">${p.priority}</td>
        </tr>`).join('');
}

document.getElementById('btn-create').onclick = () => document.getElementById('dlg').showModal();

document.getElementById('form').onsubmit = async (e) => {
    e.preventDefault();
    const f = new FormData(e.target);
    let rules = {};
    try { rules = JSON.parse(f.get('rules')); } catch (err) { return alert('Invalid JSON in rules'); }
    const data = {
        name: f.get('name'), type: f.get('type'), rules,
        valid_from: f.get('valid_from'), valid_to: f.get('valid_to'),
        priority: +(f.get('priority') || 0), is_active: f.get('is_active') === 'on',
    };
    const r = await api.call('/marketing/promotions', { method: 'POST', body: data });
    if (r.ok) { document.getElementById('dlg').close(); e.target.reset(); load(); }
    else alert(JSON.stringify(r.data?.errors || r.data));
};

(async function () { if (!api.token()) return location.href = '/login'; await load(); })();
</script>
@endsection
