@extends('layouts.app')
@section('title', 'ใบเบิกของ')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/inventory" class="text-cyan-700 hover:underline">← คลัง</a>
            <h1 class="font-bold">📋 ใบเบิกสินค้า (Stock Requisition)</h1>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6 space-y-4">
        <section class="bg-white rounded-xl shadow p-4">
            <h3 class="font-semibold mb-3">สร้างใบเบิกใหม่</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                <label class="text-sm">จาก
                    <select id="src" class="w-full border rounded px-2 py-1 mt-1"></select>
                </label>
                <label class="text-sm">ไปที่
                    <select id="dst" class="w-full border rounded px-2 py-1 mt-1"></select>
                </label>
                <label class="text-sm">หมายเหตุ
                    <input id="notes" class="w-full border rounded px-2 py-1 mt-1">
                </label>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100"><tr>
                    <th class="px-2 py-1 text-left">สินค้า</th>
                    <th class="px-2 py-1 text-right">ขอ</th>
                    <th></th>
                </tr></thead>
                <tbody id="items"></tbody>
            </table>
            <button id="add" class="mt-2 text-sm bg-slate-100 hover:bg-slate-200 px-3 py-1 rounded">+ เพิ่ม</button>
            <div class="mt-3 flex justify-end">
                <button id="save" class="bg-amber-600 text-white px-4 py-2 rounded">ส่งใบเบิก</button>
            </div>
        </section>

        <section class="bg-white rounded-xl shadow p-4">
            <h3 class="font-semibold mb-3">ใบเบิกล่าสุด</h3>
            <div id="list" class="text-sm space-y-3"></div>
        </section>
    </main>
</div>
@endsection

@section('scripts')
<script>
const STATUS_LABEL = { pending: 'รออนุมัติ', approved: 'อนุมัติแล้ว', rejected: 'ปฏิเสธ', completed: 'โอนเสร็จ' };
const STATUS_COLOR = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-cyan-100 text-cyan-800',
    completed: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-rose-100 text-rose-800',
};
let products = [], warehouses = [];

function rowHtml() {
    return `
      <tr class="border-t">
        <td class="px-2 py-1"><select class="prod border rounded px-2 py-1 w-72">${products.map(p => `<option value="${p.id}">${p.sku} — ${p.name}</option>`).join('')}</select></td>
        <td class="px-2 py-1"><input type="number" class="qty w-20 border rounded px-2 py-1 text-right" value="1" min="1"></td>
        <td class="px-2 py-1"><button class="del text-rose-600 text-sm">ลบ</button></td>
      </tr>`;
}

document.getElementById('add').addEventListener('click', () => {
    document.getElementById('items').insertAdjacentHTML('beforeend', rowHtml());
});
document.addEventListener('click', e => {
    if (e.target.matches('.del')) e.target.closest('tr').remove();
});

document.getElementById('save').addEventListener('click', async () => {
    const items = Array.from(document.querySelectorAll('#items tr')).map(tr => ({
        product_id: +tr.querySelector('.prod').value,
        requested_qty: +tr.querySelector('.qty').value,
    }));
    if (items.length === 0) return alert('เพิ่มรายการก่อน');
    const r = await api.call('/inventory/requisitions', {
        method: 'POST',
        body: JSON.stringify({
            source_warehouse_id: +document.getElementById('src').value,
            dest_warehouse_id: +document.getElementById('dst').value,
            notes: document.getElementById('notes').value || null,
            items,
        }),
    });
    if (!r.ok) return alert((r.data && r.data.message) || 'บันทึกไม่ได้');
    alert('สร้างใบเบิก: ' + r.data.data.document_no);
    loadList();
});

async function approveReq(id) {
    if (!confirm('อนุมัติและโอนสต็อก?')) return;
    const r = await api.call(`/inventory/requisitions/${id}/approve`, { method: 'POST', body: JSON.stringify({}) });
    if (!r.ok) return alert((r.data && r.data.message) || 'อนุมัติไม่ได้');
    alert('อนุมัติเสร็จ');
    loadList();
}

async function rejectReq(id) {
    if (!confirm('ปฏิเสธใบเบิกนี้?')) return;
    const r = await api.call(`/inventory/requisitions/${id}/reject`, { method: 'POST', body: JSON.stringify({}) });
    if (!r.ok) return alert((r.data && r.data.message) || 'ปฏิเสธไม่ได้');
    loadList();
}

window.approveReq = approveReq;
window.rejectReq = rejectReq;

async function loadList() {
    const r = await api.call('/inventory/requisitions');
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('list').innerHTML = rows.map(req => `
      <div class="border rounded-lg p-3">
        <div class="flex justify-between items-center mb-2">
          <div>
            <div class="font-mono font-semibold">${req.document_no}</div>
            <div class="text-xs text-slate-500">${req.source} → ${req.destination} • โดย ${req.requested_by||'-'}</div>
          </div>
          <div class="flex items-center gap-2">
            <span class="px-2 py-1 rounded text-xs ${STATUS_COLOR[req.status]||''}">${STATUS_LABEL[req.status]||req.status}</span>
            ${req.status === 'pending' ? `<button onclick="approveReq(${req.id})" class="bg-emerald-600 text-white text-xs px-2 py-1 rounded">อนุมัติ</button>
            <button onclick="rejectReq(${req.id})" class="bg-rose-600 text-white text-xs px-2 py-1 rounded">ปฏิเสธ</button>` : ''}
          </div>
        </div>
        <table class="min-w-full text-xs">
          <thead class="bg-slate-50"><tr><th class="px-2 py-1 text-left">สินค้า</th><th class="px-2 py-1 text-right">ขอ</th><th class="px-2 py-1 text-right">อนุมัติ</th></tr></thead>
          <tbody>${(req.items||[]).map(i => `<tr><td class="px-2 py-1">${i.product_name}</td><td class="px-2 py-1 text-right">${i.requested_qty}</td><td class="px-2 py-1 text-right">${i.approved_qty}</td></tr>`).join('')}</tbody>
        </table>
      </div>`).join('') || '<em class="text-slate-500">ยังไม่มีใบเบิก</em>';
}

async function bootstrap() {
    if (!api.token()) return window.location.href = '/login';
    const [pr, wh] = await Promise.all([
        api.call('/inventory/products?per_page=200'),
        api.call('/inventory/warehouses'),
    ]);
    if (!pr.ok || !wh.ok) return;
    products = pr.data.data;
    warehouses = wh.data.data;
    const opts = warehouses.map(w => `<option value="${w.id}">${w.name} (${w.type})</option>`).join('');
    document.getElementById('src').innerHTML = opts;
    document.getElementById('dst').innerHTML = opts;
    const main = warehouses.find(w => w.type === 'main');
    const floor = warehouses.find(w => w.type === 'floor');
    if (main) document.getElementById('src').value = main.id;
    if (floor) document.getElementById('dst').value = floor.id;
    document.getElementById('items').insertAdjacentHTML('beforeend', rowHtml());
    loadList();
}
bootstrap();
</script>
@endsection
