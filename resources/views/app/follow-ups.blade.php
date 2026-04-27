@extends('layouts.app')
@section('title', 'ติดตามผล (Follow-up)')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">Follow-up</h1>
            <button id="btn-refresh" class="ml-auto bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded text-sm">⟳ รีเฟรช</button>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <div id="stats" class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4"></div>

        <div class="bg-white rounded-xl shadow p-4 mb-4 flex flex-wrap gap-3 items-center">
            <select id="filter-priority" class="border rounded px-2 py-1.5 text-sm">
                <option value="">— ทุกระดับความเร่งด่วน —</option>
                <option value="critical">วิกฤต (Critical)</option>
                <option value="high">สูง (High)</option>
                <option value="normal">ปกติ (Normal)</option>
                <option value="low">ต่ำ (Low)</option>
            </select>
            <select id="filter-status" class="border rounded px-2 py-1.5 text-sm">
                <option value="">— ทุกสถานะ —</option>
                <option value="pending">รอดำเนินการ</option>
                <option value="contacted">ติดต่อแล้ว</option>
                <option value="scheduled">นัดหมายแล้ว</option>
                <option value="completed">เสร็จสิ้น</option>
                <option value="cancelled">ยกเลิก</option>
            </select>
        </div>

        <div class="bg-white rounded-xl shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left">Priority</th>
                        <th class="px-3 py-2 text-left">ผู้ป่วย</th>
                        <th class="px-3 py-2 text-left">หัตถการ</th>
                        <th class="px-3 py-2 text-left">วันที่นัด</th>
                        <th class="px-3 py-2 text-left">เลย (วัน)</th>
                        <th class="px-3 py-2 text-left">ติดต่อ</th>
                        <th class="px-3 py-2 text-left">สถานะ</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody id="rows"><tr><td colspan="8" class="text-slate-500 text-center p-4">กำลังโหลด...</td></tr></tbody>
            </table>
        </div>
    </main>

    <!-- Quick Book modal -->
    <div id="qb-modal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-5">
            <h2 class="font-bold mb-3">นัดถัดไป (Quick Book)</h2>
            <div id="qb-summary" class="mb-3 p-3 rounded bg-cyan-50 text-sm"></div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="block text-xs mb-1">วันที่ *</label><input id="qb-date" type="date" class="w-full border rounded px-2 py-1.5"></div>
                <div><label class="block text-xs mb-1">ห้อง</label><select id="qb-room" class="w-full border rounded px-2 py-1.5"><option value="">-</option></select></div>
                <div><label class="block text-xs mb-1">เริ่ม *</label><input id="qb-start" type="time" class="w-full border rounded px-2 py-1.5"></div>
                <div><label class="block text-xs mb-1">สิ้นสุด *</label><input id="qb-end" type="time" class="w-full border rounded px-2 py-1.5"></div>
            </div>
            <textarea id="qb-notes" rows="2" class="w-full mt-2 border rounded px-2 py-1.5 text-sm" placeholder="หมายเหตุ"></textarea>
            <div id="qb-error" class="hidden mt-2 p-2 rounded bg-red-50 text-red-700 text-xs whitespace-pre-line"></div>
            <div class="flex justify-end gap-2 mt-3">
                <button id="qb-cancel" class="px-3 py-1.5">ยกเลิก</button>
                <button id="qb-save" class="px-3 py-1.5 rounded bg-cyan-600 hover:bg-cyan-700 text-white">ยืนยันนัด</button>
            </div>
        </div>
    </div>

    <!-- Contact modal -->
    <div id="ct-modal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-5">
            <h2 class="font-bold mb-3">บันทึกการติดต่อ</h2>
            <div id="ct-summary" class="mb-3 p-3 rounded bg-slate-50 text-sm"></div>
            <textarea id="ct-notes" rows="3" class="w-full border rounded px-2 py-1.5 text-sm" placeholder="โน้ตการติดต่อ (ไม่บังคับ)"></textarea>
            <div class="mt-2 text-sm">
                <label class="block text-xs mb-1">เปลี่ยนสถานะเป็น (ไม่บังคับ)</label>
                <select id="ct-status" class="w-full border rounded px-2 py-1.5">
                    <option value="">— ไม่เปลี่ยน —</option>
                    <option value="contacted">Contacted</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div id="ct-error" class="hidden mt-2 p-2 rounded bg-red-50 text-red-700 text-xs"></div>
            <div class="flex justify-end gap-2 mt-3">
                <button id="ct-cancel" class="px-3 py-1.5">ยกเลิก</button>
                <button id="ct-save" class="px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-900 text-white">บันทึก</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const PRIORITY_COLOR = {
    critical: 'bg-red-100 text-red-800',
    high: 'bg-orange-100 text-orange-800',
    normal: 'bg-blue-100 text-blue-800',
    low: 'bg-slate-100 text-slate-700',
};
const PRIORITY_LABEL = { critical: 'วิกฤต', high: 'สูง', normal: 'ปกติ', low: 'ต่ำ' };
const STATUS_LABEL = { pending: 'รอดำเนินการ', contacted: 'ติดต่อแล้ว', scheduled: 'นัดหมายแล้ว', completed: 'เสร็จสิ้น', cancelled: 'ยกเลิก' };
const STATUS_COLOR = {
    pending:   'bg-amber-100 text-amber-800',
    contacted: 'bg-blue-100 text-blue-800',
    scheduled: 'bg-cyan-100 text-cyan-800',
    completed: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-slate-200 text-slate-600',
};

