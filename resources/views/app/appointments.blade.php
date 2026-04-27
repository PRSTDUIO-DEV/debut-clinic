@extends('layouts.app')
@section('title', 'นัดหมาย')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">นัดหมาย</h1>
            <div class="ml-auto flex items-center gap-2">
                <button id="prev-day" class="px-3 py-1.5 border rounded">◀</button>
                <span id="cur-date" class="font-semibold min-w-[150px] text-center"></span>
                <button id="next-day" class="px-3 py-1.5 border rounded">▶</button>
                <select id="view-mode" class="border rounded px-2 py-1.5">
                    <option value="day">รายวัน</option>
                    <option value="week">รายสัปดาห์</option>
                </select>
                <select id="filter-doctor" class="border rounded px-2 py-1.5">
                    <option value="">— ทุกแพทย์ —</option>
                </select>
                <button id="btn-new" class="bg-cyan-600 hover:bg-cyan-700 text-white px-3 py-1.5 rounded text-sm font-semibold">+ นัดใหม่</button>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <div id="legend" class="mb-3 flex flex-wrap gap-2 text-xs">
            <span class="px-2 py-1 rounded bg-amber-100 text-amber-800">รอ</span>
            <span class="px-2 py-1 rounded bg-blue-100 text-blue-800">ยืนยัน</span>
            <span class="px-2 py-1 rounded bg-emerald-100 text-emerald-800">มาแล้ว</span>
            <span class="px-2 py-1 rounded bg-slate-200 text-slate-700">เสร็จ</span>
            <span class="px-2 py-1 rounded bg-red-100 text-red-800">ยกเลิก / ไม่มา</span>
            <span class="ml-auto text-slate-500">📅 นัดที่มาจากติดตามผลจะมีกรอบเขียว</span>
        </div>
        <div id="board" class="bg-white rounded-xl shadow p-4 min-h-[400px]">กำลังโหลด...</div>
    </main>

    <!-- Modal -->
    <div id="modal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-5 border-b flex items-center">
                <h2 id="modal-title" class="font-bold text-lg">นัดหมายใหม่</h2>
                <button id="modal-close" class="ml-auto text-slate-400 hover:text-slate-700 text-xl">×</button>
            </div>
            <form id="form" class="p-5 grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs mb-1">ค้นหาผู้ป่วย *</label>
                    <input id="patient-search" placeholder="ค้นหา HN, ชื่อ, เบอร์โทร..." class="w-full border rounded px-2 py-1.5">
                    <div id="patient-results" class="mt-1 border rounded max-h-40 overflow-y-auto hidden"></div>
                    <div id="patient-selected" class="mt-2 hidden p-2 bg-cyan-50 rounded text-sm"></div>
                    <input type="hidden" id="patient_uuid">
                </div>
                <div><label class="block text-xs mb-1">แพทย์ *</label><select id="doctor_id" required class="w-full border rounded px-2 py-1.5"></select></div>
                <div><label class="block text-xs mb-1">ห้อง</label><select id="room_id" class="w-full border rounded px-2 py-1.5"><option value="">-</option></select></div>
                <div><label class="block text-xs mb-1">หัตถการ</label><select id="procedure_id" class="w-full border rounded px-2 py-1.5"><option value="">-</option></select></div>
                <div><label class="block text-xs mb-1">วันที่ *</label><input id="appointment_date" type="date" required class="w-full border rounded px-2 py-1.5"></div>
                <div><label class="block text-xs mb-1">เริ่ม *</label><input id="start_time" type="time" required class="w-full border rounded px-2 py-1.5"></div>
                <div><label class="block text-xs mb-1">สิ้นสุด *</label><input id="end_time" type="time" required class="w-full border rounded px-2 py-1.5"></div>
                <div class="col-span-2"><label class="block text-xs mb-1">หมายเหตุ</label><textarea id="notes" rows="2" class="w-full border rounded px-2 py-1.5"></textarea></div>
                <div id="form-error" class="hidden col-span-2 p-3 rounded bg-red-50 text-red-700 text-sm whitespace-pre-line"></div>
                <div class="col-span-2 flex justify-end gap-2 mt-2">
                    <button type="button" id="cancel" class="px-4 py-2 text-slate-700">ยกเลิก</button>
                    <button type="submit" id="save-btn" class="px-4 py-2 rounded bg-cyan-600 hover:bg-cyan-700 text-white">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const STATUS_COLOR = {
    pending: 'bg-amber-100 text-amber-800 border-amber-200',
    confirmed: 'bg-blue-100 text-blue-800 border-blue-200',
    arrived: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    completed: 'bg-slate-200 text-slate-700 border-slate-300',
    cancelled: 'bg-red-100 text-red-800 border-red-200',
    no_show: 'bg-red-100 text-red-800 border-red-200',
};
const STATUS_LABEL = {
    pending: 'รอ', confirmed: 'ยืนยัน', arrived: 'มาแล้ว', completed: 'เสร็จ', cancelled: 'ยกเลิก', no_show: 'ไม่มา',
};

