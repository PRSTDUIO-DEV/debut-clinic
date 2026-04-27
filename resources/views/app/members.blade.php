@extends('layouts.app')
@section('title', 'สมาชิกเงินฝาก')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">💎 สมาชิกเงินฝาก (Member Wallet)</h1>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6 grid md:grid-cols-3 gap-4">
        <section class="md:col-span-1 bg-white rounded-xl shadow p-4">
            <div class="flex items-center gap-2 mb-3">
                <input id="search" placeholder="ค้นหา HN/ชื่อ/เบอร์..." class="w-full border rounded px-2 py-1 text-sm">
            </div>
            <div id="status-filter" class="flex gap-1 mb-3">
                <button data-st="" class="text-xs px-2 py-1 rounded bg-cyan-600 text-white">ทั้งหมด</button>
                <button data-st="active" class="text-xs px-2 py-1 rounded bg-white border">active</button>
                <button data-st="expired" class="text-xs px-2 py-1 rounded bg-white border">expired</button>
                <button data-st="suspended" class="text-xs px-2 py-1 rounded bg-white border">suspended</button>
            </div>
            <ul id="list" class="space-y-2 max-h-[600px] overflow-y-auto"></ul>
        </section>

        <section class="md:col-span-2 bg-white rounded-xl shadow p-4">
            <div id="detail" class="text-slate-500">เลือกสมาชิกจากรายการด้านซ้าย</div>
        </section>
    </main>
</div>

<dialog id="topup-dialog" class="rounded-xl p-0 w-96 max-w-full">
    <form id="topup-form" class="p-4 space-y-3">
        <h3 class="font-semibold text-lg">เติมเงินเข้า Wallet</h3>
        <label class="block text-sm">จำนวนเงิน (บาท)
            <input name="amount" type="number" min="0.01" step="0.01" required class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <label class="block text-sm">แพ็คเกจ (optional)
            <input name="package_name" class="w-full border rounded px-2 py-1 mt-1" placeholder="เช่น แพ็คเกจฝาก 50,000">
        </label>
        <label class="block text-sm">หมดอายุ (optional)
            <input name="expires_at" type="date" class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <label class="block text-sm">หมายเหตุ
            <input name="notes" class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <div class="flex justify-end gap-2">
            <button type="button" class="dlg-cancel px-3 py-1 rounded bg-slate-100">ยกเลิก</button>
            <button type="submit" class="px-3 py-1 rounded bg-cyan-600 text-white">เติมเงิน</button>
        </div>
    </form>
</dialog>

<dialog id="adjust-dialog" class="rounded-xl p-0 w-96 max-w-full">
    <form id="adjust-form" class="p-4 space-y-3">
        <h3 class="font-semibold text-lg">ปรับยอด Wallet</h3>
        <label class="block text-sm">delta (+เพิ่ม / -ลด)
            <input name="delta" type="number" step="0.01" required class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <label class="block text-sm">เหตุผล
            <input name="reason" required class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <div class="flex justify-end gap-2">
            <button type="button" class="dlg-cancel px-3 py-1 rounded bg-slate-100">ยกเลิก</button>
            <button type="submit" class="px-3 py-1 rounded bg-amber-600 text-white">บันทึก</button>
        </div>
    </form>
</dialog>

<dialog id="refund-dialog" class="rounded-xl p-0 w-96 max-w-full">
    <form id="refund-form" class="p-4 space-y-3">
        <h3 class="font-semibold text-lg">คืนเงินเข้า Wallet</h3>
        <label class="block text-sm">จำนวนเงิน
            <input name="amount" type="number" min="0.01" step="0.01" required class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <label class="block text-sm">หมายเหตุ
            <input name="notes" class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <div class="flex justify-end gap-2">
            <button type="button" class="dlg-cancel px-3 py-1 rounded bg-slate-100">ยกเลิก</button>
            <button type="submit" class="px-3 py-1 rounded bg-emerald-600 text-white">คืนเงิน</button>
        </div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
const STATUS_LABEL = { active: 'active', expired: 'หมดอายุ', suspended: 'ระงับ' };
const STATUS_COLOR = {
    active: 'bg-emerald-100 text-emerald-800',
    expired: 'bg-slate-200 text-slate-700',
    suspended: 'bg-rose-100 text-rose-800',
};
const TYPE_LABEL = { deposit: 'ฝาก', usage: 'ใช้', refund: 'คืน', adjustment: 'ปรับ' };
const TYPE_COLOR = {
    deposit: 'bg-cyan-100 text-cyan-800',
    usage: 'bg-amber-100 text-amber-800',
    refund: 'bg-emerald-100 text-emerald-800',
    adjustment: 'bg-purple-100 text-purple-800',
};