let state = { priority: '', status: '', list: [], rooms: [] };
let qbCurrent = null, ctCurrent = null;

async function loadStats() {
    const r = await api.call('/follow-ups/stats');
    if (!r.ok) return;
    const s = r.data.data;
    document.getElementById('stats').innerHTML = `
        <div class="rounded-lg bg-white shadow p-3 text-center"><div class="text-xs text-slate-500">รวมทั้งหมด</div><div class="font-bold text-2xl">${s.total}</div></div>
        <div class="rounded-lg p-3 text-center ${PRIORITY_COLOR.critical}"><div class="text-xs">${PRIORITY_LABEL.critical}</div><div class="font-bold text-2xl">${s.critical}</div></div>
        <div class="rounded-lg p-3 text-center ${PRIORITY_COLOR.high}"><div class="text-xs">${PRIORITY_LABEL.high}</div><div class="font-bold text-2xl">${s.high}</div></div>
        <div class="rounded-lg p-3 text-center ${PRIORITY_COLOR.normal}"><div class="text-xs">${PRIORITY_LABEL.normal}</div><div class="font-bold text-2xl">${s.normal}</div></div>
        <div class="rounded-lg p-3 text-center ${PRIORITY_COLOR.low}"><div class="text-xs">${PRIORITY_LABEL.low}</div><div class="font-bold text-2xl">${s.low}</div></div>
    `;
}

async function load() {
    const params = new URLSearchParams({ per_page: 100 });
    if (state.priority) params.set('filter.priority', state.priority);
    if (state.status) params.set('filter.status', state.status);
    const r = await api.call('/follow-ups?' + params.toString());
    const rows = document.getElementById('rows');
    if (!r.ok) { rows.innerHTML = '<tr><td colspan="8" class="text-red-600 p-4">โหลดไม่สำเร็จ</td></tr>'; return; }
    state.list = r.data.data;
    if (!state.list.length) { rows.innerHTML = '<tr><td colspan="8" class="text-center p-6 text-slate-500">ไม่มีรายการ</td></tr>'; return; }
    rows.innerHTML = state.list.map(f => `
        <tr class="border-b">
            <td class="px-3 py-2"><span class="px-2 py-1 rounded text-xs font-semibold ${PRIORITY_COLOR[f.priority] || ''}">${PRIORITY_LABEL[f.priority] || f.priority}</span></td>
            <td class="px-3 py-2"><div class="font-semibold">${f.patient?.name || '-'}</div><div class="text-xs text-slate-500">${f.patient?.hn || ''} ${f.patient?.phone ? '• '+f.patient.phone : ''}</div></td>
            <td class="px-3 py-2">${f.procedure?.name || '-'}</td>
            <td class="px-3 py-2 text-xs">${f.follow_up_date}</td>
            <td class="px-3 py-2 ${f.days_overdue > 0 ? 'text-red-600 font-semibold' : 'text-slate-500'}">${f.days_overdue || 0}</td>
            <td class="px-3 py-2 text-xs">${f.contact_attempts}</td>
            <td class="px-3 py-2"><span class="px-2 py-1 rounded text-xs font-semibold ${STATUS_COLOR[f.status] || ''}">${STATUS_LABEL[f.status] || f.status}</span></td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
                ${['scheduled','completed','cancelled'].includes(f.status) ? '<span class="text-xs text-slate-400">—</span>' : `<button data-qb="${f.id}" class="text-cyan-700 hover:underline text-xs font-semibold">📅 นัดถัดไป</button>`}
                <button data-ct="${f.id}" class="text-slate-700 hover:underline text-xs ml-2">📞 ติดต่อ</button>
            </td>
        </tr>`).join('');

    rows.querySelectorAll('[data-qb]').forEach(b => b.addEventListener('click', () => openQuickBook(parseInt(b.dataset.qb))));
    rows.querySelectorAll('[data-ct]').forEach(b => b.addEventListener('click', () => openContact(parseInt(b.dataset.ct))));
}