let state = { date: new Date().toISOString().slice(0,10), view: 'day', doctorId: '', doctors: [], rooms: [], procedures: [] };

async function loadLookups() {
    const [d, r, p] = await Promise.all([
        api.call('/lookups/doctors'),
        api.call('/lookups/rooms'),
        api.call('/lookups/procedures'),
    ]);
    if (d.ok) state.doctors = d.data.data;
    if (r.ok) state.rooms = r.data.data;
    if (p.ok) state.procedures = p.data.data;

    const doctorSelect = document.getElementById('doctor_id');
    const filterDoc = document.getElementById('filter-doctor');
    state.doctors.forEach(doc => {
        doctorSelect.insertAdjacentHTML('beforeend', `<option value="${doc.id}">${doc.name}</option>`);
        filterDoc.insertAdjacentHTML('beforeend', `<option value="${doc.id}">${doc.name}</option>`);
    });
    const roomSelect = document.getElementById('room_id');
    state.rooms.forEach(r => roomSelect.insertAdjacentHTML('beforeend', `<option value="${r.id}">${r.name}</option>`));
    const procSelect = document.getElementById('procedure_id');
    state.procedures.forEach(p => procSelect.insertAdjacentHTML('beforeend', `<option value="${p.id}">${p.code} — ${p.name}</option>`));
}

function dateStr(d) { return d.toLocaleDateString('th-TH', { dateStyle: 'full' }); }

async function load() {
    document.getElementById('cur-date').textContent = dateStr(new Date(state.date));
    const params = new URLSearchParams();
    if (state.view === 'day') {
        params.set('date_from', state.date); params.set('date_to', state.date);
    } else {
        const d = new Date(state.date);
        const day = d.getDay();
        const diffToMon = (day + 6) % 7;
        const start = new Date(d); start.setDate(d.getDate() - diffToMon);
        const end = new Date(start); end.setDate(start.getDate() + 6);
        params.set('date_from', start.toISOString().slice(0,10));
        params.set('date_to', end.toISOString().slice(0,10));
    }
    if (state.doctorId) params.set('filter.doctor_id', state.doctorId);
    params.set('per_page', 200);

    const r = await api.call('/appointments?' + params.toString());
    const board = document.getElementById('board');
    if (!r.ok) { board.innerHTML = '<div class="text-red-700">โหลดไม่สำเร็จ</div>'; return; }

    const groupBy = (arr, key) => arr.reduce((acc, x) => ((acc[key(x)] = acc[key(x)] || []).push(x), acc), {});
    const byDate = groupBy(r.data.data, a => a.appointment_date);

    if (state.view === 'day') {
        const list = byDate[state.date] || [];
        if (!list.length) { board.innerHTML = '<div class="text-slate-500 text-center py-10">ไม่มีนัดในวันนี้</div>'; return; }
        board.innerHTML = `<div class="grid gap-2">${list.sort((a,b) => a.start_time.localeCompare(b.start_time)).map(renderCard).join('')}</div>`;
    } else {
        const days = Object.keys(byDate).sort();
        if (!days.length) { board.innerHTML = '<div class="text-slate-500 text-center py-10">ไม่มีนัดในสัปดาห์นี้</div>'; return; }
        board.innerHTML = days.map(d => `
            <div class="mb-4">
                <div class="font-semibold mb-2">${dateStr(new Date(d))} (${byDate[d].length} นัด)</div>
                <div class="grid gap-2">${byDate[d].sort((a,b) => a.start_time.localeCompare(b.start_time)).map(renderCard).join('')}</div>
            </div>`).join('');
    }
    bindStatusButtons();
}

function renderCard(a) {
    const cls = STATUS_COLOR[a.status] || '';
    const fromFollowUp = a.source === 'follow_up';
    return `<div class="border rounded p-3 ${cls} ${fromFollowUp ? 'ring-2 ring-emerald-400' : ''}">
        <div class="flex items-center gap-3">
            <div class="font-mono text-xs">${a.start_time.slice(0,5)} - ${a.end_time.slice(0,5)}</div>
            <div class="font-semibold">${a.patient.name}</div>
            <div class="text-xs">HN: ${a.patient.hn}</div>
            ${fromFollowUp ? '<span class="text-xs px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">📅 จากติดตามผล</span>' : ''}
            <div class="text-xs ml-auto">แพทย์: ${a.doctor.name}${a.room ? ' / ห้อง: '+a.room.name : ''}</div>
        </div>
        <div class="mt-2 flex flex-wrap gap-1">
            ${['pending','confirmed','arrived','completed','cancelled','no_show'].map(s =>
                `<button data-id="${a.id}" data-status="${s}" class="status-btn text-xs px-2 py-0.5 rounded ${a.status === s ? 'bg-white font-bold' : 'bg-white/40 hover:bg-white'}">${STATUS_LABEL[s] || s}</button>`
            ).join('')}
        </div>
        ${a.notes ? `<div class="mt-2 text-xs text-slate-600">${a.notes}</div>` : ''}
    </div>`;
}

