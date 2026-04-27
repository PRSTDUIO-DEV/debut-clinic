@extends('layouts.app')
@section('title', 'Reviews')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">⭐ Reviews</h1>
            <a href="/marketing/coupons" class="text-sm text-cyan-700 hover:underline">Coupons</a>
            <a href="/marketing/promotions" class="text-sm text-cyan-700 hover:underline">Promotions</a>
            <select id="status-filter" class="ml-auto border rounded px-2 py-1 text-sm">
                <option value="">ทั้งหมด</option><option>pending</option><option>published</option><option>hidden</option>
            </select>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6 space-y-4">
        <section id="aggregate" class="grid md:grid-cols-3 gap-3"></section>

        <section class="bg-white rounded-xl shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100"><tr>
                    <th class="px-3 py-2">★</th><th class="text-left">Title / Body</th><th>ลูกค้า</th><th>Source</th><th>Status</th><th>Action</th>
                </tr></thead>
                <tbody id="rows"></tbody>
            </table>
        </section>
    </main>
</div>
@endsection

@section('scripts')
<script>
async function loadAgg() {
    const r = await api.call('/reviews/aggregate');
    if (!r.ok) return;
    const d = r.data.data;
    document.getElementById('aggregate').innerHTML = `
        <div class="bg-white rounded-xl shadow p-4">
            <div class="text-xs text-slate-500">Avg Rating</div>
            <div class="text-3xl font-bold">${d.branch.avg} <span class="text-amber-500">★</span></div>
            <div class="text-xs text-slate-500">${d.branch.count} reviews</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 col-span-2">
            <div class="text-xs text-slate-500 mb-1">Distribution</div>
            ${[5,4,3,2,1].map(s => `
                <div class="flex items-center gap-2">
                    <span class="w-6 text-amber-500">${s}★</span>
                    <div class="flex-1 bg-slate-100 rounded h-3 overflow-hidden">
                        <div class="bg-amber-400 h-full" style="width: ${d.branch.count > 0 ? (d.branch.distribution[s]/d.branch.count)*100 : 0}%"></div>
                    </div>
                    <span class="w-12 text-right text-xs">${d.branch.distribution[s]}</span>
                </div>`).join('')}
        </div>`;
}

async function loadList() {
    const status = document.getElementById('status-filter').value;
    const r = await api.call('/reviews' + (status ? '?status='+status : ''));
    if (!r.ok) return;
    const rows = r.data.data?.data || [];
    document.getElementById('rows').innerHTML = rows.map(rv => `
        <tr class="border-t">
            <td class="px-3 py-2 text-amber-500 text-center">${'★'.repeat(rv.rating)}${'☆'.repeat(5 - rv.rating)}</td>
            <td><b>${rv.title || '—'}</b><div class="text-xs text-slate-500">${rv.body || ''}</div></td>
            <td class="text-xs">${rv.patient?.first_name || ''} ${rv.patient?.last_name || ''}</td>
            <td class="text-center text-xs">${rv.source}</td>
            <td class="text-center"><span class="px-2 py-0.5 rounded text-xs ${rv.status === 'published' ? 'bg-emerald-100 text-emerald-700' : (rv.status === 'hidden' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700')}">${rv.status}</span></td>
            <td class="text-center">
                ${rv.status !== 'published' ? `<button data-action="publish" data-id="${rv.id}" class="text-emerald-600 text-xs hover:underline">Publish</button>` : ''}
                ${rv.status !== 'hidden' ? `<button data-action="hide" data-id="${rv.id}" class="text-rose-600 text-xs hover:underline ml-2">Hide</button>` : ''}
            </td>
        </tr>`).join('');

    document.querySelectorAll('[data-action]').forEach(b => b.onclick = async () => {
        const status = b.dataset.action === 'publish' ? 'published' : 'hidden';
        const r = await api.call(`/reviews/${b.dataset.id}/moderate`, { method: 'PATCH', body: { status } });
        if (r.ok) { loadList(); loadAgg(); }
    });
}

document.getElementById('status-filter').onchange = loadList;
(async function () { if (!api.token()) return location.href = '/login'; await Promise.all([loadAgg(), loadList()]); })();
</script>
@endsection
