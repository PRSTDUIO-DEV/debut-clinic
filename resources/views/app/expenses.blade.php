@extends('layouts.app')
@section('title', 'รายจ่าย')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">💸 รายจ่าย (Expenses)</h1>
            <a href="/closing" class="ml-auto text-sm text-cyan-700 hover:underline">ปิดยอด</a>
            <button id="btn-cat" class="text-sm bg-slate-100 px-3 py-1 rounded">หมวดรายจ่าย</button>
            <button id="btn-new" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">+ รายจ่ายใหม่</button>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6 space-y-4">
        <section class="bg-white rounded-xl shadow p-4">
            <div class="flex flex-wrap gap-2 mb-3 items-end">
                <label class="text-sm">จาก
                    <input id="date_from" type="date" class="border rounded px-2 py-1 mt-1">
                </label>
                <label class="text-sm">ถึง
                    <input id="date_to" type="date" class="border rounded px-2 py-1 mt-1">
                </label>
                <label class="text-sm">หมวด
                    <select id="cat" class="border rounded px-2 py-1 mt-1"><option value="">ทั้งหมด</option></select>
                </label>
                <button id="btn-filter" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">ค้นหา</button>
                <span id="sum" class="ml-auto font-bold text-lg text-rose-700"></span>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100"><tr>
                    <th class="px-3 py-2 text-left">วันที่</th>
                    <th class="px-3 py-2 text-left">หมวด</th>
                    <th class="px-3 py-2 text-right">จำนวน</th>
                    <th class="px-3 py-2 text-left">วิธีจ่าย</th>
                    <th class="px-3 py-2 text-left">ผู้ขาย</th>
                    <th class="px-3 py-2 text-left">รายละเอียด</th>
                    <th></th>
                </tr></thead>
                <tbody id="rows"></tbody>
            </table>
        </section>
    </main>
</div>

<dialog id="form-dialog" class="rounded-xl p-0 w-[480px] max-w-full">
    <form id="form" class="p-4 space-y-3">
        <h3 class="font-semibold text-lg">รายจ่ายใหม่</h3>
        <input type="hidden" name="id">
        <div class="grid grid-cols-2 gap-2">
            <label class="text-sm col-span-2">หมวด
                <select name="category_id" class="w-full border rounded px-2 py-1 mt-1"></select>
            </label>
            <label class="text-sm">วันที่
                <input name="expense_date" type="date" required class="w-full border rounded px-2 py-1 mt-1">
            </label>
            <label class="text-sm">จำนวน (บาท)
                <input name="amount" type="number" step="0.01" min="0.01" required class="w-full border rounded px-2 py-1 mt-1">
            </label>
            <label class="text-sm">วิธีจ่าย
                <select name="payment_method" class="w-full border rounded px-2 py-1 mt-1">
                    <option value="cash">เงินสด</option>
                    <option value="transfer">โอน</option>
                    <option value="credit_card">บัตรเครดิต</option>
                    <option value="check">เช็ค</option>
                    <option value="other">อื่นๆ</option>
                </select>
            </label>
            <label class="text-sm">ผู้ขาย/ผู้รับเงิน
                <input name="vendor" maxlength="150" class="w-full border rounded px-2 py-1 mt-1">
            </label>
            <label class="text-sm col-span-2">รายละเอียด
                <textarea name="description" rows="2" class="w-full border rounded px-2 py-1 mt-1"></textarea>
            </label>
        </div>
        <div class="flex justify-end gap-2">
            <button type="button" class="dlg-cancel px-3 py-1 rounded bg-slate-100">ยกเลิก</button>
            <button type="submit" class="px-3 py-1 rounded bg-cyan-600 text-white">บันทึก</button>
        </div>
    </form>
</dialog>

<dialog id="cat-dialog" class="rounded-xl p-0 w-[480px] max-w-full">
    <div class="p-4 space-y-3">
        <h3 class="font-semibold text-lg">หมวดรายจ่าย</h3>
        <ul id="cat-list" class="text-sm divide-y"></ul>
        <div class="flex gap-2">
            <input id="new-cat-name" placeholder="ชื่อหมวดใหม่" class="flex-1 border rounded px-2 py-1">
            <button id="add-cat" class="bg-cyan-600 text-white px-3 py-1 rounded">เพิ่ม</button>
        </div>
        <div class="flex justify-end">
            <button class="dlg-cancel px-3 py-1 rounded bg-slate-100">ปิด</button>
        </div>
    </div>
</dialog>
@endsection

@section('scripts')
<script>
const PM_LABEL = { cash: 'เงินสด', transfer: 'โอน', credit_card: 'บัตรเครดิต', check: 'เช็ค', other: 'อื่นๆ' };
let categories = [];

