@extends('layouts.app')
@section('title', 'การแจ้งเตือน')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">🔔 การแจ้งเตือน</h1>
            <button id="btn-mark-all" class="ml-auto bg-cyan-600 text-white px-3 py-1 rounded text-sm">อ่านทั้งหมด</button>
            <a href="#prefs" id="btn-prefs" class="bg-slate-100 px-3 py-1 rounded text-sm">⚙️ Preferences</a>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-6 space-y-4">
        <div id="status-filter" class="flex gap-1 flex-wrap">
            <button data-st="" class="text-xs px-2 py-1 rounded bg-cyan-600 text-white">ทั้งหมด</button>
            <button data-st="unread" class="text-xs px-2 py-1 rounded bg-white border">ยังไม่อ่าน</button>
            <button data-st="read" class="text-xs px-2 py-1 rounded bg-white border">อ่านแล้ว</button>
            <button data-st="failed" class="text-xs px-2 py-1 rounded bg-white border">ส่งไม่สำเร็จ</button>
        </div>

        <section class="bg-white rounded-xl shadow p-3">
            <ul id="list" class="divide-y"></ul>
        </section>

        <section id="prefs-section" class="bg-white rounded-xl shadow p-4 hidden">
            <h3 class="font-semibold mb-3">⚙️ ช่องทางการรับแจ้งเตือน</h3>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left">ช่องทาง</th>
                        <th class="px-3 py-2 text-center">เปิดรับ</th>
                        <th class="px-3 py-2 text-center">ห้ามส่งช่วง (เริ่ม - สิ้นสุด)</th>
                    </tr>
                </thead>
                <tbody id="prefs-rows"></tbody>
            </table>
            <div class="mt-3 flex justify-end">
                <button id="btn-save-prefs" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">บันทึก</button>
            </div>
        </section>
    </main>
</div>
@endsection

@section('scripts')
<script>
const SEV_COLOR = {
    info: 'bg-cyan-100 text-cyan-800',
    success: 'bg-emerald-100 text-emerald-800',
    warning: 'bg-amber-100 text-amber-800',
    critical: 'bg-rose-100 text-rose-800',
};
const SEV_ICON = { info: 'ℹ️', success: '✅', warning: '⚠️', critical: '🚨' };
const TYPE_LABEL = {
    birthday: 'วันเกิด',
    birthday_followup: 'ติดตามวันเกิด',
    urgent_followup: 'ติดตามด่วน',
    expiry_alert: 'ยา/สินค้าใกล้หมดอายุ',
    low_stock: 'สต็อกต่ำ',
    appointment_reminder: 'เตือนนัด',
};
const CH_LABEL = { in_app: 'ในแอป', line: 'LINE', sms: 'SMS', email: 'Email' };
let currentStatus = '';

function fmt(d) { return d ? d.replace('T',' ').slice(0,16) : '-'; }

async function load() {
    const params = new URLSearchParams();
    if (currentStatus) params.set('status', currentStatus);
    const r = await api.call('/notifications?'+params.toString());
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('list').innerHTML = rows.map(n => `
      <li class="py-3 flex items-start gap-3 ${!n.read_at ? 'bg-cyan-50/30' : ''}" data-id="${n.id}">
        <div class="text-2xl">${SEV_ICON[n.severity]||'•'}</div>
        <div class="flex-1">
          <div class="flex items-center gap-2">
            <span class="font-semibold">${n.title}</span>
            <span class="text-xs px-1.5 py-0.5 rounded ${SEV_COLOR[n.severity]||''}">${n.severity}</span>
            <span class="text-xs px-1.5 py-0.5 rounded bg-slate-100">${TYPE_LABEL[n.type]||n.type}</span>
            <span class="text-xs text-slate-400">${CH_LABEL[n.channel]||n.channel}</span>
          </div>
          ${n.body ? `<div class="text-sm text-slate-600 mt-1">${n.body}</div>` : ''}
          <div class="text-xs text-slate-400 mt-1">${fmt(n.created_at)}${n.related_type ? ` • อ้างอิง: ${n.related_type}#${n.related_id||'-'}` : ''}</div>
        </div>
        ${!n.read_at ? `<button class="mark-read text-cyan-700 text-xs hover:underline" data-id="${n.id}">อ่านแล้ว</button>` : '<span class="text-xs text-slate-400">อ่านแล้ว</span>'}
      </li>`).join('') || '<li class="py-6 text-center text-slate-500">ยังไม่มีการแจ้งเตือน</li>';

    document.querySelectorAll('.mark-read').forEach(b => b.addEventListener('click', async () => {
        const r = await api.call(`/notifications/${b.dataset.id}/mark-read`, { method: 'PATCH' });
        if (r.ok) load();
    }));
}

document.getElementById('btn-mark-all').addEventListener('click', async () => {
    if (!confirm('Mark all as read?')) return;
    const r = await api.call('/notifications/mark-all-read', { method: 'POST', body: JSON.stringify({}) });
    if (!r.ok) return alert((r.data && r.data.message) || 'ไม่ได้');
    load();
});

document.querySelectorAll('#status-filter button').forEach(b => {
    b.addEventListener('click', () => {
        currentStatus = b.dataset.st;
        document.querySelectorAll('#status-filter button').forEach(x => {
            x.className = `text-xs px-2 py-1 rounded ${x.dataset.st === currentStatus ? 'bg-cyan-600 text-white' : 'bg-white border'}`;
        });
        load();
    });
});

async function loadPrefs() {
    document.getElementById('prefs-section').classList.remove('hidden');
    const r = await api.call('/notification-preferences');
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('prefs-rows').innerHTML = rows.map(p => `
      <tr class="border-t" data-channel="${p.channel}">
        <td class="px-3 py-2">${CH_LABEL[p.channel]||p.channel}</td>
        <td class="px-3 py-2 text-center"><input type="checkbox" class="enabled" ${p.enabled ? 'checked' : ''}></td>
        <td class="px-3 py-2 text-center">
          <input type="time" class="qstart border rounded px-1 py-0.5" value="${p.quiet_hours_start || ''}">
          <span class="mx-1">–</span>
          <input type="time" class="qend border rounded px-1 py-0.5" value="${p.quiet_hours_end || ''}">
        </td>
      </tr>`).join('');
}

document.getElementById('btn-prefs').addEventListener('click', e => {
    e.preventDefault();
    loadPrefs();
});

document.getElementById('btn-save-prefs').addEventListener('click', async () => {
    const prefs = Array.from(document.querySelectorAll('#prefs-rows tr')).map(tr => ({
        channel: tr.dataset.channel,
        enabled: tr.querySelector('.enabled').checked,
        quiet_hours_start: tr.querySelector('.qstart').value || null,
        quiet_hours_end: tr.querySelector('.qend').value || null,
    }));
    const r = await api.call('/notification-preferences', { method: 'PUT', body: JSON.stringify({ preferences: prefs }) });
    if (!r.ok) return alert('บันทึกไม่ได้');
    alert('บันทึกแล้ว');
});

(async function () {
    if (!api.token()) return window.location.href = '/login';
    await load();
})();
</script>
@endsection
