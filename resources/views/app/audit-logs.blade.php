@extends('layouts.app')
@section('title', 'Audit Logs')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">📜 Audit Logs</h1>
            <input id="search" placeholder="ค้นหา action/model..." class="border rounded px-2 py-1 text-sm">
            <select id="action" class="border rounded px-2 py-1 text-sm">
                <option value="">ทุก action</option><option>created</option><option>updated</option><option>deleted</option>
            </select>
            <input id="from" type="date" class="border rounded px-2 py-1 text-sm">
            <input id="to" type="date" class="border rounded px-2 py-1 text-sm">
            <button id="btn-load" class="bg-cyan-600 text-white px-3 py-1.5 rounded text-sm">โหลด</button>
            <button id="btn-export" class="ml-auto bg-emerald-600 text-white px-3 py-1.5 rounded text-sm">📥 Export CSV</button>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <div class="bg-white rounded-xl shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100"><tr>
                    <th class="px-3 py-2 text-left">เวลา</th>
                    <th class="text-left">ผู้ใช้</th>
                    <th>Action</th>
                    <th class="text-left">Model</th>
                    <th>Record</th>
                    <th>IP</th>
                    <th></th>
                </tr></thead>
                <tbody id="rows"></tbody>
            </table>
        </div>
    </main>
</div>

<dialog id="dlg-diff" class="rounded-xl p-0 w-[700px] max-w-full">
    <div class="p-4">
        <div class="flex items-center justify-between mb-2">
            <h3 class="font-bold">🔍 Audit Diff</h3>
            <button onclick="document.getElementById('dlg-diff').close()" class="text-slate-400">✕</button>
        </div>
        <div id="diff-meta" class="text-xs text-slate-500 mb-3"></div>
        <div id="diff-content" class="space-y-2 max-h-[60vh] overflow-y-auto"></div>
    </div>
</dialog>
@endsection

@section('scripts')
<script>
function buildQuery() {
    const params = new URLSearchParams();
    const search = document.getElementById('search').value;
    const action = document.getElementById('action').value;
    const from = document.getElementById('from').value;
    const to = document.getElementById('to').value;
    if (search) params.set('search', search);
    if (action) params.set('action', action);
    if (from) params.set('date_from', from);
    if (to) params.set('date_to', to);

    return params;
}

async function load() {
    const params = buildQuery();
    const r = await api.call('/audit-logs?'+params);
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('rows').innerHTML = rows.map(l => `
        <tr class="border-t hover:bg-slate-50">
            <td class="px-3 py-1.5 text-xs">${(l.created_at || '').replace('T', ' ').slice(0, 19)}</td>
            <td>${l.user?.name || '—'}</td>
            <td class="text-center"><span class="px-2 py-0.5 rounded text-xs ${
                l.action === 'created' ? 'bg-emerald-100 text-emerald-700' :
                l.action === 'updated' ? 'bg-cyan-100 text-cyan-700' :
                l.action === 'deleted' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100'
            }">${l.action}</span></td>
            <td class="text-xs font-mono">${(l.auditable_type || '').split('\\\\').pop()}</td>
            <td class="text-center">${l.auditable_id || '—'}</td>
            <td class="text-xs">${l.ip_address || '—'}</td>
            <td><button data-id="${l.id}" class="text-cyan-700 text-xs hover:underline">Diff →</button></td>
        </tr>`).join('') || `<tr><td colspan="7" class="text-center py-8 text-slate-400">ไม่มี audit log</td></tr>`;

    document.querySelectorAll('[data-id]').forEach(b => b.onclick = () => showDiff(b.dataset.id));
}

async function showDiff(id) {
    const r = await api.call('/audit-logs/'+id);
    if (!r.ok) return;
    const d = r.data.data;
    document.getElementById('diff-meta').textContent = `${d.log.action} • ${d.log.auditable_type} #${d.log.auditable_id} • ${d.log.created_at}`;
    document.getElementById('diff-content').innerHTML = d.changed_fields.length === 0 ? '<div class="text-slate-400">ไม่มีการเปลี่ยนแปลง</div>' :
        d.changed_fields.map(field => `
            <div class="border rounded p-2">
                <div class="font-mono text-xs font-semibold">${field}</div>
                <div class="grid grid-cols-2 gap-2 mt-1">
                    <div class="bg-rose-50 rounded p-2 text-xs">
                        <div class="text-rose-700 font-semibold">- Before</div>
                        <pre class="whitespace-pre-wrap break-all">${JSON.stringify(d.diff[field].before, null, 2)}</pre>
                    </div>
                    <div class="bg-emerald-50 rounded p-2 text-xs">
                        <div class="text-emerald-700 font-semibold">+ After</div>
                        <pre class="whitespace-pre-wrap break-all">${JSON.stringify(d.diff[field].after, null, 2)}</pre>
                    </div>
                </div>
            </div>`).join('');
    document.getElementById('dlg-diff').showModal();
}

document.getElementById('btn-load').onclick = load;
document.getElementById('btn-export').onclick = async () => {
    const params = buildQuery();
    const url = '/api/v1/audit-logs/export?'+params;
    const res = await fetch(url, { headers: { 'Authorization': 'Bearer ' + api.token(), 'X-Branch-Id': api.branchId() } });
    const blob = await res.blob();
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'audit-logs.csv'; a.click();
};

(async function () { if (!api.token()) return location.href = '/login'; await load(); })();
</script>
@endsection
