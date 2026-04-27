@extends('layouts.app')
@section('title', 'Purchase Orders')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">📦 Purchase Orders (PO)</h1>
            <a href="/accounting/pr" class="ml-auto text-sm text-cyan-700 hover:underline">PR</a>
            <a href="/accounting/disbursements" class="text-sm text-cyan-700 hover:underline">Disbursements</a>
            <a href="/accounting/tax-invoices" class="text-sm text-cyan-700 hover:underline">Tax Invoices</a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6 grid md:grid-cols-3 gap-4">
        <section class="md:col-span-1 bg-white rounded-xl shadow p-4">
            <ul id="list" class="space-y-2 max-h-[700px] overflow-y-auto"></ul>
        </section>
        <section class="md:col-span-2 bg-white rounded-xl shadow p-4">
            <div id="detail" class="text-slate-500">เลือก PO จากด้านซ้าย</div>
        </section>
    </main>
</div>

<dialog id="recv-dialog" class="rounded-xl p-0 w-[640px] max-w-full">
    <form id="recv-form" class="p-4 space-y-3">
        <h3 class="font-semibold text-lg">รับเข้าสินค้า</h3>
        <label class="text-sm block">Warehouse ID
            <input name="warehouse_id" type="number" required class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <table class="min-w-full text-sm">
            <thead class="bg-slate-100"><tr>
                <th class="px-2 py-1 text-left">รายการ</th>
                <th class="px-2 py-1 text-right">สั่ง</th>
                <th class="px-2 py-1 text-right">รับแล้ว</th>
                <th class="px-2 py-1 text-right">รับครั้งนี้</th>
                <th class="px-2 py-1 text-left">Lot</th>
                <th class="px-2 py-1 text-left">Expiry</th>
            </tr></thead>
            <tbody id="recv-items"></tbody>
        </table>
        <div class="flex justify-end gap-2">
            <button type="button" class="dlg-cancel px-3 py-1 rounded bg-slate-100">ยกเลิก</button>
            <button type="submit" class="px-3 py-1 rounded bg-emerald-600 text-white">บันทึกรับ</button>
        </div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
const SC = {
    draft: 'bg-slate-100 text-slate-700',
    sent: 'bg-amber-100 text-amber-800',
    partial_received: 'bg-blue-100 text-blue-800',
    received: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-rose-100 text-rose-800',
};
let currentPo = null;

document.addEventListener('click', e => {
    if (e.target.classList.contains('dlg-cancel')) e.target.closest('dialog').close();
});

async function loadList() {
    const r = await api.call('/accounting/po');
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('list').innerHTML = rows.map(p => `
      <li class="border rounded p-2 cursor-pointer hover:bg-cyan-50" data-id="${p.id}">
        <div class="flex justify-between">
          <div>
            <div class="font-mono text-xs">${p.po_number}</div>
            <div class="text-xs text-slate-500">${p.supplier?.name||'-'} • ${p.order_date}</div>
            <div class="text-sm font-bold">฿${(+p.total).toLocaleString()}</div>
          </div>
          <span class="text-xs px-1.5 py-0.5 rounded h-fit ${SC[p.status]||''}">${p.status}</span>
        </div>
      </li>`).join('') || '<em class="text-slate-400 text-sm">ยังไม่มี PO</em>';
    document.querySelectorAll('#list li').forEach(li => li.addEventListener('click', () => loadDetail(+li.dataset.id)));
}

