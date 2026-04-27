@extends('layouts.app')
@section('title', 'Purchase Requests')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">📋 Purchase Requests (PR)</h1>
            <a href="/accounting/po" class="ml-auto text-sm text-cyan-700 hover:underline">PO</a>
            <a href="/accounting/disbursements" class="text-sm text-cyan-700 hover:underline">Disbursements</a>
            <a href="/accounting/tax-invoices" class="text-sm text-cyan-700 hover:underline">Tax Invoices</a>
            <a href="/accounting/ledger" class="text-sm text-cyan-700 hover:underline">Ledger</a>
            <a href="/accounting/coa" class="text-sm text-cyan-700 hover:underline">CoA</a>
            <button id="btn-new" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">+ PR ใหม่</button>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6 grid md:grid-cols-3 gap-4">
        <section class="md:col-span-1 bg-white rounded-xl shadow p-4">
            <div class="flex gap-1 flex-wrap mb-3" id="filter">
                <button data-st="" class="text-xs px-2 py-1 rounded bg-cyan-600 text-white">ทั้งหมด</button>
                <button data-st="draft" class="text-xs px-2 py-1 rounded bg-white border">draft</button>
                <button data-st="submitted" class="text-xs px-2 py-1 rounded bg-white border">submitted</button>
                <button data-st="approved" class="text-xs px-2 py-1 rounded bg-white border">approved</button>
                <button data-st="converted" class="text-xs px-2 py-1 rounded bg-white border">converted</button>
                <button data-st="rejected" class="text-xs px-2 py-1 rounded bg-white border">rejected</button>
            </div>
            <ul id="list" class="space-y-2 max-h-[600px] overflow-y-auto"></ul>
        </section>
        <section class="md:col-span-2 bg-white rounded-xl shadow p-4">
            <div id="detail" class="text-slate-500">เลือก PR จากด้านซ้าย</div>
        </section>
    </main>
</div>

<dialog id="form-dialog" class="rounded-xl p-0 w-[640px] max-w-full">
    <form id="form" class="p-4 space-y-3">
        <h3 class="font-semibold text-lg">PR ใหม่</h3>
        <label class="text-sm block">วันที่
            <input name="request_date" type="date" required class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <label class="text-sm block">หมายเหตุ
            <input name="notes" class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <div>
            <div class="text-sm font-medium mb-1">รายการ</div>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100"><tr>
                    <th class="px-2 py-1 text-left">รายละเอียด</th>
                    <th class="px-2 py-1 text-right">จำนวน</th>
                    <th class="px-2 py-1 text-right">ราคาประมาณ</th>
                    <th></th>
                </tr></thead>
                <tbody id="items"></tbody>
            </table>
            <button type="button" id="add-item" class="mt-2 text-sm bg-slate-100 px-2 py-1 rounded">+ เพิ่ม</button>
        </div>
        <div class="flex justify-end gap-2">
            <button type="button" class="dlg-cancel px-3 py-1 rounded bg-slate-100">ยกเลิก</button>
            <button type="submit" class="px-3 py-1 rounded bg-cyan-600 text-white">สร้าง PR</button>
        </div>
    </form>
</dialog>

<dialog id="convert-dialog" class="rounded-xl p-0 w-[480px] max-w-full">
    <form id="convert-form" class="p-4 space-y-3">
        <h3 class="font-semibold text-lg">แปลง PR → PO</h3>
        <label class="text-sm block">Supplier
            <select name="supplier_id" required class="w-full border rounded px-2 py-1 mt-1"></select>
        </label>
        <label class="text-sm block">วันที่คาดว่าจะได้รับ
            <input name="expected_date" type="date" class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <label class="text-sm block">VAT %
            <input name="vat_percent" type="number" step="0.01" min="0" max="30" value="7" class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <div class="flex justify-end gap-2">
            <button type="button" class="dlg-cancel px-3 py-1 rounded bg-slate-100">ยกเลิก</button>
            <button type="submit" class="px-3 py-1 rounded bg-cyan-600 text-white">สร้าง PO</button>
        </div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
const SC = {
    draft: 'bg-slate-100 text-slate-700',
    submitted: 'bg-amber-100 text-amber-800',
    approved: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-rose-100 text-rose-800',
    converted: 'bg-cyan-100 text-cyan-800',
};
let currentStatus = '';
let currentPrId = null;
let suppliers = [];