async function loadRooms() {
    const r = await api.call('/lookups/rooms');
    if (r.ok) {
        state.rooms = r.data.data;
        const sel = document.getElementById('qb-room');
        state.rooms.forEach(rm => sel.insertAdjacentHTML('beforeend', `<option value="${rm.id}">${rm.name}</option>`));
    }
}

function openQuickBook(id) {
    qbCurrent = state.list.find(f => f.id === id);
    if (!qbCurrent) return;
    document.getElementById('qb-summary').innerHTML = `
        <strong>${qbCurrent.patient?.name || ''}</strong> (${qbCurrent.patient?.hn || ''})<br>
        ${qbCurrent.procedure?.name ? 'หัตถการ: '+qbCurrent.procedure.name+'<br>' : ''}
        แพทย์: ${qbCurrent.doctor?.name || '—'}`;
    const tomorrow = new Date(); tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('qb-date').value = tomorrow.toISOString().slice(0, 10);
    document.getElementById('qb-start').value = '10:00';
    document.getElementById('qb-end').value = '10:30';
    document.getElementById('qb-notes').value = '';
    document.getElementById('qb-error').classList.add('hidden');
    document.getElementById('qb-modal').classList.remove('hidden');
}

document.getElementById('qb-cancel').addEventListener('click', () => document.getElementById('qb-modal').classList.add('hidden'));
document.getElementById('qb-save').addEventListener('click', async () => {
    if (!qbCurrent) return;
    const payload = {
        follow_up_id: qbCurrent.id,
        appointment_date: document.getElementById('qb-date').value,
        start_time: document.getElementById('qb-start').value,
        end_time: document.getElementById('qb-end').value,
        room_id: document.getElementById('qb-room').value ? parseInt(document.getElementById('qb-room').value) : null,
        notes: document.getElementById('qb-notes').value || null,
    };
    const r = await api.call('/appointments/quick-create', { method: 'POST', body: JSON.stringify(payload) });
    const errEl = document.getElementById('qb-error');
    if (!r.ok) {
        let msg = (r.data && r.data.message) || 'นัดไม่สำเร็จ';
        if (r.data && r.data.errors) msg += '\n' + Object.values(r.data.errors).flat().join('\n');
        errEl.textContent = msg; errEl.classList.remove('hidden');
        return;
    }
    document.getElementById('qb-modal').classList.add('hidden');
    refresh();
});

function openContact(id) {
    ctCurrent = state.list.find(f => f.id === id);
    if (!ctCurrent) return;
    document.getElementById('ct-summary').innerHTML = `
        <strong>${ctCurrent.patient?.name || ''}</strong> (${ctCurrent.patient?.hn || ''})<br>
        ติดต่อแล้ว: ${ctCurrent.contact_attempts} ครั้ง`;
    document.getElementById('ct-notes').value = '';
    document.getElementById('ct-status').value = '';
    document.getElementById('ct-error').classList.add('hidden');
    document.getElementById('ct-modal').classList.remove('hidden');
}

document.getElementById('ct-cancel').addEventListener('click', () => document.getElementById('ct-modal').classList.add('hidden'));
document.getElementById('ct-save').addEventListener('click', async () => {
    if (!ctCurrent) return;
    const payload = {
        notes: document.getElementById('ct-notes').value || null,
        mark_status: document.getElementById('ct-status').value || null,
    };
    const r = await api.call(`/follow-ups/${ctCurrent.id}/contact`, { method: 'POST', body: JSON.stringify(payload) });
    const errEl = document.getElementById('ct-error');
    if (!r.ok) {
        let msg = (r.data && r.data.message) || 'บันทึกไม่ได้';
        if (r.data && r.data.errors) msg += '\n' + Object.values(r.data.errors).flat().join('\n');
        errEl.textContent = msg; errEl.classList.remove('hidden');
        return;
    }
    document.getElementById('ct-modal').classList.add('hidden');
    refresh();
});

document.getElementById('filter-priority').addEventListener('change', e => { state.priority = e.target.value; load(); });
document.getElementById('filter-status').addEventListener('change', e => { state.status = e.target.value; load(); });
document.getElementById('btn-refresh').addEventListener('click', refresh);

async function refresh() { await Promise.all([loadStats(), load()]); }

if (!api.token()) window.location.href = '/login';
else { loadRooms().then(refresh); }
</script>
@endsection
