@extends('layouts.app')
@section('title', 'Disbursements')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">💵 Disbursements (เบิกจ่าย)</h1>
            <a href="/accounting/pr" class="ml-auto text-sm text-cyan-700 hover:underline">PR</a>
            <a href="/accounting/po" class="text-sm text-cyan-700 hover:underline">PO</a>
            <button id="btn-new" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">+ เบิกจ่ายใหม่</button>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6">
        <section class="bg-white rounded-xl shadow">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="px-3 py-2 text-left">No.</th>
                        <th class="px-3 py-2 text-left">วันที่</th>
                        <th class="px-3 py-2 text-left">ประเภท</th>
                        <th class="px-3 py-2 text-right">จำนวน</th>
                        <th class="px-3 py-2 text-left">วิธีจ่าย</th>
                        <th class="px-3 py-2 text-left">ผู้รับ</th>
                        <th class="px-3 py-2 text-center">สถานะ</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody id="rows"></tbody>
            </table>
        </section>
    </main>
</div>

<dialog id="form-dialog" class="rounded-xl p-0 w-[480px] max-w-full">
    <form id="form" class="p-4 space-y-3">
        <h3 class="font-semibold text-lg">เบิกจ่ายใหม่</h3>
        <label class="text-sm block">วันที่
            <input name="disbursement_date" type="date" required class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <label class="text-sm block">ประเภท
            <select name="type" required class="w-full border rounded px-2 py-1 mt-1">
                <option value="salary">เงินเดือน</option>
                <option value="utilities">ค่าน้ำ-ค่าไฟ</option>
                <option value="rent">ค่าเช่า</option>
                <option value="tax">ภาษี</option>
                <option value="supplier">ผู้ขาย</option>
                <option value="other">อื่นๆ</option>
            </select>
        </label>
        <label class="text-sm block">จำนวน (บาท)
            <input name="amount" type="number" step="0.01" min="0.01" required class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <label class="text-sm block">วิธีจ่าย
            <select name="payment_method" class="w-full border rounded px-2 py-1 mt-1">
                <option value="transfer">โอน</option>
                <option value="cash">เงินสด</option>
                <option value="check">เช็ค</option>
                <option value="credit_card">บัตร</option>
            </select>
        </label>
        <label class="text-sm block">ผู้รับ/Vendor
            <input name="vendor" maxlength="150" class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <label class="text-sm block">รายละเอียด
            <textarea name="description" rows="2" class="w-full border rounded px-2 py-1 mt-1"></textarea>
        </label>
        <div class="flex justify-end gap-2">
            <button type="button" class="dlg-cancel px-3 py-1 rounded bg-slate-100">ยกเลิก</button>
            <button type="submit" class="px-3 py-1 rounded bg-cyan-600 text-white">บันทึก (Draft)</button>
        </div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
const SC = {
    draft: 'bg-slate-100 text-slate-700',
    approved: 'bg-amber-100 text-amber-800',
    paid: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-rose-100 text-rose-800',
};
const TYPE_LABEL = { salary: 'เงินเดือน', utilities: 'น้ำ-ไฟ', rent: 'ค่าเช่า', tax: 'ภาษี', supplier: 'ผู้ขาย', other: 'อื่นๆ' };
const PM_LABEL = { cash: 'เงินสด', transfer: 'โอน', check: 'เช็ค', credit_card: 'บัตร' };

document.addEventListener('click', e => {
    if (e.target.classList.contains('dlg-cancel')) e.target.closest('dialog').close();
});

async function loadList() {
    const r = await api.call('/accounting/disbursements');
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('rows').innerHTML = rows.map(d => `
      <tr class="border-t">
        <td class="px-3 py-2 font-mono text-xs">${d.disbursement_no}</td>
        <td class="px-3 py-2 text-xs">${d.disbursement_date}</td>
        <td class="px-3 py-2">${TYPE_LABEL[d.type]||d.type}</td>
        <td class="px-3 py-2 text-right font-bold">${(+d.amount).toLocaleString()}</td>
        <td class="px-3 py-2 text-xs">${PM_LABEL[d.payment_method]||d.payment_method}</td>
        <td class="px-3 py-2 text-xs">${d.vendor||'-'}</td>
        <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded text-xs ${SC[d.status]||''}">${d.status}</span></td>
        <td class="px-3 py-2 text-right whitespace-nowrap">
          ${d.status === 'draft' ? `<button class="approve text-cyan-700 text-sm hover:underline" data-id="${d.id}">อนุมัติ</button>` : ''}
          ${d.status === 'approved' ? `<button class="pay text-emerald-700 text-sm hover:underline ml-2" data-id="${d.id}">💸 จ่าย</button>` : ''}
        </td>
      </tr>`).join('') || '<tr><td colspan="8" class="text-center py-6 text-slate-500">ยังไม่มี</td></tr>';

    document.querySelectorAll('.approve').forEach(b => b.addEventListener('click', async () => {
        const r = await api.call(`/accounting/disbursements/${b.dataset.id}/approve`, { method: 'POST', body: JSON.stringify({}) });
        if (!r.ok) return alert((r.data && r.data.message) || 'อนุมัติไม่ได้');
        loadList();
    }));
    document.querySelectorAll('.pay').forEach(b => b.addEventListener('click', async () => {
        const ref = prompt('เลขที่อ้างอิง (เช่น เลขที่โอน):') || null;
        const r = await api.call(`/accounting/disbursements/${b.dataset.id}/pay`, { method: 'POST', body: JSON.stringify({ reference: ref }) });
        if (!r.ok) return alert((r.data && r.data.message) || 'จ่ายไม่ได้');
        alert('จ่ายแล้ว + post accounting entries');
        loadList();
    }));
}

document.getElementById('btn-new').addEventListener('click', () => {
    const f = document.getElementById('form');
    f.reset();
    f.disbursement_date.value = new Date().toISOString().slice(0, 10);
    document.getElementById('form-dialog').showModal();
});

document.getElementById('form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const r = await api.call('/accounting/disbursements', {
        method: 'POST',
        body: JSON.stringify({
            disbursement_date: f.disbursement_date.value,
            type: f.type.value,
            amount: +f.amount.value,
            payment_method: f.payment_method.value,
            vendor: f.vendor.value || null,
            description: f.description.value || null,
        }),
    });
    if (!r.ok) return alert((r.data && r.data.message) || 'บันทึกไม่ได้');
    document.getElementById('form-dialog').close();
    loadList();
});

(async function () {
    if (!api.token()) return window.location.href = '/login';
    await loadList();
})();
</script>
@endsection
