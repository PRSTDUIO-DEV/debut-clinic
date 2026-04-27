@extends('layouts.app')
@section('title', 'ผู้ป่วย')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">ผู้ป่วย</h1>
            <button id="btn-new" class="ml-auto bg-cyan-600 hover:bg-cyan-700 text-white px-4 py-2 rounded text-sm font-semibold">+ เพิ่มผู้ป่วย</button>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <div class="bg-white rounded-xl shadow p-4 mb-4">
            <input id="search" type="text" placeholder="ค้นหา HN, ชื่อ, นามสกุล, เบอร์โทร, LINE ID..."
                class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:border-cyan-500 focus:ring-cyan-500">
        </div>

        <div class="bg-white rounded-xl shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left">HN</th>
                        <th class="px-3 py-2 text-left">ชื่อ-นามสกุล</th>
                        <th class="px-3 py-2 text-left">เพศ</th>
                        <th class="px-3 py-2 text-left">เบอร์โทร</th>
                        <th class="px-3 py-2 text-left">มาล่าสุด</th>
                        <th class="px-3 py-2 text-left">ยอดสะสม</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody id="rows"><tr><td colspan="7" class="text-center text-slate-500 p-6">กำลังโหลด...</td></tr></tbody>
            </table>
        </div>

        <div id="pagination" class="mt-4 flex items-center gap-2 text-sm"></div>
    </main>

    <!-- Modal -->
    <div id="modal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-5 border-b flex items-center">
                <h2 id="modal-title" class="font-bold text-lg">เพิ่มผู้ป่วย</h2>
                <button id="modal-close" class="ml-auto text-slate-400 hover:text-slate-700 text-xl">×</button>
            </div>
            <form id="form" class="p-5 grid grid-cols-2 gap-4">
                <input id="patient-uuid" type="hidden">
                <div><label class="block text-xs mb-1">คำนำหน้า</label><input id="prefix" class="w-full border rounded px-2 py-1.5"></div>
                <div></div>
                <div><label class="block text-xs mb-1">ชื่อ *</label><input id="first_name" required class="w-full border rounded px-2 py-1.5"></div>
                <div><label class="block text-xs mb-1">นามสกุล *</label><input id="last_name" required class="w-full border rounded px-2 py-1.5"></div>
                <div><label class="block text-xs mb-1">ชื่อเล่น</label><input id="nickname" class="w-full border rounded px-2 py-1.5"></div>
                <div><label class="block text-xs mb-1">เพศ *</label>
                    <select id="gender" class="w-full border rounded px-2 py-1.5">
                        <option value="male">ชาย</option><option value="female">หญิง</option><option value="other">อื่น ๆ</option>
                    </select>
                </div>
                <div><label class="block text-xs mb-1">วันเกิด</label><input id="date_of_birth" type="date" class="w-full border rounded px-2 py-1.5"></div>
                <div><label class="block text-xs mb-1">เบอร์โทร</label><input id="phone" class="w-full border rounded px-2 py-1.5"></div>
                <div><label class="block text-xs mb-1">Email</label><input id="email" type="email" class="w-full border rounded px-2 py-1.5"></div>
                <div><label class="block text-xs mb-1">LINE ID</label><input id="line_id" class="w-full border rounded px-2 py-1.5"></div>
                <div class="col-span-2"><label class="block text-xs mb-1">ที่อยู่</label><textarea id="address" rows="2" class="w-full border rounded px-2 py-1.5"></textarea></div>
                <div class="col-span-2"><label class="block text-xs mb-1">แพ้ยา/อาการแพ้</label><textarea id="allergies" rows="2" class="w-full border rounded px-2 py-1.5"></textarea></div>
                <div class="col-span-2"><label class="block text-xs mb-1">โรคประจำตัว</label><textarea id="underlying_diseases" rows="2" class="w-full border rounded px-2 py-1.5"></textarea></div>
                <div id="form-error" class="hidden col-span-2 p-3 rounded bg-red-50 text-red-700 text-sm"></div>
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
let state = { page: 1, perPage: 20, search: '', total: 0, lastPage: 1 };
let editingId = null;

function fmtDate(s) { return s ? new Date(s).toLocaleDateString('th-TH', { dateStyle: 'medium' }) : '—'; }