let currentPatientUuid = null;
let currentStatus = '';
let listCache = [];

async function loadList() {
    const params = new URLSearchParams();
    if (document.getElementById('search').value) params.set('q', document.getElementById('search').value);
    if (currentStatus) params.set('status', currentStatus);
    const r = await api.call('/members?'+params.toString());
    if (!r.ok) return;
    listCache = r.data.data || [];
    document.getElementById('list').innerHTML = listCache.map(m => `
      <li class="border rounded p-2 cursor-pointer hover:bg-cyan-50" data-uuid="${m.patient.uuid}">
        <div class="flex justify-between items-center">
          <div>
            <div class="font-medium">${m.patient.name}</div>
            <div class="text-xs text-slate-500 font-mono">${m.patient.hn} • ${m.patient.phone||'-'}</div>
          </div>
          <div class="text-right">
            <div class="font-bold text-cyan-700">฿${m.balance.toLocaleString()}</div>
            <span class="text-xs px-1.5 py-0.5 rounded ${STATUS_COLOR[m.status]||''}">${STATUS_LABEL[m.status]||m.status}</span>
          </div>
        </div>
      </li>`).join('') || '<em class="text-slate-400 text-sm">ไม่พบสมาชิก</em>';
    document.querySelectorAll('#list li').forEach(li => {
        li.addEventListener('click', () => loadDetail(li.dataset.uuid));
    });
}

async function loadDetail(uuid) {
    currentPatientUuid = uuid;
    const [accRes, txnRes] = await Promise.all([
        api.call(`/members/${uuid}`),
        api.call(`/members/${uuid}/transactions`),
    ]);
    if (!accRes.ok) return;
    const a = accRes.data.data;
    const txns = txnRes.data?.data || [];
    if (!a) {
        document.getElementById('detail').innerHTML = '<em class="text-slate-500">ผู้ป่วยรายนี้ยังไม่มีบัญชีสมาชิก กดเติมเงินเพื่อเปิดบัญชี</em><br>'
            + `<button id="btn-topup" class="mt-3 bg-cyan-600 text-white px-3 py-1 rounded text-sm">+ เปิดบัญชี/เติมเงิน</button>`;
        document.getElementById('btn-topup').addEventListener('click', openTopup);
        return;
    }

    document.getElementById('detail').innerHTML = `
      <div class="flex items-start justify-between">
        <div>
          <h2 class="text-xl font-bold">${a.patient.name}</h2>
          <div class="text-sm text-slate-500 font-mono">${a.patient.hn} • ${a.patient.phone||'-'}</div>
          ${a.package_name ? `<div class="mt-1 text-xs px-2 py-0.5 inline-block rounded bg-slate-100">${a.package_name}</div>` : ''}
        </div>
        <div class="text-right">
          <div class="text-3xl font-bold text-cyan-700">฿${a.balance.toLocaleString()}</div>
          <span class="text-xs px-2 py-0.5 rounded ${STATUS_COLOR[a.status]||''}">${STATUS_LABEL[a.status]||a.status}</span>
        </div>
      </div>
      <div class="grid grid-cols-3 gap-2 mt-4 text-center text-sm">
        <div class="p-2 bg-slate-50 rounded">ฝากรวม<br><span class="font-bold text-slate-700">฿${a.total_deposit.toLocaleString()}</span></div>
        <div class="p-2 bg-slate-50 rounded">ใช้รวม<br><span class="font-bold text-slate-700">฿${a.total_used.toLocaleString()}</span></div>
        <div class="p-2 bg-slate-50 rounded">เติมไปแล้ว<br><span class="font-bold text-slate-700">${a.lifetime_topups} ครั้ง</span></div>
      </div>
      <div class="mt-4 flex gap-2 flex-wrap">
        <button id="btn-topup" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">+ เติมเงิน</button>
        <button id="btn-adjust" class="bg-amber-600 text-white px-3 py-1 rounded text-sm">ปรับยอด</button>
        <button id="btn-refund" class="bg-emerald-600 text-white px-3 py-1 rounded text-sm">คืนเงิน</button>
        <a href="/patients/${a.patient.uuid}" class="bg-slate-100 px-3 py-1 rounded text-sm">📋 OPD Card</a>
      </div>

      <h3 class="font-semibold mt-6 mb-2">ประวัติการเคลื่อนไหว</h3>
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100">
          <tr>
            <th class="px-2 py-1 text-left">เวลา</th>
            <th class="px-2 py-1 text-left">ประเภท</th>
            <th class="px-2 py-1 text-right">จำนวน</th>
            <th class="px-2 py-1 text-right">คงเหลือ</th>
            <th class="px-2 py-1 text-left">หมายเหตุ</th>
            <th class="px-2 py-1 text-left">ผู้บันทึก</th>
          </tr>
        </thead>
        <tbody>${txns.map(t => `
          <tr class="border-t">
            <td class="px-2 py-1 text-xs text-slate-500">${(t.created_at||'').replace('T',' ').slice(0,16)}</td>
            <td class="px-2 py-1"><span class="px-2 py-0.5 text-xs rounded ${TYPE_COLOR[t.type]||''}">${TYPE_LABEL[t.type]||t.type}</span></td>
            <td class="px-2 py-1 text-right ${t.amount>=0?'text-emerald-700':'text-rose-700'} font-bold">${t.amount>=0?'+':''}${(+t.amount).toLocaleString()}</td>
            <td class="px-2 py-1 text-right">${(+t.balance_after).toLocaleString()}</td>
            <td class="px-2 py-1 text-xs">${t.notes||'-'}</td>
            <td class="px-2 py-1 text-xs text-slate-600">${t.created_by||'-'}</td>
          </tr>`).join('') || '<tr><td colspan="6" class="text-center py-4 text-slate-500">ยังไม่มีรายการ</td></tr>'}
        </tbody>
      </table>`;

    document.getElementById('btn-topup').addEventListener('click', openTopup);
    document.getElementById('btn-adjust').addEventListener('click', () => document.getElementById('adjust-dialog').showModal());
    document.getElementById('btn-refund').addEventListener('click', () => document.getElementById('refund-dialog').showModal());
}

