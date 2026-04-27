@extends('layouts.app')
@section('title', 'General Ledger')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">📚 บัญชีแยกประเภท + งบ</h1>
            <a href="/accounting/coa" class="ml-auto text-sm text-cyan-700 hover:underline">CoA</a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6 space-y-4">
        <section class="bg-white rounded-xl shadow p-4">
            <div class="flex flex-wrap gap-2 items-end mb-3">
                <div class="flex gap-1">
                    <button data-tab="ledger" class="tab-btn px-3 py-1 rounded bg-cyan-600 text-white text-sm">Ledger</button>
                    <button data-tab="trial" class="tab-btn px-3 py-1 rounded bg-white border text-sm">Trial Balance</button>
                    <button data-tab="cash" class="tab-btn px-3 py-1 rounded bg-white border text-sm">Cash Flow</button>
                    <button data-tab="tax" class="tab-btn px-3 py-1 rounded bg-white border text-sm">Tax Summary</button>
                </div>
            </div>
            <div id="content"></div>
        </section>
    </main>
</div>
@endsection

@section('scripts')
<script>
let coa = [];
let active = 'ledger';

function fmt(n) { return (+n||0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}); }

async function loadCoa() {
    if (coa.length) return;
    const r = await api.call('/accounting/coa');
    if (r.ok) coa = r.data.data || [];
}

async function showLedger() {
    await loadCoa();
    document.getElementById('content').innerHTML = `
      <div class="flex flex-wrap gap-2 items-end mb-3">
        <label class="text-sm">บัญชี
          <select id="acc" class="border rounded px-2 py-1 mt-1">${coa.map(a => `<option value="${a.id}">${a.code} ${a.name}</option>`).join('')}</select>
        </label>
        <label class="text-sm">จาก
          <input id="from" type="date" class="border rounded px-2 py-1 mt-1">
        </label>
        <label class="text-sm">ถึง
          <input id="to" type="date" class="border rounded px-2 py-1 mt-1">
        </label>
        <button id="btn-load" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">โหลด</button>
      </div>
      <div id="ledger-out"></div>`;
    const today = new Date(), start = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('from').value = start.toISOString().slice(0, 10);
    document.getElementById('to').value = today.toISOString().slice(0, 10);
    document.getElementById('btn-load').addEventListener('click', loadLedger);
    loadLedger();
}

async function loadLedger() {
    const accId = +document.getElementById('acc').value;
    const from = document.getElementById('from').value;
    const to = document.getElementById('to').value;
    const r = await api.call(`/accounting/reports/ledger?account_id=${accId}&from=${from}&to=${to}`);
    if (!r.ok) return;
    const d = r.data.data;
    document.getElementById('ledger-out').innerHTML = `
      <div class="text-sm mb-2"><b>${d.account.code} ${d.account.name}</b> (${d.account.type})</div>
      <div class="text-xs text-slate-500 mb-3">Opening: ${fmt(d.opening)} • Closing: ${fmt(d.closing)} • Debit total: ${fmt(d.totals.debit)} • Credit total: ${fmt(d.totals.credit)}</div>
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100"><tr>
          <th class="px-2 py-1 text-left">Date</th>
          <th class="px-2 py-1 text-left">Journal</th>
          <th class="px-2 py-1 text-left">Document</th>
          <th class="px-2 py-1 text-right">Debit</th>
          <th class="px-2 py-1 text-right">Credit</th>
          <th class="px-2 py-1 text-right">Balance</th>
          <th class="px-2 py-1 text-left">Description</th>
        </tr></thead>
        <tbody>${d.rows.map(r => `
          <tr class="border-t">
            <td class="px-2 py-1 text-xs">${r.date}</td>
            <td class="px-2 py-1 font-mono text-xs">${r.journal_no}</td>
            <td class="px-2 py-1 text-xs">${r.document}</td>
            <td class="px-2 py-1 text-right ${r.debit>0?'':'text-slate-300'}">${r.debit>0?fmt(r.debit):'-'}</td>
            <td class="px-2 py-1 text-right ${r.credit>0?'':'text-slate-300'}">${r.credit>0?fmt(r.credit):'-'}</td>
            <td class="px-2 py-1 text-right font-semibold">${fmt(r.running_balance)}</td>
            <td class="px-2 py-1 text-xs text-slate-600">${r.description||''}</td>
          </tr>`).join('') || '<tr><td colspan="7" class="text-center py-6 text-slate-500">ไม่มีรายการ</td></tr>'}</tbody>
      </table>`;
}

