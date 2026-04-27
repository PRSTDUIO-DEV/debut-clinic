@extends('layouts.app')
@section('title', 'รับเข้าสินค้า')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/inventory" class="text-cyan-700 hover:underline">← คลัง</a>
            <h1 class="font-bold">📥 รับเข้าสินค้า (Goods Receiving)</h1>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-6 space-y-4">
        <section class="bg-white rounded-xl shadow p-4">
            <h3 class="font-semibold mb-3">สร้างใบรับเข้าใหม่</h3>
            <form id="form" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <label class="text-sm">คลังปลายทาง
                    <select name="warehouse_id" required class="w-full border rounded px-2 py-1 mt-1"></select>
                </label>
                <label class="text-sm">ผู้ขาย (optional)
                    <select name="supplier_id" class="w-full border rounded px-2 py-1 mt-1">
                        <option value="">— ไม่ระบุ —</option>
                    </select>
                </label>
                <label class="text-sm">วันที่รับ
                    <input type="date" name="receive_date" required class="w-full border rounded px-2 py-1 mt-1" value="{{ now()->toDateString() }}">
                </label>
                <label class="text-sm md:col-span-3">หมายเหตุ
                    <input name="notes" class="w-full border rounded px-2 py-1 mt-1">
                </label>
            </form>

            <div class="mt-4">
                <h4 class="font-medium text-sm mb-2">รายการสินค้า</h4>
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100"><tr>
                        <th class="px-2 py-1 text-left">สินค้า</th>
                        <th class="px-2 py-1 text-right">จำนวน</th>
                        <th class="px-2 py-1 text-right">ทุน/หน่วย</th>
                        <th class="px-2 py-1 text-left">Lot</th>
                        <th class="px-2 py-1 text-left">หมดอายุ</th>
                        <th class="px-2 py-1 text-right">รวม</th>
                        <th></th>
                    </tr></thead>
                    <tbody id="items"></tbody>
                </table>
                <button id="add-item" class="mt-2 text-sm bg-slate-100 hover:bg-slate-200 px-3 py-1 rounded">+ เพิ่มรายการ</button>
            </div>

            <div class="mt-4 flex justify-between items-center">
                <div>
                    <label class="text-sm">VAT
                        <input id="vat" type="number" step="0.01" min="0" value="0" class="w-32 border rounded px-2 py-1">
                    </label>
                </div>
                <div class="text-right">
                    <div class="text-xs text-slate-500">ยอดรวม</div>
                    <div class="text-xl font-bold" id="total">0.00</div>
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <button id="save" class="bg-cyan-600 text-white px-4 py-2 rounded">บันทึกรับเข้า</button>
            </div>
        </section>

        <section class="bg-white rounded-xl shadow p-4">
            <h3 class="font-semibold mb-3">ใบรับเข้าล่าสุด</h3>
            <div id="recent" class="text-sm"></div>
        </section>
    </main>
</div>
@endsection

@section('scripts')
<script>
let products = [], warehouses = [], suppliers = [];

function rowHtml(idx) {
    return `
      <tr data-idx="${idx}" class="border-t">
        <td class="px-2 py-1"><select class="product w-64 border rounded px-2 py-1">${products.map(p => `<option value="${p.id}" data-cost="${p.cost_price}">${p.sku} — ${p.name}</option>`).join('')}</select></td>
        <td class="px-2 py-1"><input type="number" class="qty w-20 border rounded px-2 py-1 text-right" value="1" min="1"></td>
        <td class="px-2 py-1"><input type="number" class="cost w-24 border rounded px-2 py-1 text-right" value="0" step="0.01" min="0"></td>
        <td class="px-2 py-1"><input type="text" class="lot w-32 border rounded px-2 py-1"></td>
        <td class="px-2 py-1"><input type="date" class="exp w-36 border rounded px-2 py-1"></td>
        <td class="px-2 py-1 text-right total">0.00</td>
        <td class="px-2 py-1"><button class="del text-rose-600 text-sm">ลบ</button></td>
      </tr>`;
}

