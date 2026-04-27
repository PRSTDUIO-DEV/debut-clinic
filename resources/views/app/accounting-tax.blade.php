@extends('layouts.app')
@section('title', 'Tax Invoices')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">🧾 Tax Invoices (ใบกำกับภาษี)</h1>
            <a href="/accounting/ledger" class="ml-auto text-sm text-cyan-700 hover:underline">Ledger</a>
            <button id="btn-new" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">+ ออกใบกำกับ</button>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6">
        <section class="bg-white rounded-xl shadow">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="px-3 py-2 text-left">Tax Inv No.</th>
                        <th class="px-3 py-2 text-left">วันที่</th>
                        <th class="px-3 py-2 text-left">ลูกค้า</th>
                        <th class="px-3 py-2 text-left">Tax ID</th>
                        <th class="px-3 py-2 text-left">Invoice</th>
                        <th class="px-3 py-2 text-right">Taxable</th>
                        <th class="px-3 py-2 text-right">VAT</th>
                        <th class="px-3 py-2 text-right">Total</th>
                        <th class="px-3 py-2 text-center">สถานะ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="rows"></tbody>
            </table>
        </section>
    </main>
</div>

<dialog id="form-dialog" class="rounded-xl p-0 w-[480px] max-w-full">
    <form id="form" class="p-4 space-y-3">
        <h3 class="font-semibold text-lg">ออกใบกำกับภาษี</h3>
        <label class="text-sm block">Invoice ID
            <input name="invoice_id" type="number" required class="w-full border rounded px-2 py-1 mt-1">
            <span class="text-xs text-slate-500">ID ของบิลที่จ่ายแล้ว (ดูจาก /reports/payment-mix)</span>
        </label>
        <label class="text-sm block">ชื่อลูกค้า
            <input name="customer_name" required maxlength="200" class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <label class="text-sm block">เลขผู้เสียภาษี
            <input name="customer_tax_id" maxlength="30" class="w-full border rounded px-2 py-1 mt-1 font-mono">
        </label>
        <label class="text-sm block">ที่อยู่
            <textarea name="customer_address" rows="2" class="w-full border rounded px-2 py-1 mt-1"></textarea>
        </label>
        <label class="text-sm block">VAT %
            <input name="vat_rate" type="number" step="0.01" min="0" max="30" value="7" class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <div class="flex justify-end gap-2">
            <button type="button" class="dlg-cancel px-3 py-1 rounded bg-slate-100">ยกเลิก</button>
            <button type="submit" class="px-3 py-1 rounded bg-cyan-600 text-white">ออกใบ</button>
        </div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
const SC = { active: 'bg-emerald-100 text-emerald-800', voided: 'bg-rose-100 text-rose-800' };

document.addEventListener('click', e => {
    if (e.target.classList.contains('dlg-cancel')) e.target.closest('dialog').close();
});

async function loadList() {
    const r = await api.call('/accounting/tax-invoices');
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('rows').innerHTML = rows.map(t => `
      <tr class="border-t">
        <td class="px-3 py-2 font-mono text-xs">${t.tax_invoice_no}</td>
        <td class="px-3 py-2 text-xs">${t.issued_at}</td>
        <td class="px-3 py-2">${t.customer_name}</td>
        <td class="px-3 py-2 font-mono text-xs">${t.customer_tax_id||'-'}</td>
        <td class="px-3 py-2 font-mono text-xs">${t.invoice?.invoice_number||'-'}</td>
        <td class="px-3 py-2 text-right">${(+t.taxable_amount).toLocaleString()}</td>
        <td class="px-3 py-2 text-right text-amber-700">${(+t.vat_amount).toLocaleString()}</td>
        <td class="px-3 py-2 text-right font-bold">${(+t.total).toLocaleString()}</td>
        <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded text-xs ${SC[t.status]||''}">${t.status}</span></td>
        <td class="px-3 py-2 text-right">
          ${t.status === 'active' ? `<button class="void text-rose-600 text-sm hover:underline" data-id="${t.id}">Void</button>` : ''}
        </td>
      </tr>`).join('') || '<tr><td colspan="10" class="text-center py-6 text-slate-500">ยังไม่มี</td></tr>';

    document.querySelectorAll('.void').forEach(b => b.addEventListener('click', async () => {
        const reason = prompt('เหตุผลที่ void:');
        if (!reason) return;
        const r = await api.call(`/accounting/tax-invoices/${b.dataset.id}/void`, { method: 'POST', body: JSON.stringify({ reason }) });
        if (!r.ok) return alert((r.data && r.data.message) || 'void ไม่ได้');
        loadList();
    }));
}

document.getElementById('btn-new').addEventListener('click', () => {
    document.getElementById('form-dialog').showModal();
});

document.getElementById('form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const r = await api.call('/accounting/tax-invoices', {
        method: 'POST',
        body: JSON.stringify({
            invoice_id: +f.invoice_id.value,
            customer_name: f.customer_name.value,
            customer_tax_id: f.customer_tax_id.value || null,
            customer_address: f.customer_address.value || null,
            vat_rate: +f.vat_rate.value,
        }),
    });
    if (!r.ok) return alert((r.data && r.data.message) || 'ออกไม่ได้');
    f.reset();
    document.getElementById('form-dialog').close();
    loadList();
});

(async function () {
    if (!api.token()) return window.location.href = '/login';
    await loadList();
})();
</script>
@endsection
