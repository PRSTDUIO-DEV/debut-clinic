@extends('layouts.app')
@section('title', 'QC Run')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/qc/runs" class="text-cyan-700 hover:underline">← Runs</a>
            <h1 class="font-bold" id="title">QC Run …</h1>
            <span id="status-badge" class="ml-2 text-xs"></span>
            <button id="btn-complete" class="ml-auto bg-emerald-600 text-white px-3 py-1.5 rounded text-sm hidden">✅ เสร็จสิ้น</button>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-6 space-y-3">
        <div id="meta" class="bg-white rounded-xl shadow p-4"></div>
        <div id="items" class="space-y-2"></div>
    </main>
</div>
@endsection

@section('scripts')
<script>
const RUN_ID = parseInt(window.location.pathname.split('/').pop(), 10);
let run = null;

async function load() {
    const r = await api.call('/qc/runs/'+RUN_ID);
    if (!r.ok) return location.href = '/qc/runs';
    run = r.data.data;
    render();
}

function render() {
    document.getElementById('title').textContent = `QC Run — ${run.checklist.name}`;

    const cls = { pending: 'bg-slate-100', in_progress: 'bg-amber-100 text-amber-700', completed: 'bg-emerald-100 text-emerald-700' }[run.status];
    document.getElementById('status-badge').innerHTML = `<span class="px-2 py-0.5 rounded ${cls}">${run.status}</span>`;

    document.getElementById('meta').innerHTML = `
        <div class="text-sm grid grid-cols-2 md:grid-cols-4 gap-2">
            <div><span class="text-slate-500">วันที่:</span> ${run.run_date}</div>
            <div><span class="text-slate-500">โดย:</span> ${run.performer?.name || '—'}</div>
            <div><span class="text-slate-500">ผ่าน:</span> ${run.passed_count}/${run.total_items}</div>
            <div><span class="text-slate-500">Failed:</span> <b class="${run.failed_count > 0 ? 'text-rose-600' : ''}">${run.failed_count}</b></div>
        </div>`;

    const editable = run.status !== 'completed';
    document.getElementById('btn-complete').classList.toggle('hidden', !editable);

    const itemsByChecklist = run.checklist?.items || [];
    const recordedById = {};
    (run.items || []).forEach(ri => recordedById[ri.item_id] = ri);

    document.getElementById('items').innerHTML = itemsByChecklist.map(item => {
        const r = recordedById[item.id];
        const status = r?.status;

        return `
        <div class="bg-white rounded-xl shadow p-4" data-item="${item.id}">
            <div class="flex items-start gap-3">
                <div class="flex-1">
                    <b>${item.title}</b>
                    ${item.description ? `<div class="text-xs text-slate-500 mt-1">${item.description}</div>` : ''}
                    ${r?.note ? `<div class="text-xs bg-amber-50 rounded p-1 mt-1">📝 ${r.note}</div>` : ''}
                </div>
                <div class="flex gap-1">
                    <button data-status="pass" class="px-3 py-1.5 rounded text-sm ${status === 'pass' ? 'bg-emerald-600 text-white' : 'bg-slate-100 hover:bg-emerald-100'}" ${editable ? '' : 'disabled'}>✓ Pass</button>
                    <button data-status="fail" class="px-3 py-1.5 rounded text-sm ${status === 'fail' ? 'bg-rose-600 text-white' : 'bg-slate-100 hover:bg-rose-100'}" ${editable ? '' : 'disabled'}>✗ Fail</button>
                    <button data-status="na" class="px-3 py-1.5 rounded text-sm ${status === 'na' ? 'bg-slate-500 text-white' : 'bg-slate-100 hover:bg-slate-200'}" ${editable ? '' : 'disabled'}>N/A</button>
                </div>
            </div>
        </div>`;
    }).join('');

    if (editable) {
        document.querySelectorAll('[data-item]').forEach(div => {
            const itemId = +div.dataset.item;
            div.querySelectorAll('[data-status]').forEach(btn => btn.onclick = () => recordItem(itemId, btn.dataset.status, div));
        });
    }
}

async function recordItem(itemId, status, divEl) {
    const item = run.checklist.items.find(i => i.id === itemId);
    let note = null;
    if (status === 'fail' || item?.requires_note) {
        note = prompt('หมายเหตุ' + (status === 'fail' ? ' (สำคัญสำหรับ Fail)' : '') + ':') || null;
    }
    const r = await api.call(`/qc/runs/${run.id}/items`, {
        method: 'POST',
        body: { item_id: itemId, status, note },
    });
    if (r.ok) await load();
}

document.getElementById('btn-complete').onclick = async () => {
    if (!confirm('เสร็จสิ้นการตรวจ? จะไม่สามารถแก้ไขได้อีก')) return;
    const r = await api.call(`/qc/runs/${run.id}/complete`, { method: 'POST' });
    if (r.ok) await load();
};

(async function () { if (!api.token()) return location.href = '/login'; await load(); })();
</script>
@endsection
