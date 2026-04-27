@extends('layouts.app')
@section('title', 'System Health')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">🩺 System Health</h1>
            <button id="btn-refresh" class="ml-auto bg-cyan-600 text-white px-3 py-1.5 rounded text-sm">🔄 รีเฟรช</button>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6 space-y-4">
        <section id="app" class="grid grid-cols-2 md:grid-cols-5 gap-3"></section>

        <div class="grid md:grid-cols-2 gap-4">
            <section class="bg-white rounded-xl shadow p-4">
                <h3 class="font-semibold mb-2">🗄 Database</h3>
                <div id="db"></div>
            </section>
            <section class="bg-white rounded-xl shadow p-4">
                <h3 class="font-semibold mb-2">⚡ Cache + 📨 Queue</h3>
                <div id="cache-queue"></div>
            </section>
        </div>

        <section class="bg-white rounded-xl shadow p-4">
            <h3 class="font-semibold mb-2">⏰ Cron Schedule</h3>
            <div id="cron"></div>
        </section>

        <section class="bg-white rounded-xl shadow p-4">
            <h3 class="font-semibold mb-2">💾 Storage</h3>
            <div id="storage"></div>
        </section>

        <section class="bg-white rounded-xl shadow p-4">
            <h3 class="font-semibold mb-2">🚨 Recent Errors (50 ล่าสุด)</h3>
            <div id="errors" class="space-y-1 max-h-[400px] overflow-y-auto text-xs font-mono"></div>
        </section>
    </main>
</div>
@endsection

@section('scripts')
<script>
function fmtBytes(b) {
    if (!b) return '0 B';
    const u = ['B', 'KB', 'MB', 'GB']; let i = 0;
    while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }

    return b.toFixed(2) + ' ' + u[i];
}
function badge(status, label) {
    const cls = status === 'ok' ? 'bg-emerald-100 text-emerald-700' :
        status === 'error' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700';

    return `<span class="px-2 py-0.5 rounded text-xs ${cls}">${label || status}</span>`;
}

async function load() {
    const r = await api.call('/admin/system-health');
    if (!r.ok) return alert('ไม่มีสิทธิ์ดู system health');
    const d = r.data.data;

    document.getElementById('app').innerHTML = `
        <div class="bg-white rounded-xl shadow p-3"><div class="text-xs text-slate-500">Env</div><div class="font-bold uppercase">${d.app.env}</div></div>
        <div class="bg-white rounded-xl shadow p-3"><div class="text-xs text-slate-500">Debug</div><div class="font-bold">${d.app.debug ? 'ON' : 'OFF'}</div></div>
        <div class="bg-white rounded-xl shadow p-3"><div class="text-xs text-slate-500">Laravel</div><div class="font-bold">${d.app.laravel}</div></div>
        <div class="bg-white rounded-xl shadow p-3"><div class="text-xs text-slate-500">PHP</div><div class="font-bold">${d.app.php_version}</div></div>
        <div class="bg-white rounded-xl shadow p-3"><div class="text-xs text-slate-500">Time</div><div class="text-xs font-mono">${d.app.time}</div></div>`;

    const dbHtml = `
        <div class="text-sm space-y-1">
            <div>${badge(d.database.status)} ${d.database.driver} ${d.database.version || ''} • ${d.database.latency_ms || '?'}ms</div>
            <div class="text-xs text-slate-500">${d.database.total_tables || 0} tables</div>
        </div>
        ${d.database.top_tables && d.database.top_tables.length ? `
        <table class="min-w-full text-xs mt-2">
            <thead class="bg-slate-100"><tr><th class="text-left p-1">ตาราง</th><th class="text-right p-1">Rows</th><th class="text-right p-1">Size</th></tr></thead>
            <tbody>${d.database.top_tables.slice(0, 15).map(t => `
                <tr class="border-t"><td class="p-1">${t.name}</td><td class="text-right">${t.rows.toLocaleString()}</td><td class="text-right">${fmtBytes(t.size_bytes)}</td></tr>
            `).join('')}</tbody></table>` : ''}`;
    document.getElementById('db').innerHTML = dbHtml;

    document.getElementById('cache-queue').innerHTML = `
        <div class="space-y-2 text-sm">
            <div><b>Cache:</b> ${badge(d.cache.status)} ${d.cache.driver}</div>
            <div><b>Queue:</b> ${badge(d.queue.status || '—')} ${d.queue.driver}</div>
            <div class="text-xs">Pending: <b>${d.queue.pending ?? '—'}</b> • Failed: <b class="${d.queue.failed > 0 ? 'text-rose-600' : ''}">${d.queue.failed ?? 0}</b></div>
            ${d.queue.latest_failure ? `
                <div class="bg-rose-50 rounded p-2 text-xs mt-2">
                    <div class="font-semibold text-rose-700">Latest failure:</div>
                    <div class="text-slate-600">${d.queue.latest_failure.failed_at}</div>
                    <div class="font-mono text-xs mt-1">${d.queue.latest_failure.exception_first_line}</div>
                </div>` : ''}
        </div>`;

    document.getElementById('cron').innerHTML = `
        <table class="min-w-full text-sm">
            <thead class="bg-slate-100"><tr>
                <th class="text-left p-2">Command</th><th>Last Run</th><th>Duration</th><th>Status</th>
            </tr></thead>
            <tbody>${d.cron.map(c => `
                <tr class="border-t">
                    <td class="p-2 font-mono text-xs">${c.command}</td>
                    <td class="text-center text-xs">${c.last_run_at || '—'}</td>
                    <td class="text-right text-xs">${c.last_duration_ms !== null ? c.last_duration_ms + ' ms' : '—'}</td>
                    <td class="text-center">${
                        c.last_status === 'success' ? '<span class="text-emerald-700">✅</span>' :
                        c.last_status === 'failed' ? '<span class="text-rose-700">❌</span>' :
                        '<span class="text-slate-400">—</span>'
                    }</td>
                </tr>`).join('')}</tbody></table>`;

    document.getElementById('storage').innerHTML = `
        <div class="grid md:grid-cols-2 gap-2 text-sm">
            ${Object.entries(d.storage).map(([k, v]) => `
                <div class="border rounded p-3">
                    <div class="font-semibold">${k} ${badge(v.status)}</div>
                    ${v.size_bytes !== undefined ? `<div class="text-xs text-slate-500 mt-1">Used: <b>${fmtBytes(v.size_bytes)}</b> • Free: <b>${fmtBytes(v.free_bytes)}</b></div>` : ''}
                    ${v.error ? `<div class="text-rose-600 text-xs">${v.error}</div>` : ''}
                </div>`).join('')}
        </div>`;

    document.getElementById('errors').innerHTML = (d.recent_errors || []).map(e => `
        <div class="border-l-4 border-rose-500 bg-rose-50 px-2 py-1">
            <span class="text-rose-700 font-semibold">[${e.level}]</span>
            <span class="text-slate-500">${e.time}</span>
            <div class="text-slate-700">${e.message}</div>
        </div>`).join('') || '<div class="text-emerald-700">✅ ไม่มี error ล่าสุด</div>';
}

document.getElementById('btn-refresh').onclick = load;
(async function () { if (!api.token()) return location.href = '/login'; await load(); })();
</script>
@endsection