function bindStatusButtons() {
    document.querySelectorAll('.status-btn').forEach(b => b.addEventListener('click', async () => {
        const r = await api.call(`/appointments/${b.dataset.id}/status`, { method: 'PATCH', body: JSON.stringify({ status: b.dataset.status }) });
        if (!r.ok) return alert((r.data && r.data.message) || 'เปลี่ยนสถานะไม่ได้');
        load();
    }));
}

document.getElementById('view-mode').addEventListener('change', e => { state.view = e.target.value; load(); });
document.getElementById('filter-doctor').addEventListener('change', e => { state.doctorId = e.target.value; load(); });
document.getElementById('prev-day').addEventListener('click', () => { const d = new Date(state.date); d.setDate(d.getDate() - (state.view === 'week' ? 7 : 1)); state.date = d.toISOString().slice(0,10); load(); });
document.getElementById('next-day').addEventListener('click', () => { const d = new Date(state.date); d.setDate(d.getDate() + (state.view === 'week' ? 7 : 1)); state.date = d.toISOString().slice(0,10); load(); });

// Modal
let patientSearchTimer;
document.getElementById('btn-new').addEventListener('click', () => {
    document.getElementById('form').reset();
    document.getElementById('patient_uuid').value = '';
    document.getElementById('patient-selected').classList.add('hidden');
    document.getElementById('appointment_date').value = state.date;
    document.getElementById('form-error').classList.add('hidden');
    document.getElementById('modal').classList.remove('hidden');
});
document.getElementById('modal-close').addEventListener('click', () => document.getElementById('modal').classList.add('hidden'));
document.getElementById('cancel').addEventListener('click', () => document.getElementById('modal').classList.add('hidden'));

document.getElementById('patient-search').addEventListener('input', (e) => {
    clearTimeout(patientSearchTimer);
    const q = e.target.value.trim();
    if (q.length < 2) { document.getElementById('patient-results').classList.add('hidden'); return; }
    patientSearchTimer = setTimeout(async () => {
        const r = await api.call('/patients?per_page=10&search=' + encodeURIComponent(q));
        const el = document.getElementById('patient-results');
        if (!r.ok) return;
        if (!r.data.data.length) { el.innerHTML = '<div class="p-2 text-slate-500 text-sm">ไม่พบข้อมูล</div>'; el.classList.remove('hidden'); return; }
        el.innerHTML = r.data.data.map(p => `<div data-uuid="${p.id}" data-name="${p.first_name} ${p.last_name}" data-hn="${p.hn}" class="p-2 hover:bg-slate-50 cursor-pointer text-sm">${p.hn} — ${p.first_name} ${p.last_name} ${p.phone ? '('+p.phone+')' : ''}</div>`).join('');
        el.classList.remove('hidden');
        el.querySelectorAll('[data-uuid]').forEach(d => d.addEventListener('click', () => {
            document.getElementById('patient_uuid').value = d.dataset.uuid;
            const sel = document.getElementById('patient-selected');
            sel.textContent = `เลือก: ${d.dataset.hn} — ${d.dataset.name}`;
            sel.classList.remove('hidden');
            el.classList.add('hidden');
            document.getElementById('patient-search').value = '';
        }));
    }, 250);
});

document.getElementById('form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
        patient_uuid: document.getElementById('patient_uuid').value,
        doctor_id: parseInt(document.getElementById('doctor_id').value),
        room_id: document.getElementById('room_id').value ? parseInt(document.getElementById('room_id').value) : null,
        procedure_id: document.getElementById('procedure_id').value ? parseInt(document.getElementById('procedure_id').value) : null,
        appointment_date: document.getElementById('appointment_date').value,
        start_time: document.getElementById('start_time').value,
        end_time: document.getElementById('end_time').value,
        notes: document.getElementById('notes').value || null,
    };
    if (!payload.patient_uuid) { return alert('เลือกผู้ป่วยก่อน'); }
    const r = await api.call('/appointments', { method: 'POST', body: JSON.stringify(payload) });
    const errEl = document.getElementById('form-error');
    if (!r.ok) {
        let msg = (r.data && r.data.message) || 'บันทึกไม่สำเร็จ';
        if (r.data && r.data.errors) msg += '\n' + Object.values(r.data.errors).flat().join('\n');
        errEl.textContent = msg; errEl.classList.remove('hidden');
        return;
    }
    document.getElementById('modal').classList.add('hidden');
    load();
});

if (!api.token()) window.location.href = '/login';
else { loadLookups().then(load); }
</script>
@endsection
