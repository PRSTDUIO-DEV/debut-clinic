@extends('layouts.app')
@section('title', 'Payroll Detail')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/admin/payroll" class="text-cyan-700 hover:underline">← Payroll</a>
            <h1 class="font-bold" id="title">Payroll …</h1>
            <span id="status-badge" class="ml-2 text-xs"></span>
            <div class="ml-auto flex gap-2" id="actions"></div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6 space-y-4">
        <section id="summary" class="grid grid-cols-2 md:grid-cols-4 gap-3"></section>

        <section class="bg-white rounded-xl shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100"><tr>
                    <th class="px-3 py-2 text-left">รหัส</th>
                    <th class="text-left">พนักงาน</th>
                    <th>ตำแหน่ง</th>
                    <th>ชั่วโมง</th>
                    <th>วัน</th>
                    <th>สาย</th>
                    <th class="text-right">Base</th>
                    <th class="text-right">Commission</th>
                    <th class="text-right">OT</th>
                    <th class="text-right">Bonus</th>
                    <th class="text-right">Deduction</th>
                    <th class="text-right font-bold">Net Pay</th>
                </tr></thead>
                <tbody id="rows"></tbody>
            </table>
        </section>
    </main>
</div>

<dialog id="dlg-pay" class="rounded-xl p-0 w-[420px]">
    <form method="dialog" id="form-pay" class="p-4 space-y-2">
        <h3 class="font-bold">บันทึกการจ่าย</h3>
        <select name="payment_method" class="w-full border rounded px-2 py-1.5">
            <option value="transfer">โอนเงิน</option><option value="cash">เงินสด</option><option value="cheque">เช็ค</option>
        </select>
        <input name="payment_reference" placeholder="เลขที่อ้างอิง" class="w-full border rounded px-2 py-1.5">
        <div class="flex gap-2 pt-2"><button type="submit" class="bg-emerald-600 text-white px-3 py-1.5 rounded">บันทึก</button>
            <button type="button" onclick="document.getElementById('dlg-pay').close()" class="px-3 py-1.5">ยกเลิก</button></div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
const PARTS = location.pathname.split('/');
const YEAR = parseInt(PARTS[PARTS.length - 2]);
const MONTH = parseInt(PARTS[PARTS.length - 1]);
let payroll = null;

function fmt(n) { return (+n||0).toLocaleString(undefined, {maximumFractionDigits:2}); }

async function loadOrCreatePayroll() {
    const list = await api.call(`/admin/payrolls?year=${YEAR}`);
    if (!list.ok) return;
    payroll = (list.data.data?.data || []).find(p => p.period_month === MONTH);

    if (!payroll) {
        const r = await api.call('/admin/payrolls/preview', { method: 'POST', body: { year: YEAR, month: MONTH } });
        if (!r.ok) return alert(JSON.stringify(r.data));
        payroll = r.data.data;
    } else {
        const r = await api.call(`/admin/payrolls/${payroll.id}`);
        if (r.ok) payroll = r.data.data;
    }
    render();
}

