@extends('layouts.app')
@section('title', 'POS')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">POS</h1>
            <button id="btn-new" class="ml-auto bg-cyan-600 hover:bg-cyan-700 text-white px-3 py-1.5 rounded text-sm font-semibold">+ เปิด Visit ใหม่</button>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6 grid lg:grid-cols-3 gap-4">
        <!-- Active visits today -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow p-4">
                <h2 class="font-semibold mb-3">Visits วันนี้</h2>
                <div id="visit-list" class="space-y-2 text-sm">กำลังโหลด...</div>
            </div>
        </div>

        <!-- Active visit detail -->
        <div class="lg:col-span-2">
            <div id="active-visit" class="bg-white rounded-xl shadow p-5 min-h-[200px]">
                <em class="text-slate-500">เลือก Visit ทางซ้ายเพื่อเริ่ม</em>
            </div>
        </div>
    </main>

    <!-- New visit modal -->
    <div id="new-modal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-5">
            <h2 class="font-bold mb-3">เปิด Visit ใหม่</h2>
            <input id="patient-search" placeholder="ค้นหา HN, ชื่อ, เบอร์โทร..." class="w-full border rounded px-2 py-1.5">
            <div id="patient-results" class="mt-1 border rounded max-h-40 overflow-y-auto hidden"></div>
            <div id="patient-selected" class="mt-2 hidden p-2 bg-cyan-50 rounded text-sm"></div>
            <input type="hidden" id="patient_uuid">
            <div class="mt-3 grid grid-cols-2 gap-2">
                <select id="doctor_id" class="border rounded px-2 py-1.5"><option value="">— แพทย์ —</option></select>
                <select id="room_id" class="border rounded px-2 py-1.5"><option value="">— ห้อง —</option></select>
            </div>
            <textarea id="chief_complaint" rows="2" class="w-full mt-2 border rounded px-2 py-1.5" placeholder="อาการหลัก"></textarea>
            <div id="new-error" class="hidden p-2 mt-2 rounded bg-red-50 text-red-700 text-xs"></div>
            <div class="flex justify-end gap-2 mt-3">
                <button id="new-cancel" class="px-3 py-1.5 text-slate-700">ยกเลิก</button>
                <button id="new-save" class="px-3 py-1.5 rounded bg-cyan-600 hover:bg-cyan-700 text-white">เริ่ม Visit</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let lookups = { doctors: [], rooms: [], procedures: [] };
let activeVisitId = null;
let currentVisit = null;

async function loadLookups() {
    const [d, r, p] = await Promise.all([
        api.call('/lookups/doctors'),
        api.call('/lookups/rooms'),
        api.call('/lookups/procedures'),
    ]);
    if (d.ok) lookups.doctors = d.data.data;
    if (r.ok) lookups.rooms = r.data.data;
    if (p.ok) lookups.procedures = p.data.data;
    const dd = document.getElementById('doctor_id');
    lookups.doctors.forEach(x => dd.insertAdjacentHTML('beforeend', `<option value="${x.id}">${x.name}</option>`));
    const rr = document.getElementById('room_id');
    lookups.rooms.forEach(x => rr.insertAdjacentHTML('beforeend', `<option value="${x.id}">${x.name}</option>`));
}