async function loadDetail(id) {
    const r = await api.call(`/accounting/po/${id}`);
    if (!r.ok) return;
    const p = r.data.data;
    currentPo = p;
    const items = p.items || [];
    document.getElementById('detail').innerHTML = `
      <div class="flex items-start justify-between mb-3">
        <div>
          <div class="font-mono text-lg">${p.po_number}</div>
          <div class="text-sm text-slate-500">${p.supplier?.name||''} • สั่ง ${p.order_date}${p.expected_date?` • คาด ${p.expected_date}`:''}</div>
          ${p.purchase_request?.pr_number ? `<div class="text-xs text-slate-500">จาก PR: ${p.purchase_request.pr_number}</div>` : ''}
        </div>
        <span class="px-2 py-1 rounded text-xs ${SC[p.status]||''}">${p.status}</span>
      </div>
      <table class="min-w-full text-sm mb-3">
        <thead class="bg-slate-100"><tr>
          <th class="px-2 py-1 text-left">รายการ</th>
          <th class="px-2 py-1 text-right">สั่ง</th>
          <th class="px-2 py-1 text-right">รับ</th>
          <th class="px-2 py-1 text-right">ราคา/หน่วย</th>
          <th class="px-2 py-1 text-right">รวม</th>
        </tr></thead>
        <tbody>${items.map(i => `
          <tr class="border-t">
            <td class="px-2 py-1">${i.description}</td>
            <td class="px-2 py-1 text-right">${i.quantity}</td>
            <td class="px-2 py-1 text-right ${i.received_qty>=i.quantity?'text-emerald-700':''}">${i.received_qty}</td>
            <td class="px-2 py-1 text-right">${(+i.unit_cost).toLocaleString()}</td>
            <td class="px-2 py-1 text-right">${(+i.total).toLocaleString()}</td>
          </tr>`).join('')}</tbody>
        <tfoot class="bg-slate-50 font-bold"><tr>
          <td colspan="4" class="px-2 py-1 text-right">Subtotal</td>
          <td class="px-2 py-1 text-right">${(+p.subtotal).toLocaleString()}</td>
        </tr><tr>
          <td colspan="4" class="px-2 py-1 text-right">VAT</td>
          <td class="px-2 py-1 text-right">${(+p.vat_amount).toLocaleString()}</td>
        </tr><tr>
          <td colspan="4" class="px-2 py-1 text-right">Total</td>
          <td class="px-2 py-1 text-right">฿${(+p.total).toLocaleString()}</td>
        </tr></tfoot>
      </table>
      <div class="flex gap-2 flex-wrap">
        ${p.status === 'draft' ? `<button id="btn-send" class="bg-amber-600 text-white px-3 py-1 rounded text-sm">▶︎ ส่ง PO</button>` : ''}
        ${(['sent','partial_received'].includes(p.status)) ? `<button id="btn-receive" class="bg-emerald-600 text-white px-3 py-1 rounded text-sm">📦 รับเข้า</button>` : ''}
        ${(['draft','sent','partial_received'].includes(p.status)) ? `<button id="btn-cancel" class="bg-rose-600 text-white px-3 py-1 rounded text-sm">ยกเลิก</button>` : ''}
      </div>`;

    document.getElementById('btn-send')?.addEventListener('click', async () => {
        const r = await api.call(`/accounting/po/${id}/send`, { method: 'POST', body: JSON.stringify({}) });
        if (!r.ok) return alert((r.data && r.data.message) || 'ส่งไม่ได้');
        await loadList(); loadDetail(id);
    });
    document.getElementById('btn-cancel')?.addEventListener('click', async () => {
        const reason = prompt('เหตุผล:');
        if (!reason) return;
        const r = await api.call(`/accounting/po/${id}/cancel`, { method: 'POST', body: JSON.stringify({ reason }) });
        if (!r.ok) return alert((r.data && r.data.message) || 'ยกเลิกไม่ได้');
        await loadList(); loadDetail(id);
    });
    document.getElementById('btn-receive')?.addEventListener('click', () => openReceive(p));
}

function openReceive(po) {
    document.getElementById('recv-items').innerHTML = (po.items || []).map(i => `
      <tr class="border-t" data-id="${i.id}">
        <td class="px-2 py-1">${i.description}</td>
        <td class="px-2 py-1 text-right">${i.quantity}</td>
        <td class="px-2 py-1 text-right">${i.received_qty}</td>
        <td class="px-2 py-1"><input type="number" min="0" max="${i.quantity - i.received_qty}" value="${i.quantity - i.received_qty}" class="rqty w-20 border rounded px-1 py-0.5 text-right"></td>
        <td class="px-2 py-1"><input type="text" class="rlot w-28 border rounded px-1 py-0.5"></td>
        <td class="px-2 py-1"><input type="date" class="rexp w-36 border rounded px-1 py-0.5"></td>
      </tr>`).join('');
    document.getElementById('recv-dialog').showModal();
}

document.getElementById('recv-form').addEventListener('submit', async e => {
    e.preventDefault();
    const warehouseId = +document.querySelector('[name=warehouse_id]').value;
    const rows = Array.from(document.querySelectorAll('#recv-items tr')).map(tr => ({
        po_item_id: +tr.dataset.id,
        qty: +tr.querySelector('.rqty').value,
        lot_no: tr.querySelector('.rlot').value || null,
        expiry_date: tr.querySelector('.rexp').value || null,
    })).filter(r => r.qty > 0);
    if (rows.length === 0) return alert('ไม่มีรายการรับ');
    const r = await api.call(`/accounting/po/${currentPo.id}/receive`, {
        method: 'POST',
        body: JSON.stringify({ warehouse_id: warehouseId, rows }),
    });
    if (!r.ok) return alert((r.data && r.data.message) || 'รับไม่ได้');
    alert('รับเข้าแล้ว: '+r.data.data.document_no);
    document.getElementById('recv-dialog').close();
    await loadList();
    loadDetail(currentPo.id);
});

(async function () {
    if (!api.token()) return window.location.href = '/login';
    await loadList();
})();
</script>
@endsection