function rowHtml() {
    return `
      <tr class="border-t">
        <td class="px-2 py-1"><input class="desc w-72 border rounded px-2 py-1" placeholder="รายการ"></td>
        <td class="px-2 py-1"><input type="number" min="1" value="1" class="qty w-20 border rounded px-2 py-1 text-right"></td>
        <td class="px-2 py-1"><input type="number" step="0.01" min="0" value="0" class="cost w-24 border rounded px-2 py-1 text-right"></td>
        <td class="px-2 py-1"><button type="button" class="del text-rose-600 text-sm">ลบ</button></td>
      </tr>`;
}

document.addEventListener('click', e => {
    if (e.target.matches('.del')) e.target.closest('tr').remove();
    if (e.target.classList.contains('dlg-cancel')) e.target.closest('dialog').close();
});

document.getElementById('add-item').addEventListener('click', () => {
    document.getElementById('items').insertAdjacentHTML('beforeend', rowHtml());
});

async function loadList() {
    const params = new URLSearchParams();
    if (currentStatus) params.set('status', currentStatus);
    const r = await api.call('/accounting/pr?'+params.toString());
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('list').innerHTML = rows.map(p => `
      <li class="border rounded p-2 cursor-pointer hover:bg-cyan-50" data-id="${p.id}">
        <div class="flex justify-between">
          <div>
            <div class="font-mono text-xs">${p.pr_number}</div>
            <div class="text-xs text-slate-500">${p.request_date} • ${p.requester?.name||'-'}</div>
            <div class="text-sm font-bold">฿${(+p.estimated_total).toLocaleString()}</div>
          </div>
          <span class="text-xs px-1.5 py-0.5 rounded h-fit ${SC[p.status]||''}">${p.status}</span>
        </div>
      </li>`).join('') || '<em class="text-slate-400 text-sm">ไม่มี PR</em>';
    document.querySelectorAll('#list li').forEach(li => li.addEventListener('click', () => loadDetail(+li.dataset.id)));
}