async function loadVisits() {
    const r = await api.call('/visits/today');
    const list = document.getElementById('visit-list');
    if (!r.ok) { list.innerHTML = '<div class="text-red-600">โหลดไม่สำเร็จ</div>'; return; }
    if (!r.data.data.length) { list.innerHTML = '<em class="text-slate-500">ยังไม่มี Visit วันนี้</em>'; return; }
    const VS_LABEL = { waiting: 'รอเข้าตรวจ', in_progress: 'กำลังรักษา', completed: 'เสร็จสิ้น', cancelled: 'ยกเลิก' };
    const VS_COLOR = {
        waiting:     'bg-amber-100 text-amber-800',
        in_progress: 'bg-blue-100 text-blue-800',
        completed:   'bg-emerald-100 text-emerald-800',
        cancelled:   'bg-slate-200 text-slate-600',
    };
    list.innerHTML = r.data.data.map(v => `
        <button data-id="${v.id}" class="visit-btn w-full text-left border rounded p-2 hover:bg-slate-50 ${v.id === activeVisitId ? 'border-cyan-500 bg-cyan-50' : ''}">
            <div class="flex items-center gap-2">
                <span class="font-mono text-xs text-slate-500">${v.visit_number}</span>
                <span class="ml-auto px-2 py-0.5 rounded text-xs font-semibold ${VS_COLOR[v.status] || ''}">${VS_LABEL[v.status] || v.status}</span>
            </div>
            <div class="font-semibold mt-1">${v.patient.name}</div>
            <div class="text-xs text-slate-500">${v.patient.hn}${v.doctor?.name ? ' • '+v.doctor.name : ''}</div>
        </button>`).join('');
    list.querySelectorAll('.visit-btn').forEach(b => b.addEventListener('click', () => openVisit(b.dataset.id)));
}

async function openVisit(uuid) {
    activeVisitId = uuid;
    const r = await api.call('/visits/' + uuid);
    if (!r.ok) { document.getElementById('active-visit').innerHTML = '<div class="text-red-600">โหลดไม่สำเร็จ</div>'; return; }
    currentVisit = r.data.data;
    renderActive();
    loadVisits();
}