async function load() {
    const params = new URLSearchParams({ page: state.page, per_page: state.perPage });
    if (state.search) params.set('search', state.search);
    const r = await api.call('/patients?'+params.toString());
    const rows = document.getElementById('rows');
    if (!r.ok) { rows.innerHTML = `<tr><td colspan="7" class="text-red-600 p-4">โหลดไม่สำเร็จ</td></tr>`; return; }
    state.total = r.data.meta.total;
    state.lastPage = r.data.meta.last_page;
    if (!r.data.data.length) { rows.innerHTML = `<tr><td colspan="7" class="text-center text-slate-500 p-6">ไม่พบข้อมูล</td></tr>`; renderPager(); return; }
    rows.innerHTML = r.data.data.map(p => `
        <tr class="border-b hover:bg-slate-50">
            <td class="px-3 py-2 font-mono text-xs">${p.hn}</td>
            <td class="px-3 py-2">${(p.prefix || '')} <strong>${p.first_name} ${p.last_name}</strong>${p.nickname ? ' ('+p.nickname+')' : ''}</td>
            <td class="px-3 py-2">${({male:'ชาย',female:'หญิง',other:'อื่น ๆ'})[p.gender] || '-'}</td>
            <td class="px-3 py-2">${p.phone || '-'}</td>
            <td class="px-3 py-2 text-xs text-slate-600">${fmtDate(p.last_visit_at)}</td>
            <td class="px-3 py-2">${(p.total_spent || 0).toLocaleString()}</td>
                <td class="px-3 py-2 text-right">
                <a class="text-cyan-700 hover:underline" href="/patients/${p.id}">เปิด OPD</a>
                <button class="text-cyan-700 hover:underline ml-2" data-edit="${p.id}">แก้ไข</button>
                <button class="text-red-600 hover:underline ml-2" data-del="${p.id}">ลบ</button>
            </td>
        </tr>
    `).join('');
    rows.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => openEdit(b.dataset.edit)));
    rows.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', () => removeOne(b.dataset.del)));
    renderPager();
}

function renderPager() {
    const el = document.getElementById('pagination');
    el.innerHTML = `<span class="text-slate-600">รวม ${state.total} ราย, หน้า ${state.page}/${state.lastPage}</span>`;
    if (state.page > 1) el.innerHTML += `<button class="ml-3 px-3 py-1 rounded border" id="prev">◀ ก่อนหน้า</button>`;
    if (state.page < state.lastPage) el.innerHTML += `<button class="ml-3 px-3 py-1 rounded border" id="next">ถัดไป ▶</button>`;
    document.getElementById('prev')?.addEventListener('click', () => { state.page--; load(); });
    document.getElementById('next')?.addEventListener('click', () => { state.page++; load(); });
}

function openCreate() {
    editingId = null;
    document.getElementById('modal-title').textContent = 'เพิ่มผู้ป่วย';
    document.getElementById('form').reset();
    document.getElementById('form-error').classList.add('hidden');
    document.getElementById('modal').classList.remove('hidden');
}

async function openEdit(uuid) {
    const r = await api.call('/patients/' + uuid);
    if (!r.ok) return alert('โหลดข้อมูลไม่สำเร็จ');
    const p = r.data.data;
    editingId = uuid;
    document.getElementById('modal-title').textContent = 'แก้ไขผู้ป่วย: ' + p.hn;
    ['prefix','first_name','last_name','nickname','gender','date_of_birth','phone','email','line_id','address','allergies','underlying_diseases']
        .forEach(k => { document.getElementById(k).value = p[k] || ''; });
    document.getElementById('form-error').classList.add('hidden');
    document.getElementById('modal').classList.remove('hidden');
}

async function removeOne(uuid) {
    if (!confirm('ลบผู้ป่วย?')) return;
    const r = await api.call('/patients/' + uuid, { method: 'DELETE' });
    if (r.ok) load(); else alert('ลบไม่สำเร็จ');
}

document.getElementById('btn-new').addEventListener('click', openCreate);
document.getElementById('modal-close').addEventListener('click', () => document.getElementById('modal').classList.add('hidden'));
document.getElementById('cancel').addEventListener('click', () => document.getElementById('modal').classList.add('hidden'));

document.getElementById('form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {};
    ['prefix','first_name','last_name','nickname','gender','date_of_birth','phone','email','line_id','address','allergies','underlying_diseases']
        .forEach(k => {
            const v = document.getElementById(k).value;
            if (v !== '') payload[k] = v;
        });
    const url = editingId ? '/patients/' + editingId : '/patients';
    const method = editingId ? 'PUT' : 'POST';
    const r = await api.call(url, { method, body: JSON.stringify(payload) });
    const errEl = document.getElementById('form-error');
    if (!r.ok) {
        let msg = (r.data && r.data.message) || 'บันทึกไม่สำเร็จ';
        if (r.data && r.data.errors) {
            msg += '\n' + Object.values(r.data.errors).flat().join('\n');
        }
        errEl.textContent = msg; errEl.classList.remove('hidden');
        return;
    }
    document.getElementById('modal').classList.add('hidden');
    load();
});

let searchTimer;
document.getElementById('search').addEventListener('input', (e) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { state.search = e.target.value.trim(); state.page = 1; load(); }, 300);
});

if (!api.token()) window.location.href = '/login'; else load();
</script>
@endsection