async function showTrial() {
    document.getElementById('content').innerHTML = `
      <div class="flex flex-wrap gap-2 items-end mb-3">
        <label class="text-sm">ณ วันที่
          <input id="asof" type="date" class="border rounded px-2 py-1 mt-1">
        </label>
        <button id="btn-load" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">โหลด</button>
      </div>
      <div id="trial-out"></div>`;
    document.getElementById('asof').value = new Date().toISOString().slice(0, 10);
    document.getElementById('btn-load').addEventListener('click', loadTrial);
    loadTrial();
}

async function loadTrial() {
    const asOf = document.getElementById('asof').value;
    const r = await api.call(`/accounting/reports/trial-balance?as_of=${asOf}`);
    if (!r.ok) return;
    const d = r.data.data;
    document.getElementById('trial-out').innerHTML = `
      <div class="text-xs text-slate-500 mb-2">As of ${d.as_of} • <b>${d.totals.balanced ? '✅ Balanced' : '❌ NOT BALANCED'}</b></div>
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100"><tr>
          <th class="px-2 py-1 text-left">Code</th>
          <th class="px-2 py-1 text-left">Name</th>
          <th class="px-2 py-1 text-left">Type</th>
          <th class="px-2 py-1 text-right">Debit</th>
          <th class="px-2 py-1 text-right">Credit</th>
        </tr></thead>
        <tbody>${d.rows.map(r => `
          <tr class="border-t">
            <td class="px-2 py-1 font-mono text-xs">${r.code}</td>
            <td class="px-2 py-1">${r.name}</td>
            <td class="px-2 py-1 text-xs text-slate-500">${r.type}</td>
            <td class="px-2 py-1 text-right">${r.debit>0?fmt(r.debit):'-'}</td>
            <td class="px-2 py-1 text-right">${r.credit>0?fmt(r.credit):'-'}</td>
          </tr>`).join('')}</tbody>
        <tfoot class="bg-slate-50 font-bold"><tr>
          <td colspan="3" class="px-2 py-2 text-right">รวม</td>
          <td class="px-2 py-2 text-right">${fmt(d.totals.debit)}</td>
          <td class="px-2 py-2 text-right">${fmt(d.totals.credit)}</td>
        </tr></tfoot>
      </table>`;
}

async function showCashFlow() {
    document.getElementById('content').innerHTML = `
      <div class="flex flex-wrap gap-2 items-end mb-3">
        <label class="text-sm">จาก<input id="from" type="date" class="border rounded px-2 py-1 mt-1"></label>
        <label class="text-sm">ถึง<input id="to" type="date" class="border rounded px-2 py-1 mt-1"></label>
        <button id="btn-load" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">โหลด</button>
      </div>
      <div id="cash-out"></div>`;
    const today = new Date(), start = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('from').value = start.toISOString().slice(0, 10);
    document.getElementById('to').value = today.toISOString().slice(0, 10);
    document.getElementById('btn-load').addEventListener('click', loadCashFlow);
    loadCashFlow();
}

async function loadCashFlow() {
    const from = document.getElementById('from').value;
    const to = document.getElementById('to').value;
    const r = await api.call(`/accounting/reports/cash-flow?from=${from}&to=${to}`);
    if (!r.ok) return;
    const d = r.data.data;
    document.getElementById('cash-out').innerHTML = `
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100"><tr>
          <th class="px-2 py-1 text-left">Account</th>
          <th class="px-2 py-1 text-right">Opening</th>
          <th class="px-2 py-1 text-right">Cash In</th>
          <th class="px-2 py-1 text-right">Cash Out</th>
          <th class="px-2 py-1 text-right">Net</th>
          <th class="px-2 py-1 text-right">Closing</th>
        </tr></thead>
        <tbody>${d.rows.map(r => `
          <tr class="border-t">
            <td class="px-2 py-1">${r.code} ${r.name}</td>
            <td class="px-2 py-1 text-right">${fmt(r.opening)}</td>
            <td class="px-2 py-1 text-right text-emerald-700">${fmt(r.cash_in)}</td>
            <td class="px-2 py-1 text-right text-rose-700">${fmt(r.cash_out)}</td>
            <td class="px-2 py-1 text-right font-bold ${r.net>=0?'text-emerald-700':'text-rose-700'}">${fmt(r.net)}</td>
            <td class="px-2 py-1 text-right font-bold">${fmt(r.closing)}</td>
          </tr>`).join('')}</tbody>
        <tfoot class="bg-slate-50 font-bold"><tr>
          <td class="px-2 py-2 text-right">รวม</td><td></td>
          <td class="px-2 py-2 text-right text-emerald-700">${fmt(d.totals.cash_in)}</td>
          <td class="px-2 py-2 text-right text-rose-700">${fmt(d.totals.cash_out)}</td>
          <td class="px-2 py-2 text-right ${d.totals.net>=0?'text-emerald-700':'text-rose-700'}">${fmt(d.totals.net)}</td><td></td>
        </tr></tfoot>
      </table>`;
}

