@extends('layouts.app')
@section('title', 'Chart of Accounts')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">📒 Chart of Accounts (ผังบัญชี)</h1>
            <a href="/accounting/ledger" class="ml-auto text-sm text-cyan-700 hover:underline">Ledger</a>
            <button id="btn-new" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">+ บัญชีใหม่</button>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-6">
        <section class="bg-white rounded-xl shadow">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="px-3 py-2 text-left">Code</th>
                        <th class="px-3 py-2 text-left">Name</th>
                        <th class="px-3 py-2 text-left">Type</th>
                        <th class="px-3 py-2 text-center">System</th>
                        <th class="px-3 py-2 text-center">Active</th>
                    </tr>
                </thead>
                <tbody id="rows"></tbody>
            </table>
        </section>
    </main>
</div>

<dialog id="form-dialog" class="rounded-xl p-0 w-[480px] max-w-full">
    <form id="form" class="p-4 space-y-3">
        <h3 class="font-semibold text-lg">บัญชีใหม่</h3>
        <div class="grid grid-cols-2 gap-2">
            <label class="text-sm">Code
                <input name="code" required maxlength="10" class="w-full border rounded px-2 py-1 mt-1 font-mono">
            </label>
            <label class="text-sm">Type
                <select name="type" required class="w-full border rounded px-2 py-1 mt-1">
                    <option value="asset">Asset</option>
                    <option value="liability">Liability</option>
                    <option value="equity">Equity</option>
                    <option value="revenue">Revenue</option>
                    <option value="expense">Expense</option>
                </select>
            </label>
            <label class="text-sm col-span-2">Name
                <input name="name" required maxlength="150" class="w-full border rounded px-2 py-1 mt-1">
            </label>
            <label class="text-sm flex items-center gap-2 col-span-2">
                <input type="checkbox" name="is_active" checked> Active
            </label>
        </div>
        <div class="flex justify-end gap-2">
            <button type="button" class="dlg-cancel px-3 py-1 rounded bg-slate-100">ยกเลิก</button>
            <button type="submit" class="px-3 py-1 rounded bg-cyan-600 text-white">บันทึก</button>
        </div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
const TYPE_COLOR = {
    asset: 'bg-cyan-100 text-cyan-800',
    liability: 'bg-amber-100 text-amber-800',
    equity: 'bg-purple-100 text-purple-800',
    revenue: 'bg-emerald-100 text-emerald-800',
    expense: 'bg-rose-100 text-rose-800',
};

document.addEventListener('click', e => {
    if (e.target.classList.contains('dlg-cancel')) e.target.closest('dialog').close();
});

async function loadList() {
    const r = await api.call('/accounting/coa');
    if (!r.ok) return;
    const rows = r.data.data || [];
    document.getElementById('rows').innerHTML = rows.map(a => `
      <tr class="border-t">
        <td class="px-3 py-2 font-mono">${a.code}</td>
        <td class="px-3 py-2">${a.name}</td>
        <td class="px-3 py-2"><span class="text-xs px-2 py-0.5 rounded ${TYPE_COLOR[a.type]||''}">${a.type}</span></td>
        <td class="px-3 py-2 text-center">${a.is_system ? '🔒' : ''}</td>
        <td class="px-3 py-2 text-center">${a.is_active ? '<span class="text-emerald-700">●</span>' : '<span class="text-slate-400">○</span>'}</td>
      </tr>`).join('');
}

document.getElementById('btn-new').addEventListener('click', () => {
    document.getElementById('form-dialog').showModal();
});

document.getElementById('form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const r = await api.call('/accounting/coa', {
        method: 'POST',
        body: JSON.stringify({
            code: f.code.value,
            name: f.name.value,
            type: f.type.value,
            is_active: f.is_active.checked,
        }),
    });
    if (!r.ok) return alert((r.data && r.data.message) || 'บันทึกไม่ได้');
    f.reset();
    document.getElementById('form-dialog').close();
    loadList();
});

(async function () {
    if (!api.token()) return window.location.href = '/login';
    await loadList();
})();
</script>
@endsection