function recalc() {
    let total = 0;
    document.querySelectorAll('#items tr').forEach(tr => {
        const q = +tr.querySelector('.qty').value || 0;
        const c = +tr.querySelector('.cost').value || 0;
        const sub = q * c;
        tr.querySelector('.total').textContent = sub.toFixed(2);
        total += sub;
    });
    const vat = +document.getElementById('vat').value || 0;
    document.getElementById('total').textContent = (total + vat).toFixed(2);
}

document.getElementById('add-item').addEventListener('click', e => {
    e.preventDefault();
    const idx = document.querySelectorAll('#items tr').length;
    document.getElementById('items').insertAdjacentHTML('beforeend', rowHtml(idx));
    recalc();
});

document.addEventListener('input', e => {
    if (e.target.matches('.qty, .cost')) recalc();
    if (e.target.matches('.product')) {
        const cost = e.target.selectedOptions[0]?.dataset.cost || 0;
        e.target.closest('tr').querySelector('.cost').value = cost;
        recalc();
    }
    if (e.target.id === 'vat') recalc();
});

document.addEventListener('click', e => {
    if (e.target.matches('.del')) {
        e.target.closest('tr').remove();
        recalc();
    }
});

document.getElementById('save').addEventListener('click', async () => {
    const f = document.getElementById('form');
    const items = Array.from(document.querySelectorAll('#items tr')).map(tr => ({
        product_id: +tr.querySelector('.product').value,
        quantity: +tr.querySelector('.qty').value,
        unit_cost: +tr.querySelector('.cost').value,
        lot_no: tr.querySelector('.lot').value || null,
        expiry_date: tr.querySelector('.exp').value || null,
    }));
    if (items.length === 0) return alert('ต้องมีรายการสินค้าอย่างน้อย 1');

    const body = {
        warehouse_id: +f.warehouse_id.value,
        supplier_id: f.supplier_id.value ? +f.supplier_id.value : null,
        receive_date: f.receive_date.value,
        vat_amount: +document.getElementById('vat').value || 0,
        notes: f.notes.value || null,
        items,
    };
    const r = await api.call('/inventory/receivings', { method: 'POST', body: JSON.stringify(body) });
    if (!r.ok) return alert((r.data && r.data.message) || 'บันทึกไม่ได้');
    alert('บันทึกสำเร็จ: ' + r.data.data.document_no);
    location.reload();
});

async function loadRecent() {
    const r = await api.call('/inventory/receivings');
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('recent').innerHTML = `
      <table class="min-w-full">
        <thead class="bg-slate-100"><tr>
          <th class="px-2 py-1 text-left">เลขที่</th>
          <th class="px-2 py-1 text-left">วันที่</th>
          <th class="px-2 py-1 text-left">คลัง</th>
          <th class="px-2 py-1 text-left">ผู้ขาย</th>
          <th class="px-2 py-1 text-right">ยอดรวม</th>
          <th class="px-2 py-1 text-right">รายการ</th>
        </tr></thead>
        <tbody>${rows.map(g => `
          <tr class="border-t">
            <td class="px-2 py-1 font-mono">${g.document_no}</td>
            <td class="px-2 py-1">${g.receive_date}</td>
            <td class="px-2 py-1">${g.warehouse}</td>
            <td class="px-2 py-1 text-slate-600">${g.supplier || '-'}</td>
            <td class="px-2 py-1 text-right">${(+g.total_amount).toFixed(2)}</td>
            <td class="px-2 py-1 text-right">${g.item_count}</td>
          </tr>`).join('')}</tbody>
      </table>`;
}

async function bootstrap() {
    if (!api.token()) return window.location.href = '/login';
    const [pr, wh] = await Promise.all([
        api.call('/inventory/products?per_page=200'),
        api.call('/inventory/warehouses'),
    ]);
    if (!pr.ok || !wh.ok) return alert('โหลดไม่ได้');
    products = pr.data.data;
    warehouses = wh.data.data;
    const wsel = document.querySelector('[name=warehouse_id]');
    wsel.innerHTML = warehouses.map(w => `<option value="${w.id}">${w.name} (${w.type})</option>`).join('');
    const main = warehouses.find(w => w.type === 'main');
    if (main) wsel.value = main.id;

    document.getElementById('items').insertAdjacentHTML('beforeend', rowHtml(0));
    recalc();
    loadRecent();
}
bootstrap();
</script>
@endsection