async function loadDetail(id) {
    currentPrId = id;
    const r = await api.call(`/accounting/pr/${id}`);
    if (!r.ok) return;
    const p = r.data.data;
    const items = p.items || [];
    document.getElementById('detail').innerHTML = `
      <div class="flex items-start justify-between mb-3">
        <div>
          <div class="font-mono text-lg">${p.pr_number}</div>
          <div class="text-sm text-slate-500">วันที่: ${p.request_date} • ผู้ขอ: ${p.requester?.name||'-'}${p.approver?` • อนุมัติโดย: ${p.approver.name}`:''}</div>
          ${p.notes?`<div class="text-sm text-slate-600 mt-1">${p.notes}</div>`:''}
          ${p.rejection_reason?`<div class="text-sm text-rose-600 mt-1">เหตุผลปฏิเสธ: ${p.rejection_reason}</div>`:''}
        </div>
        <span class="px-2 py-1 rounded text-xs ${SC[p.status]||''}">${p.status}</span>
      </div>
      <table class="min-w-full text-sm mb-3">
        <thead class="bg-slate-100"><tr>
          <th class="px-2 py-1 text-left">รายละเอียด</th>
          <th class="px-2 py-1 text-right">จำนวน</th>
          <th class="px-2 py-1 text-right">ราคาประมาณ</th>
          <th class="px-2 py-1 text-right">รวม</th>
        </tr></thead>
        <tbody>${items.map(i => `
          <tr class="border-t">
            <td class="px-2 py-1">${i.description}${i.product?.name?` <span class="text-xs text-slate-500">(${i.product.name})</span>`:''}</td>
            <td class="px-2 py-1 text-right">${i.quantity}</td>
            <td class="px-2 py-1 text-right">${(+i.estimated_cost).toLocaleString()}</td>
            <td class="px-2 py-1 text-right">${(+(i.quantity * i.estimated_cost)).toLocaleString()}</td>
          </tr>`).join('')}</tbody>
        <tfoot class="bg-slate-50 font-bold"><tr>
          <td colspan="3" class="px-2 py-1 text-right">รวม</td>
          <td class="px-2 py-1 text-right">฿${(+p.estimated_total).toLocaleString()}</td>
        </tr></tfoot>
      </table>
      <div class="flex gap-2 flex-wrap">
        ${p.status === 'draft' ? `<button id="btn-submit" class="bg-amber-600 text-white px-3 py-1 rounded text-sm">ส่งอนุมัติ</button>` : ''}
        ${(['draft','submitted'].includes(p.status)) ? `
          <button id="btn-approve" class="bg-emerald-600 text-white px-3 py-1 rounded text-sm">อนุมัติ</button>
          <button id="btn-reject" class="bg-rose-600 text-white px-3 py-1 rounded text-sm">ปฏิเสธ</button>
        ` : ''}
        ${p.status === 'approved' ? `<button id="btn-convert" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">→ สร้าง PO</button>` : ''}
      </div>`;

    document.getElementById('btn-submit')?.addEventListener('click', async () => {
        const r = await api.call(`/accounting/pr/${id}/submit`, { method: 'POST', body: JSON.stringify({}) });
        if (!r.ok) return alert((r.data && r.data.message) || 'ส่งไม่ได้');
        await loadList(); loadDetail(id);
    });
    document.getElementById('btn-approve')?.addEventListener('click', async () => {
        const r = await api.call(`/accounting/pr/${id}/approve`, { method: 'POST', body: JSON.stringify({}) });
        if (!r.ok) return alert((r.data && r.data.message) || 'อนุมัติไม่ได้');
        await loadList(); loadDetail(id);
    });
    document.getElementById('btn-reject')?.addEventListener('click', async () => {
        const reason = prompt('เหตุผลที่ปฏิเสธ:');
        if (!reason) return;
        const r = await api.call(`/accounting/pr/${id}/reject`, { method: 'POST', body: JSON.stringify({ reason }) });
        if (!r.ok) return alert((r.data && r.data.message) || 'ปฏิเสธไม่ได้');
        await loadList(); loadDetail(id);
    });
    document.getElementById('btn-convert')?.addEventListener('click', async () => {
        if (suppliers.length === 0) {
            // Suppliers endpoint may not be exposed; use lookups workaround — just ask
            const r = await api.call('/lookups/customer-groups'); // placeholder
            // Fall back: prompt for supplier id
        }
        const supplierId = +prompt('Supplier ID (ดูได้จาก /admin/suppliers ในอนาคต):');
        if (!supplierId) return;
        const r = await api.call(`/accounting/pr/${id}/convert`, {
            method: 'POST',
            body: JSON.stringify({ supplier_id: supplierId, vat_percent: 7 }),
        });
        if (!r.ok) return alert((r.data && r.data.message) || 'แปลงไม่ได้');
        alert('สร้าง PO แล้ว: '+r.data.data.po_number+' → ดูที่ /accounting/po');
        await loadList(); loadDetail(id);
    });
}

document.getElementById('btn-new').addEventListener('click', () => {
    const f = document.getElementById('form');
    f.reset();
    f.request_date.value = new Date().toISOString().slice(0, 10);
    document.getElementById('items').innerHTML = rowHtml();
    document.getElementById('form-dialog').showModal();
});

document.getElementById('form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const items = Array.from(document.querySelectorAll('#items tr')).map(tr => ({
        product_id: null,
        description: tr.querySelector('.desc').value,
        quantity: +tr.querySelector('.qty').value,
        estimated_cost: +tr.querySelector('.cost').value,
    })).filter(i => i.description && i.quantity > 0);
    if (items.length === 0) return alert('ต้องมีรายการอย่างน้อย 1');
    const r = await api.call('/accounting/pr', {
        method: 'POST',
        body: JSON.stringify({
            request_date: f.request_date.value,
            notes: f.notes.value || null,
            items,
        }),
    });
    if (!r.ok) return alert((r.data && r.data.message) || 'สร้างไม่ได้');
    document.getElementById('form-dialog').close();
    await loadList();
    loadDetail(r.data.data.id);
});

document.querySelectorAll('#filter button').forEach(b => {
    b.addEventListener('click', () => {
        currentStatus = b.dataset.st;
        document.querySelectorAll('#filter button').forEach(x => {
            x.className = `text-xs px-2 py-1 rounded ${x.dataset.st === currentStatus ? 'bg-cyan-600 text-white' : 'bg-white border'}`;
        });
        loadList();
    });
});

(async function () {
    if (!api.token()) return window.location.href = '/login';
    await loadList();
})();
</script>
@endsection