function renderActive() {
    if (!currentVisit) return;
    const v = currentVisit;
    const isPaid = v.invoice?.status !== 'draft';
    const items = v.invoice?.items || [];
    const itemsHtml = items.length ? items.map(i => `
        <tr class="border-b">
            <td class="px-2 py-1">${i.item_name}</td>
            <td class="px-2 py-1 text-right">${i.quantity}</td>
            <td class="px-2 py-1 text-right">${(+i.unit_price).toLocaleString()}</td>
            <td class="px-2 py-1 text-right">${(+i.total).toLocaleString()}</td>
            <td class="px-2 py-1 text-right">${isPaid ? '' : `<button data-rm="${i.id}" class="text-red-600 text-xs">ลบ</button>`}</td>
        </tr>`).join('') : '<tr><td colspan="5" class="text-center p-3 text-slate-500">ยังไม่มีรายการ</td></tr>';

    document.getElementById('active-visit').innerHTML = `
        <div class="flex items-start gap-4 mb-3">
            <div>
                <div class="font-mono text-xs text-slate-500">${v.visit_number}</div>
                <div class="font-bold text-lg">${v.patient.name}</div>
                <div class="text-xs">HN: ${v.patient.hn} • ${v.patient.phone || ''}</div>
            </div>
            <div class="ml-auto text-right space-y-1">
                ${(() => {
                    const VL = { waiting: 'รอเข้าตรวจ', in_progress: 'กำลังรักษา', completed: 'เสร็จสิ้น', cancelled: 'ยกเลิก' };
                    const VC = { waiting: 'bg-amber-100 text-amber-800', in_progress: 'bg-blue-100 text-blue-800', completed: 'bg-emerald-100 text-emerald-800', cancelled: 'bg-slate-200 text-slate-600' };
                    const IL = { draft: 'แบบร่าง', paid: 'ชำระแล้ว', partial: 'ชำระบางส่วน', voided: 'ยกเลิก', refunded: 'คืนเงินแล้ว' };
                    const IC = { draft: 'bg-slate-100 text-slate-700', paid: 'bg-emerald-100 text-emerald-800', partial: 'bg-amber-100 text-amber-800', voided: 'bg-red-100 text-red-800', refunded: 'bg-orange-100 text-orange-800' };
                    const inv = v.invoice?.status;
                    return `
                        <div class="text-xs text-slate-500">สถานะ Visit</div>
                        <div><span class="inline-block px-2 py-1 rounded text-xs font-semibold ${VC[v.status] || ''}">${VL[v.status] || v.status}</span></div>
                        <div class="text-xs text-slate-500 mt-1">บิล</div>
                        <div>${inv ? `<span class="inline-block px-2 py-1 rounded text-xs font-semibold ${IC[inv] || ''}">${IL[inv] || inv}</span>` : '-'}</div>
                    `;
                })()}
            </div>
        </div>

        <h3 class="font-semibold mb-2">รายการในบิล</h3>
        <div class="border rounded mb-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50"><tr>
                    <th class="text-left px-2 py-1">รายการ</th>
                    <th class="text-right px-2 py-1">จำนวน</th>
                    <th class="text-right px-2 py-1">ราคา</th>
                    <th class="text-right px-2 py-1">รวม</th>
                    <th></th>
                </tr></thead>
                <tbody>${itemsHtml}</tbody>
                <tfoot><tr class="bg-slate-50 font-semibold">
                    <td class="px-2 py-2" colspan="3">รวมทั้งสิ้น</td>
                    <td class="px-2 py-2 text-right">${(+v.invoice?.total_amount || 0).toLocaleString()}</td>
                    <td></td>
                </tr></tfoot>
            </table>
        </div>

        ${isPaid ? `<div class="rounded bg-emerald-50 text-emerald-800 p-3 text-sm">บิลปิดแล้ว — ${v.invoice.invoice_number}</div>` : `
        <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-4">
            <select id="proc-pick" class="border rounded px-2 py-1.5 md:col-span-2">
                <option value="">— เลือกหัตถการ —</option>
                ${lookups.procedures.map(p => `<option value="${p.id}" data-price="${p.price}">${p.code} — ${p.name} (${(+p.price).toLocaleString()})</option>`).join('')}
            </select>
            <button id="add-proc" class="bg-slate-800 text-white rounded px-3 py-1.5">+ เพิ่ม</button>
        </div>

        <h3 class="font-semibold mb-2">ชำระเงิน</h3>
        <div class="flex flex-wrap gap-2 mb-2">
            <select id="pay-method" class="border rounded px-2 py-1.5">
                <option value="cash">เงินสด</option>
                <option value="credit_card">บัตรเครดิต</option>
                <option value="transfer">โอน</option>
                <option value="member_credit">Member</option>
            </select>
            <input id="pay-amount" type="number" min="0.01" step="0.01" class="border rounded px-2 py-1.5 w-32" placeholder="ยอด">
            <button id="checkout" class="bg-emerald-600 hover:bg-emerald-700 text-white rounded px-4 py-1.5 font-semibold">ปิดบิล</button>
        </div>
        <div id="pos-error" class="hidden mt-2 p-2 bg-red-50 text-red-700 rounded text-sm"></div>
        `}
    `;

    if (!isPaid) {
        document.getElementById('add-proc').addEventListener('click', addProcedure);
        document.querySelectorAll('[data-rm]').forEach(b => b.addEventListener('click', () => removeItem(b.dataset.rm)));
        document.getElementById('checkout').addEventListener('click', checkout);
        const total = +(v.invoice?.total_amount || 0);
        document.getElementById('pay-amount').value = total > 0 ? total : '';
    }
}

async function addProcedure() {
    const sel = document.getElementById('proc-pick');
    if (!sel.value) return;
    const r = await api.call('/visits/' + currentVisit.id + '/invoice-items', {
        method: 'POST',
        body: JSON.stringify({ item_type: 'procedure', item_id: parseInt(sel.value), quantity: 1 }),
    });
    if (!r.ok) { alert((r.data && r.data.message) || 'เพิ่มไม่ได้'); return; }
    openVisit(currentVisit.id);
}

async function removeItem(itemId) {
    if (!confirm('ลบรายการ?')) return;
    const r = await api.call(`/visits/${currentVisit.id}/invoice-items/${itemId}`, { method: 'DELETE' });
    if (!r.ok) return alert('ลบไม่ได้');
    openVisit(currentVisit.id);
}

