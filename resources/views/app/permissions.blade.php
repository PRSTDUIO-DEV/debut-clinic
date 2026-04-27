@extends('layouts.app')
@section('title', 'Permission Matrix')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">Permission Matrix</h1>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6">
        <div id="status" class="hidden mb-3 p-3 rounded-lg text-sm"></div>
        <div id="matrix-host" class="bg-white rounded-xl shadow overflow-x-auto p-4">
            <div id="loading" class="text-slate-500 text-sm">กำลังโหลด...</div>
        </div>
    </main>
</div>
@endsection

@section('scripts')
<script>
let state = { roles: [], perms: [], byModule: {}, dirty: new Set() };

function show(msg, type='info') {
    const el = document.getElementById('status');
    el.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-700', 'bg-red-50', 'text-red-700');
    el.classList.add(type === 'error' ? 'bg-red-50' : 'bg-emerald-50', type === 'error' ? 'text-red-700' : 'text-emerald-700');
    el.textContent = msg;
    setTimeout(() => el.classList.add('hidden'), 4000);
}

function render() {
    const host = document.getElementById('matrix-host');
    const roleIds = state.roles.map(r => r.id);
    const rolePermSet = Object.fromEntries(state.roles.map(r => [r.id, new Set(r.permissions.map(p => p.id))]));
    const modules = Object.entries(state.byModule);

    let html = '<table class="min-w-full text-sm"><thead class="bg-slate-50"><tr>';
    html += '<th class="px-3 py-2 text-left">Permission</th>';
    state.roles.forEach(r => {
        html += `<th class="px-2 py-2 text-center text-xs font-semibold">${r.name}</th>`;
    });
    html += '</tr></thead><tbody>';

    modules.forEach(([mod, perms]) => {
        html += `<tr class="bg-slate-100"><td colspan="${1 + state.roles.length}" class="px-3 py-1 font-bold text-xs uppercase text-slate-500">${mod}</td></tr>`;
        perms.forEach(p => {
            html += `<tr class="border-b border-slate-100"><td class="px-3 py-1 font-mono text-xs">${p.name}</td>`;
            state.roles.forEach(r => {
                const checked = rolePermSet[r.id].has(p.id) ? 'checked' : '';
                html += `<td class="px-2 py-1 text-center"><input type="checkbox" data-role="${r.id}" data-perm="${p.id}" ${checked} class="perm-cb"></td>`;
            });
            html += '</tr>';
        });
    });
    html += '</tbody></table>';
    html += '<div class="mt-4 flex justify-end"><button id="save-btn" class="bg-cyan-600 hover:bg-cyan-700 text-white px-4 py-2 rounded font-semibold">บันทึก</button></div>';
    host.innerHTML = html;

    host.querySelectorAll('.perm-cb').forEach(cb => {
        cb.addEventListener('change', () => state.dirty.add(parseInt(cb.dataset.role)));
    });
    document.getElementById('save-btn').addEventListener('click', save);
}

async function save() {
    const dirtyRoleIds = [...state.dirty];
    if (!dirtyRoleIds.length) { show('ไม่มีการเปลี่ยนแปลง'); return; }
    let ok = 0, fail = 0;
    for (const roleId of dirtyRoleIds) {
        const ids = [...document.querySelectorAll(`.perm-cb[data-role="${roleId}"]:checked`)].map(cb => parseInt(cb.dataset.perm));
        const r = await api.call(`/admin/roles/${roleId}/permissions`, { method: 'PUT', body: JSON.stringify({ permission_ids: ids }) });
        if (r.ok) ok++; else fail++;
    }
    state.dirty.clear();
    show(`บันทึก ${ok} role${fail ? `, ล้มเหลว ${fail}` : ''}`, fail ? 'error' : 'info');
}

(async function () {
    if (!api.token()) { window.location.href = '/login'; return; }
    const r = await api.call('/admin/roles');
    if (!r.ok) {
        document.getElementById('matrix-host').innerHTML =
            `<div class="text-red-700">โหลดไม่สำเร็จ (HTTP ${r.status}): ${(r.data && r.data.message) || ''}</div>`;
        return;
    }
    state.roles = r.data.data.roles;
    state.perms = r.data.data.permissions;
    state.byModule = state.perms.reduce((acc, p) => {
        (acc[p.module] = acc[p.module] || []).push(p);
        return acc;
    }, {});
    render();
})();
</script>
@endsection