function render() {
    document.getElementById('title').textContent = `Payroll ${YEAR}/${String(MONTH).padStart(2,'0')}`;

    const badge = document.getElementById('status-badge');
    const cls = {
        draft: 'bg-amber-100 text-amber-700',
        finalized: 'bg-cyan-100 text-cyan-700',
        paid: 'bg-emerald-100 text-emerald-700',
    }[payroll.status];
    badge.innerHTML = `<span class="px-2 py-0.5 rounded ${cls}">${payroll.status}</span>`;

    const items = payroll.items || [];
    const totals = items.reduce((acc, i) => {
        acc.base += +i.base_pay;
        acc.commission += +i.commission_total;
        acc.bonus += +i.bonus;
        acc.ot += +i.overtime_pay;
        return acc;
    }, { base: 0, commission: 0, bonus: 0, ot: 0 });

    document.getElementById('summary').innerHTML = `
        <div class="bg-white rounded-xl shadow p-3"><div class="text-xs text-slate-500">รวมจ่าย</div><div class="text-2xl font-bold">฿${fmt(payroll.total_amount)}</div></div>
        <div class="bg-cyan-50 rounded-xl p-3"><div class="text-xs text-slate-500">Base</div><div class="text-xl font-bold">฿${fmt(totals.base)}</div></div>
        <div class="bg-emerald-50 rounded-xl p-3"><div class="text-xs text-slate-500">Commission</div><div class="text-xl font-bold">฿${fmt(totals.commission)}</div></div>
        <div class="bg-violet-50 rounded-xl p-3"><div class="text-xs text-slate-500">OT + Bonus</div><div class="text-xl font-bold">฿${fmt(totals.ot + totals.bonus)}</div></div>`;

    const editable = payroll.status === 'draft';
    document.getElementById('rows').innerHTML = items.map(i => `
        <tr class="border-t" data-item="${i.id}">
            <td class="px-3 py-1.5 font-mono text-xs">${i.user?.employee_code || '—'}</td>
            <td><a href="/admin/staff/${i.user_id}" class="text-cyan-700 hover:underline">${i.user?.name || '?'}</a></td>
            <td class="text-xs text-center">${i.user?.position || '—'}</td>
            <td class="text-right">${i.hours_worked}</td>
            <td class="text-right">${i.days_worked}</td>
            <td class="text-right ${i.late_count > 0 ? 'text-amber-600' : ''}">${i.late_count}</td>
            <td class="text-right">${fmt(i.base_pay)}</td>
            <td class="text-right">${fmt(i.commission_total)}</td>
            <td class="text-right">${fmt(i.overtime_pay)}</td>
            <td class="text-right">${editable ? `<input type="number" step="0.01" value="${+i.bonus}" data-field="bonus" class="w-20 border rounded px-1 py-0.5 text-right text-xs">` : fmt(i.bonus)}</td>
            <td class="text-right">${editable ? `<input type="number" step="0.01" value="${+i.deduction}" data-field="deduction" class="w-20 border rounded px-1 py-0.5 text-right text-xs">` : fmt(i.deduction)}</td>
            <td class="text-right font-bold">${fmt(i.net_pay)}</td>
        </tr>`).join('') || `<tr><td colspan="12" class="text-center py-6 text-slate-400">ยังไม่มี items</td></tr>`;

    if (editable) {
        document.querySelectorAll('input[data-field]').forEach(inp => {
            inp.onchange = async () => {
                const tr = inp.closest('tr');
                const itemId = tr.dataset.item;
                const bonus = +tr.querySelector('[data-field="bonus"]').value;
                const deduction = +tr.querySelector('[data-field="deduction"]').value;
                const r = await api.call(`/admin/payrolls/${payroll.id}/items/${itemId}`, {
                    method: 'PATCH', body: { bonus, deduction },
                });
                if (r.ok) {
                    const upd = r.data.data;
                    tr.querySelector('td:last-child').textContent = fmt(upd.net_pay);
                    await loadOrCreatePayroll();
                }
            };
        });
    }

    const actions = document.getElementById('actions');
    if (payroll.status === 'draft') {
        actions.innerHTML = `<button id="btn-finalize" class="bg-cyan-600 text-white px-3 py-1.5 rounded text-sm">🔒 Finalize</button>`;
        document.getElementById('btn-finalize').onclick = async () => {
            if (!confirm('Finalize payroll? Commission ทั้งหมดในเดือนนี้จะถูก mark เป็น paid')) return;
            const r = await api.call(`/admin/payrolls/${payroll.id}/finalize`, { method: 'POST' });
            if (r.ok) await loadOrCreatePayroll();
            else alert(JSON.stringify(r.data));
        };
    } else if (payroll.status === 'finalized') {
        actions.innerHTML = `<button id="btn-paid" class="bg-emerald-600 text-white px-3 py-1.5 rounded text-sm">💸 Mark as Paid</button>`;
        document.getElementById('btn-paid').onclick = () => document.getElementById('dlg-pay').showModal();
    } else {
        actions.innerHTML = `<span class="text-emerald-700 text-sm">✅ จ่ายแล้ว ${payroll.payment_method || ''} ${payroll.payment_reference ? `(${payroll.payment_reference})` : ''}</span>`;
    }
}

document.getElementById('form-pay').onsubmit = async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const r = await api.call(`/admin/payrolls/${payroll.id}/mark-paid`, { method: 'POST', body: data });
    if (r.ok) { document.getElementById('dlg-pay').close(); await loadOrCreatePayroll(); }
    else alert(JSON.stringify(r.data));
};

(async function () { if (!api.token()) return location.href = '/login'; await loadOrCreatePayroll(); })();
</script>
@endsection
