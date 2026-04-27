@extends('layouts.app')
@section('title', 'คอร์สรักษา')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">🎫 คอร์สรักษา (Course Tracking)</h1>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6 grid md:grid-cols-3 gap-4">
        <section class="md:col-span-1 bg-white rounded-xl shadow p-4">
            <input id="search" placeholder="ค้นหา คอร์ส/HN/ชื่อ..." class="w-full border rounded px-2 py-1 text-sm mb-3">
            <div id="status-filter" class="flex gap-1 flex-wrap mb-3">
                <button data-st="" class="text-xs px-2 py-1 rounded bg-cyan-600 text-white">ทั้งหมด</button>
                <button data-st="active" class="text-xs px-2 py-1 rounded bg-white border">ใช้งาน</button>
                <button data-st="completed" class="text-xs px-2 py-1 rounded bg-white border">เสร็จสิ้น</button>
                <button data-st="expired" class="text-xs px-2 py-1 rounded bg-white border">หมดอายุ</button>
                <button data-st="cancelled" class="text-xs px-2 py-1 rounded bg-white border">ยกเลิก</button>
            </div>
            <ul id="list" class="space-y-2 max-h-[600px] overflow-y-auto"></ul>
        </section>

        <section class="md:col-span-2 bg-white rounded-xl shadow p-4">
            <div id="detail" class="text-slate-500">เลือกคอร์สจากรายการด้านซ้าย</div>
        </section>
    </main>
</div>

<dialog id="use-dialog" class="rounded-xl p-0 w-96 max-w-full">
    <form id="use-form" class="p-4 space-y-3">
        <h3 class="font-semibold text-lg">ใช้ session</h3>
        <label class="block text-sm">Visit (ผู้ป่วยที่กำลังเข้ารับบริการวันนี้)
            <select name="visit_uuid" required class="w-full border rounded px-2 py-1 mt-1"></select>
        </label>
        <label class="block text-sm">Doctor (optional)
            <select name="doctor_id" class="w-full border rounded px-2 py-1 mt-1"><option value="">-</option></select>
        </label>
        <label class="block text-sm">หมายเหตุ
            <input name="notes" class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <div class="flex justify-end gap-2">
            <button type="button" class="dlg-cancel px-3 py-1 rounded bg-slate-100">ยกเลิก</button>
            <button type="submit" class="px-3 py-1 rounded bg-emerald-600 text-white">บันทึกใช้</button>
        </div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
const STATUS_LABEL = { active: 'ใช้งาน', completed: 'เสร็จสิ้น', expired: 'หมดอายุ', cancelled: 'ยกเลิก' };
const STATUS_COLOR = {
    active: 'bg-emerald-100 text-emerald-800',
    completed: 'bg-cyan-100 text-cyan-800',
    expired: 'bg-slate-200 text-slate-700',
    cancelled: 'bg-rose-100 text-rose-800',
};

let currentCourseId = null;
let currentStatus = '';
let visits = [], doctors = [];

async function loadList() {
    const params = new URLSearchParams();
    if (document.getElementById('search').value) params.set('q', document.getElementById('search').value);
    if (currentStatus) params.set('status', currentStatus);
    const r = await api.call('/courses?'+params.toString());
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('list').innerHTML = rows.map(c => {
        const pct = c.total_sessions > 0 ? Math.round(c.used_sessions / c.total_sessions * 100) : 0;
        return `
          <li class="border rounded p-2 cursor-pointer hover:bg-cyan-50" data-id="${c.id}">
            <div class="flex justify-between items-start">
              <div>
                <div class="font-medium">${c.name}</div>
                <div class="text-xs text-slate-500">${c.patient?.name||'-'} (${c.patient?.hn||'-'})</div>
              </div>
              <span class="text-xs px-1.5 py-0.5 rounded ${STATUS_COLOR[c.status]||''}">${STATUS_LABEL[c.status]||c.status}</span>
            </div>
            <div class="mt-1 text-sm">${c.used_sessions}/${c.total_sessions} session</div>
            <div class="w-full bg-slate-200 h-1.5 rounded mt-1">
              <div class="bg-cyan-500 h-1.5 rounded" style="width: ${pct}%"></div>
            </div>
            ${c.expires_at ? `<div class="text-xs text-slate-500 mt-1">หมดอายุ: ${c.expires_at}</div>` : ''}
          </li>`;
    }).join('') || '<em class="text-slate-400 text-sm">ไม่พบคอร์ส</em>';
    document.querySelectorAll('#list li').forEach(li => {
        li.addEventListener('click', () => loadDetail(+li.dataset.id));
    });
}