async function showTax() {
    document.getElementById('content').innerHTML = `
      <div class="flex flex-wrap gap-2 items-end mb-3">
        <label class="text-sm">เดือน
          <input id="month" type="month" class="border rounded px-2 py-1 mt-1">
        </label>
        <button id="btn-load" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">โหลด</button>
      </div>
      <div id="tax-out"></div>`;
    document.getElementById('month').value = new Date().toISOString().slice(0, 7);
    document.getElementById('btn-load').addEventListener('click', loadTax);
    loadTax();
}

async function loadTax() {
    const month = document.getElementById('month').value;
    const r = await api.call(`/accounting/reports/tax-summary?month=${month}`);
    if (!r.ok) return;
    const d = r.data.data;
    document.getElementById('tax-out').innerHTML = `
      <div class="grid grid-cols-3 gap-2 mb-4">
        <div class="p-3 bg-emerald-50 rounded"><div class="text-xs text-emerald-600">Output VAT (ขาย)</div><div class="text-xl font-bold text-emerald-700">${fmt(d.output_vat)}</div></div>
        <div class="p-3 bg-amber-50 rounded"><div class="text-xs text-amber-600">Input VAT (ซื้อ)</div><div class="text-xl font-bold text-amber-700">${fmt(d.input_vat)}</div></div>
        <div class="p-3 bg-cyan-50 rounded"><div class="text-xs text-cyan-600">Net Payable</div><div class="text-xl font-bold text-cyan-700">${fmt(d.net_payable)}</div></div>
      </div>
      <h4 class="font-medium text-sm mb-2">Output VAT Rows (ใบกำกับภาษีในเดือน ${d.period})</h4>
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100"><tr>
          <th class="px-2 py-1 text-left">Tax Inv No.</th>
          <th class="px-2 py-1 text-left">Date</th>
          <th class="px-2 py-1 text-left">Customer</th>
          <th class="px-2 py-1 text-left">Tax ID</th>
          <th class="px-2 py-1 text-right">Taxable</th>
          <th class="px-2 py-1 text-right">VAT</th>
          <th class="px-2 py-1 text-right">Total</th>
        </tr></thead>
        <tbody>${(d.output_rows||[]).map(r => `
          <tr class="border-t">
            <td class="px-2 py-1 font-mono text-xs">${r.tax_invoice_no}</td>
            <td class="px-2 py-1 text-xs">${r.issued_at}</td>
            <td class="px-2 py-1">${r.customer_name}</td>
            <td class="px-2 py-1 font-mono text-xs">${r.customer_tax_id||'-'}</td>
            <td class="px-2 py-1 text-right">${fmt(r.taxable_amount)}</td>
            <td class="px-2 py-1 text-right text-amber-700">${fmt(r.vat_amount)}</td>
            <td class="px-2 py-1 text-right font-bold">${fmt(r.total)}</td>
          </tr>`).join('')||'<tr><td colspan="7" class="text-center py-4 text-slate-500">ไม่มีใบกำกับในเดือนนี้</td></tr>'}</tbody>
      </table>`;
}

document.querySelectorAll('.tab-btn').forEach(b => {
    b.addEventListener('click', () => {
        active = b.dataset.tab;
        document.querySelectorAll('.tab-btn').forEach(x => {
            x.className = `tab-btn px-3 py-1 rounded text-sm ${x.dataset.tab === active ? 'bg-cyan-600 text-white' : 'bg-white border'}`;
        });
        if (active === 'ledger') showLedger();
        if (active === 'trial') showTrial();
        if (active === 'cash') showCashFlow();
        if (active === 'tax') showTax();
    });
});

(async function () {
    if (!api.token()) return window.location.href = '/login';
    showLedger();
})();
</script>
@endsection