async function checkout() {
    const method = document.getElementById('pay-method').value;
    const amount = parseFloat(document.getElementById('pay-amount').value || '0');
    const errEl = document.getElementById('pos-error');
    errEl.classList.add('hidden');
    const r = await api.call(`/visits/${currentVisit.id}/checkout`, {
        method: 'POST',
        body: JSON.stringify({ payments: [{ method, amount }] }),
    });
    if (!r.ok) {
        let msg = (r.data && r.data.message) || 'ปิดบิลไม่ได้';
        if (r.data && r.data.errors) msg += '\n' + Object.values(r.data.errors).flat().join('\n');
        errEl.textContent = msg; errEl.classList.remove('hidden');
        return;
    }
    openVisit(currentVisit.id);
}

// New visit modal
let searchTimer;
document.getElementById('btn-new').addEventListener('click', () => {
    document.getElementById('patient-search').value = '';
    document.getElementById('patient_uuid').value = '';
    document.getElementById('patient-selected').classList.add('hidden');
    document.getElementById('chief_complaint').value = '';
    document.getElementById('new-error').classList.add('hidden');
    document.getElementById('new-modal').classList.remove('hidden');
});
document.getElementById('new-cancel').addEventListener('click', () => document.getElementById('new-modal').classList.add('hidden'));

document.getElementById('patient-search').addEventListener('input', e => {
    clearTimeout(searchTimer);
    const q = e.target.value.trim();
    if (q.length < 2) { document.getElementById('patient-results').classList.add('hidden'); return; }
    searchTimer = setTimeout(async () => {
        const r = await api.call('/patients?per_page=10&search=' + encodeURIComponent(q));
        const el = document.getElementById('patient-results');
        if (!r.ok || !r.data.data.length) { el.innerHTML = '<div class="p-2 text-slate-500 text-sm">ไม่พบ</div>'; el.classList.remove('hidden'); return; }
        el.innerHTML = r.data.data.map(p => `<div data-uuid="${p.id}" data-label="${p.hn} — ${p.first_name} ${p.last_name}" class="p-2 hover:bg-slate-50 cursor-pointer text-sm">${p.hn} — ${p.first_name} ${p.last_name}</div>`).join('');
        el.classList.remove('hidden');
        el.querySelectorAll('[data-uuid]').forEach(d => d.addEventListener('click', () => {
            document.getElementById('patient_uuid').value = d.dataset.uuid;
            const sel = document.getElementById('patient-selected');
            sel.textContent = 'เลือก: ' + d.dataset.label;
            sel.classList.remove('hidden');
            el.classList.add('hidden');
            document.getElementById('patient-search').value = '';
        }));
    }, 250);
});

document.getElementById('new-save').addEventListener('click', async () => {
    const payload = {
        patient_uuid: document.getElementById('patient_uuid').value,
        doctor_id: document.getElementById('doctor_id').value ? parseInt(document.getElementById('doctor_id').value) : null,
        room_id: document.getElementById('room_id').value ? parseInt(document.getElementById('room_id').value) : null,
        chief_complaint: document.getElementById('chief_complaint').value || null,
    };
    if (!payload.patient_uuid) { return alert('เลือกผู้ป่วยก่อน'); }
    const r = await api.call('/visits', { method: 'POST', body: JSON.stringify(payload) });
    const errEl = document.getElementById('new-error');
    if (!r.ok) {
        let msg = (r.data && r.data.message) || 'เปิด Visit ไม่ได้';
        if (r.data && r.data.errors) msg += '\n' + Object.values(r.data.errors).flat().join('\n');
        errEl.textContent = msg; errEl.classList.remove('hidden');
        return;
    }
    document.getElementById('new-modal').classList.add('hidden');
    activeVisitId = r.data.data.id;
    await loadVisits();
    openVisit(activeVisitId);
});

if (!api.token()) window.location.href = '/login';
else { loadLookups().then(loadVisits); }
</script>
@endsection