async function loadDetail(id) {
    currentCourseId = id;
    const r = await api.call(`/courses/${id}`);
    if (!r.ok) return;
    const c = r.data.data;
    const pct = c.total_sessions > 0 ? Math.round(c.used_sessions / c.total_sessions * 100) : 0;
    document.getElementById('detail').innerHTML = `
      <div class="flex items-start justify-between">
        <div>
          <h2 class="text-xl font-bold">${c.name}</h2>
          <div class="text-sm text-slate-500">${c.patient?.name||''} • ${c.patient?.hn||''}</div>
          ${c.expires_at ? `<div class="text-sm text-slate-500">หมดอายุ: ${c.expires_at}</div>` : ''}
        </div>
        <span class="text-xs px-2 py-1 rounded ${STATUS_COLOR[c.status]||''}">${STATUS_LABEL[c.status]||c.status}</span>
      </div>
      <div class="mt-4 grid grid-cols-3 gap-2 text-center text-sm">
        <div class="p-2 bg-slate-50 rounded">รวม<br><span class="text-xl font-bold">${c.total_sessions}</span></div>
        <div class="p-2 bg-amber-50 rounded">ใช้แล้ว<br><span class="text-xl font-bold">${c.used_sessions}</span></div>
        <div class="p-2 bg-emerald-50 rounded">เหลือ<br><span class="text-xl font-bold">${c.remaining_sessions}</span></div>
      </div>
      <div class="w-full bg-slate-200 h-2 rounded mt-3">
        <div class="bg-cyan-500 h-2 rounded" style="width: ${pct}%"></div>
      </div>

      <div class="mt-4 flex gap-2 flex-wrap">
        ${c.status === 'active' && c.remaining_sessions > 0 ? `<button id="btn-use" class="bg-emerald-600 text-white px-3 py-1 rounded text-sm">+ บันทึกใช้ session</button>` : ''}
        ${c.status === 'active' ? `<button id="btn-cancel" class="bg-rose-600 text-white px-3 py-1 rounded text-sm">ยกเลิกคอร์ส</button>` : ''}
        <a href="/patients/${c.patient?.uuid}" class="bg-slate-100 px-3 py-1 rounded text-sm">📋 OPD Card</a>
      </div>

      <h3 class="font-semibold mt-6 mb-2">ประวัติการใช้</h3>
      <table class="min-w-full text-sm">
        <thead class="bg-slate-100"><tr>
          <th class="px-2 py-1 text-left">#</th>
          <th class="px-2 py-1 text-left">เวลา</th>
          <th class="px-2 py-1 text-left">Visit</th>
          <th class="px-2 py-1 text-left">หมายเหตุ</th>
        </tr></thead>
        <tbody>${(c.usages||[]).map(u => `
          <tr class="border-t">
            <td class="px-2 py-1 font-mono">${u.session_number}</td>
            <td class="px-2 py-1 text-xs text-slate-500">${(u.used_at||'').replace('T',' ').slice(0,16)}</td>
            <td class="px-2 py-1 font-mono text-xs">${u.visit?.visit_number||'-'}</td>
            <td class="px-2 py-1 text-xs">${u.notes||'-'}</td>
          </tr>`).join('') || '<tr><td colspan="4" class="text-center py-4 text-slate-500">ยังไม่ได้ใช้ session ใดๆ</td></tr>'}
        </tbody>
      </table>`;

    document.getElementById('btn-use')?.addEventListener('click', openUseDialog);
    document.getElementById('btn-cancel')?.addEventListener('click', async () => {
        const reason = prompt('เหตุผลที่ยกเลิก:');
        if (!reason) return;
        const r = await api.call(`/courses/${id}/cancel`, { method: 'POST', body: JSON.stringify({ reason }) });
        if (!r.ok) return alert((r.data && r.data.message) || 'ยกเลิกไม่ได้');
        await loadList();
        await loadDetail(id);
    });
}

async function openUseDialog() {
    const visitsRes = await api.call('/visits/today');
    visits = (visitsRes.ok && visitsRes.data.data) || [];
    if (visits.length === 0) {
        return alert('ไม่มี visit เปิดอยู่วันนี้ — เปิด visit ที่ /pos ก่อน');
    }
    const sel = document.querySelector('[name=visit_uuid]');
    sel.innerHTML = visits.map(v => `<option value="${v.uuid}">${v.visit_number} - ${v.patient?.name || v.patient_uuid}</option>`).join('');

    if (doctors.length === 0) {
        const docRes = await api.call('/lookups/doctors');
        doctors = (docRes.ok && docRes.data.data) || [];
        const dsel = document.querySelector('[name=doctor_id]');
        dsel.innerHTML = '<option value="">-</option>' + doctors.map(d => `<option value="${d.id}">${d.name}</option>`).join('');
    }

    document.getElementById('use-dialog').showModal();
}

document.getElementById('use-form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const r = await api.call(`/courses/${currentCourseId}/use-session`, {
        method: 'POST',
        body: JSON.stringify({
            visit_uuid: f.visit_uuid.value,
            doctor_id: f.doctor_id.value ? +f.doctor_id.value : null,
            notes: f.notes.value || null,
        }),
    });
    if (!r.ok) return alert((r.data && r.data.message) || 'บันทึกไม่ได้');
    f.reset();
    document.getElementById('use-dialog').close();
    await loadList();
    await loadDetail(currentCourseId);
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
