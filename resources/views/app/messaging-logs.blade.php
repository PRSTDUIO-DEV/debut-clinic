@extends('layouts.app')
@section('title', 'Messaging Logs')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">📋 Messaging Logs</h1>
            <a href="/admin/messaging-providers" class="ml-auto text-sm text-cyan-700 hover:underline">📡 Providers</a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <section class="bg-white rounded-xl shadow p-4">
            <div class="flex flex-wrap gap-2 items-end mb-3">
                <label class="text-sm">Channel
                    <select id="channel" class="border rounded px-2 py-1 mt-1">
                        <option value="">ทั้งหมด</option>
                        <option value="line">LINE</option>
                        <option value="sms">SMS</option>
                        <option value="email">Email</option>
                    </select>
                </label>
                <label class="text-sm">Status
                    <select id="status" class="border rounded px-2 py-1 mt-1">
                        <option value="">ทั้งหมด</option>
                        <option value="sent">sent</option>
                        <option value="failed">failed</option>
                        <option value="pending">pending</option>
                        <option value="bounced">bounced</option>
                    </select>
                </label>
                <button id="btn-load" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">Filter</button>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="px-3 py-2 text-left">เวลา</th>
                        <th class="px-3 py-2 text-left">Channel</th>
                        <th class="px-3 py-2 text-left">Recipient</th>
                        <th class="px-3 py-2 text-left">Status</th>
                        <th class="px-3 py-2 text-left">Payload</th>
                        <th class="px-3 py-2 text-left">External ID</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="rows"></tbody>
            </table>
        </section>
    </main>
</div>
@endsection

@section('scripts')
<script>
const SC = {
    sent: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-rose-100 text-rose-800',
    pending: 'bg-slate-100 text-slate-700',
    bounced: 'bg-amber-100 text-amber-800',
};

async function load() {
    const params = new URLSearchParams();
    const ch = document.getElementById('channel').value;
    const s = document.getElementById('status').value;
    if (ch) params.set('channel', ch);
    if (s) params.set('status', s);
    const r = await api.call('/messaging-logs?'+params.toString());
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('rows').innerHTML = rows.map(l => `
      <tr class="border-t">
        <td class="px-3 py-1.5 text-xs">${(l.created_at||'').replace('T',' ').slice(0,16)}</td>
        <td class="px-3 py-1.5 text-xs uppercase">${l.channel}</td>
        <td class="px-3 py-1.5 font-mono text-xs">${l.recipient_address}</td>
        <td class="px-3 py-1.5"><span class="px-2 py-0.5 rounded text-xs ${SC[l.status]||''}">${l.status}</span>${l.error?`<div class="text-xs text-rose-600 mt-1">${l.error}</div>`:''}</td>
        <td class="px-3 py-1.5 text-xs text-slate-600">${(l.payload_preview||'').replace(/</g,'&lt;')}</td>
        <td class="px-3 py-1.5 font-mono text-xs">${l.external_id||'-'}</td>
        <td class="px-3 py-1.5 text-right whitespace-nowrap">
          ${l.status === 'failed' ? `<button class="retry text-cyan-700 text-sm hover:underline" data-id="${l.id}">🔁 Retry</button>` : ''}
        </td>
      </tr>`).join('') || '<tr><td colspan="7" class="text-center py-6 text-slate-500">ไม่มี log</td></tr>';

    document.querySelectorAll('.retry').forEach(b => b.addEventListener('click', async () => {
        const r = await api.call(`/messaging-logs/${b.dataset.id}/retry`, { method: 'POST', body: JSON.stringify({}) });
        if (!r.ok) return alert((r.data && r.data.message) || 'retry ไม่ได้');
        alert(r.data.data.ok ? 'Retry สำเร็จ' : 'Retry ล้มเหลว — ดูใน log');
        load();
    }));
}

document.getElementById('btn-load').addEventListener('click', load);

(async function () {
    if (!api.token()) return window.location.href = '/login';
    await load();
})();
</script>
@endsection