function openTopup() {
    if (!currentPatientUuid) return alert('เลือกสมาชิกก่อน');
    document.getElementById('topup-dialog').showModal();
}

document.getElementById('topup-form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const r = await api.call(`/members/${currentPatientUuid}/deposit`, {
        method: 'POST',
        body: JSON.stringify({
            amount: +f.amount.value,
            package_name: f.package_name.value || null,
            expires_at: f.expires_at.value || null,
            notes: f.notes.value || null,
        }),
    });
    if (!r.ok) return alert((r.data && r.data.message) || 'ทำไม่ได้');
    f.reset();
    document.getElementById('topup-dialog').close();
    await loadList();
    await loadDetail(currentPatientUuid);
});

document.getElementById('adjust-form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const r = await api.call(`/members/${currentPatientUuid}/adjust`, {
        method: 'POST',
        body: JSON.stringify({ delta: +f.delta.value, reason: f.reason.value }),
    });
    if (!r.ok) return alert((r.data && r.data.message) || 'ทำไม่ได้');
    f.reset();
    document.getElementById('adjust-dialog').close();
    await loadList();
    await loadDetail(currentPatientUuid);
});

document.getElementById('refund-form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const r = await api.call(`/members/${currentPatientUuid}/refund`, {
        method: 'POST',
        body: JSON.stringify({ amount: +f.amount.value, notes: f.notes.value || null }),
    });
    if (!r.ok) return alert((r.data && r.data.message) || 'ทำไม่ได้');
    f.reset();
    document.getElementById('refund-dialog').close();
    await loadList();
    await loadDetail(currentPatientUuid);
});

document.querySelectorAll('.dlg-cancel').forEach(b => b.addEventListener('click', e => {
    e.target.closest('dialog').close();
}));

document.querySelectorAll('#status-filter button').forEach(b => {
    b.addEventListener('click', () => {
        currentStatus = b.dataset.st;
        document.querySelectorAll('#status-filter button').forEach(x => {
            x.className = `text-xs px-2 py-1 rounded ${x.dataset.st === currentStatus ? 'bg-cyan-600 text-white' : 'bg-white border'}`;
        });
        loadList();
    });
});

let searchT;
document.getElementById('search').addEventListener('input', () => {
    clearTimeout(searchT);
    searchT = setTimeout(loadList, 250);
});

async function bootstrap() {
    if (!api.token()) return window.location.href = '/login';
    await loadList();
}
bootstrap();
</script>
@endsection