async function loadCategories() {
    const r = await api.call('/expense-categories');
    if (!r.ok) return;
    categories = r.data.data || [];
    const opts = '<option value="">— ไม่ระบุ —</option>' + categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    document.querySelector('[name=category_id]').innerHTML = opts;
    document.getElementById('cat').innerHTML = '<option value="">ทั้งหมด</option>' + categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    document.getElementById('cat-list').innerHTML = categories.map(c => `
      <li class="py-2 flex justify-between items-center">
        <span>${c.name} ${c.is_active ? '' : '<span class="text-xs text-slate-400">(ปิด)</span>'}</span>
        <button class="del-cat text-rose-600 text-xs hover:underline" data-id="${c.id}">ลบ</button>
      </li>`).join('') || '<em class="text-slate-500">ไม่มี</em>';

    document.querySelectorAll('.del-cat').forEach(b => b.addEventListener('click', async () => {
        if (!confirm('ลบหมวดนี้?')) return;
        const r = await api.call(`/expense-categories/${b.dataset.id}`, { method: 'DELETE' });
        if (!r.ok) return alert((r.data && r.data.message) || 'ลบไม่ได้');
        loadCategories();
    }));
}

async function loadExpenses() {
    const params = new URLSearchParams();
    const f = document.getElementById('date_from').value;
    const t = document.getElementById('date_to').value;
    const c = document.getElementById('cat').value;
    if (f) params.set('date_from', f);
    if (t) params.set('date_to', t);
    if (c) params.set('category_id', c);
    const r = await api.call('/expenses?'+params.toString());
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('rows').innerHTML = rows.map(e => `
      <tr class="border-t">
        <td class="px-3 py-1.5 text-xs">${e.expense_date}</td>
        <td class="px-3 py-1.5">${e.category||'-'}</td>
        <td class="px-3 py-1.5 text-right text-rose-700 font-bold">${(+e.amount).toLocaleString()}</td>
        <td class="px-3 py-1.5 text-xs">${PM_LABEL[e.payment_method]||e.payment_method}</td>
        <td class="px-3 py-1.5 text-xs text-slate-600">${e.vendor||'-'}</td>
        <td class="px-3 py-1.5 text-xs text-slate-500">${e.description||''}</td>
        <td class="px-3 py-1.5 text-right">
          <button class="del text-rose-600 text-xs hover:underline" data-id="${e.id}">ลบ</button>
        </td>
      </tr>`).join('') || '<tr><td colspan="7" class="text-center py-6 text-slate-500">ไม่มีรายการ</td></tr>';
    document.getElementById('sum').textContent = 'รวม ฿' + (r.data.meta?.sum||0).toLocaleString();
    document.querySelectorAll('.del').forEach(b => b.addEventListener('click', async () => {
        if (!confirm('ลบรายการนี้?')) return;
        const r = await api.call(`/expenses/${b.dataset.id}`, { method: 'DELETE' });
        if (!r.ok) return alert((r.data && r.data.message) || 'ลบไม่ได้');
        loadExpenses();
    }));
}

document.getElementById('btn-new').addEventListener('click', () => {
    const f = document.getElementById('form');
    f.reset();
    f.expense_date.value = new Date().toISOString().slice(0, 10);
    document.getElementById('form-dialog').showModal();
});

document.getElementById('btn-filter').addEventListener('click', loadExpenses);

document.getElementById('btn-cat').addEventListener('click', () => document.getElementById('cat-dialog').showModal());

document.getElementById('add-cat').addEventListener('click', async () => {
    const name = document.getElementById('new-cat-name').value.trim();
    if (!name) return;
    const r = await api.call('/expense-categories', { method: 'POST', body: JSON.stringify({ name }) });
    if (!r.ok) return alert((r.data && r.data.message) || 'เพิ่มไม่ได้');
    document.getElementById('new-cat-name').value = '';
    loadCategories();
});

document.getElementById('form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const body = {
        category_id: f.category_id.value ? +f.category_id.value : null,
        expense_date: f.expense_date.value,
        amount: +f.amount.value,
        payment_method: f.payment_method.value,
        vendor: f.vendor.value || null,
        description: f.description.value || null,
    };
    const r = await api.call('/expenses', { method: 'POST', body: JSON.stringify(body) });
    if (!r.ok) return alert((r.data && r.data.message) || 'บันทึกไม่ได้');
    document.getElementById('form-dialog').close();
    loadExpenses();
});

document.querySelectorAll('.dlg-cancel').forEach(b => b.addEventListener('click', e => e.target.closest('dialog').close()));

(async function () {
    if (!api.token()) return window.location.href = '/login';
    await loadCategories();
    await loadExpenses();
})();
</script>
@endsection
