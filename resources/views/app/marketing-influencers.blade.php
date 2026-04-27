@extends('layouts.app')
@section('title', 'Influencers')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">📢 Influencers</h1>
            <a href="/marketing/coupons" class="text-sm text-cyan-700 hover:underline">Coupons</a>
            <a href="/marketing/promotions" class="text-sm text-cyan-700 hover:underline">Promotions</a>
            <button id="btn-create" class="ml-auto bg-cyan-600 text-white px-3 py-1.5 rounded text-sm">+ Influencer</button>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6 grid md:grid-cols-2 gap-4">
        <section class="bg-white rounded-xl shadow p-4">
            <h3 class="font-semibold mb-2">Influencers</h3>
            <div id="influencers-list" class="space-y-2"></div>
        </section>
        <section class="bg-white rounded-xl shadow p-4">
            <h3 class="font-semibold mb-2">Campaigns + ROI</h3>
            <div id="campaigns-panel" class="text-sm text-slate-500">เลือก Influencer →</div>
        </section>
    </main>
</div>

<dialog id="dlg-inf" class="rounded-xl p-0 w-[420px] max-w-full">
    <form method="dialog" id="form-inf" class="p-4 space-y-2">
        <h3 class="font-bold">+ Influencer</h3>
        <input name="name" required placeholder="ชื่อ" class="w-full border rounded px-2 py-1.5">
        <select name="channel" class="w-full border rounded px-2 py-1.5">
            <option>instagram</option><option>facebook</option><option>tiktok</option><option>youtube</option><option>line</option><option>other</option>
        </select>
        <input name="handle" placeholder="@handle" class="w-full border rounded px-2 py-1.5">
        <input name="contact" placeholder="ติดต่อ (เบอร์/อีเมล)" class="w-full border rounded px-2 py-1.5">
        <input name="commission_rate" type="number" step="0.01" placeholder="% commission" class="w-full border rounded px-2 py-1.5">
        <div class="flex gap-2 pt-2"><button type="submit" class="bg-cyan-600 text-white px-3 py-1.5 rounded">บันทึก</button>
            <button type="button" onclick="document.getElementById('dlg-inf').close()" class="px-3 py-1.5">ยกเลิก</button></div>
    </form>
</dialog>

<dialog id="dlg-camp" class="rounded-xl p-0 w-[460px] max-w-full">
    <form method="dialog" id="form-camp" class="p-4 space-y-2">
        <h3 class="font-bold">+ Campaign <span id="for-influencer" class="text-slate-500 text-sm"></span></h3>
        <input name="name" required placeholder="ชื่อ campaign" class="w-full border rounded px-2 py-1.5">
        <input name="utm_source" required placeholder="utm_source" value="instagram" class="w-full border rounded px-2 py-1.5">
        <input name="utm_medium" placeholder="utm_medium" value="cpc" class="w-full border rounded px-2 py-1.5">
        <input name="utm_campaign" required placeholder="utm_campaign" class="w-full border rounded px-2 py-1.5">
        <input name="landing_url" type="url" placeholder="https://landing-url..." class="w-full border rounded px-2 py-1.5">
        <div class="grid grid-cols-2 gap-2">
            <input name="start_date" required type="date" class="border rounded px-2 py-1.5">
            <input name="end_date" required type="date" class="border rounded px-2 py-1.5">
        </div>
        <input name="total_budget" type="number" step="0.01" placeholder="Budget" class="w-full border rounded px-2 py-1.5">
        <select name="status" class="w-full border rounded px-2 py-1.5">
            <option>draft</option><option selected>active</option><option>paused</option><option>ended</option>
        </select>
        <div class="flex gap-2 pt-2"><button type="submit" class="bg-cyan-600 text-white px-3 py-1.5 rounded">บันทึก</button>
            <button type="button" onclick="document.getElementById('dlg-camp').close()" class="px-3 py-1.5">ยกเลิก</button></div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
let currentInfluencer = null;

async function loadInfluencers() {
    const r = await api.call('/marketing/influencers');
    if (!r.ok) return;
    const rows = r.data.data?.data || [];
    document.getElementById('influencers-list').innerHTML = rows.map(i => `
        <button class="w-full text-left p-3 rounded hover:bg-slate-100 border ${currentInfluencer?.id === i.id ? 'bg-cyan-50 border-cyan-500' : ''}"
                data-id="${i.id}" data-name="${i.name}">
            <div class="font-semibold">${i.name}</div>
            <div class="text-xs text-slate-500">${i.channel} • ${i.handle || '—'} • ${i.commission_rate}%</div>
        </button>`).join('') || '<div class="text-slate-400 text-sm">ไม่มีข้อมูล</div>';

    document.querySelectorAll('#influencers-list button').forEach(b => b.onclick = () => {
        currentInfluencer = { id: +b.dataset.id, name: b.dataset.name };
        loadCampaigns();
        loadInfluencers();
    });
}

async function loadCampaigns() {
    if (!currentInfluencer) return;
    const r = await api.call(`/marketing/influencers/${currentInfluencer.id}/campaigns`);
    if (!r.ok) return;
    const camps = r.data.data?.data || [];
    let html = `<div class="flex items-center justify-between mb-2">
        <h4 class="font-semibold">Campaigns of ${currentInfluencer.name}</h4>
        <button id="btn-camp" class="text-cyan-600 text-sm hover:underline">+ เพิ่ม</button></div>`;
    html += '<div class="space-y-2">' + camps.map(c => `
        <div class="border rounded p-2">
            <div class="flex justify-between items-center">
                <div><b>${c.name}</b> <span class="text-xs text-slate-400 ml-2">/${c.shortcode}</span></div>
                <span class="text-xs px-2 py-0.5 rounded bg-slate-100">${c.status}</span>
            </div>
            <div class="text-xs text-slate-500 mt-1">UTM: ${c.utm_source} / ${c.utm_campaign} • Budget: ${c.total_budget}</div>
            <div class="text-xs text-cyan-700 mt-1">URL: <a href="/r/${c.shortcode}" target="_blank">/r/${c.shortcode}</a></div>
            <button class="text-cyan-600 text-xs hover:underline mt-1" data-camp="${c.id}">📊 Report ROI</button>
            <div data-report="${c.id}" class="mt-2 hidden text-xs"></div>
        </div>`).join('') + '</div>';
    document.getElementById('campaigns-panel').innerHTML = html;

    document.getElementById('btn-camp').onclick = () => {
        document.getElementById('for-influencer').textContent = '— ' + currentInfluencer.name;
        document.getElementById('dlg-camp').showModal();
    };
    document.querySelectorAll('[data-camp]').forEach(b => b.onclick = async () => {
        const id = b.dataset.camp;
        const r = await api.call(`/marketing/campaigns/${id}/report`);
        if (!r.ok) return;
        const d = r.data.data;
        const target = document.querySelector(`[data-report="${id}"]`);
        target.classList.remove('hidden');
        target.innerHTML = `Clicks: <b>${d.clicks}</b> • Signups: <b>${d.signups}</b> •
            LTV: ฿${(+d.ltv_total).toLocaleString()} •
            Budget: ฿${(+d.budget).toLocaleString()} •
            ROI: <span class="${d.roi_pct > 0 ? 'text-emerald-700' : 'text-rose-700'}">${d.roi_pct === null ? '—' : d.roi_pct + '%'}</span>`;
    });
}

document.getElementById('btn-create').onclick = () => document.getElementById('dlg-inf').showModal();
document.getElementById('form-inf').onsubmit = async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const r = await api.call('/marketing/influencers', { method: 'POST', body: data });
    if (r.ok) { document.getElementById('dlg-inf').close(); e.target.reset(); loadInfluencers(); }
    else alert(JSON.stringify(r.data?.errors || r.data));
};

document.getElementById('form-camp').onsubmit = async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const r = await api.call(`/marketing/influencers/${currentInfluencer.id}/campaigns`, { method: 'POST', body: data });
    if (r.ok) { document.getElementById('dlg-camp').close(); e.target.reset(); loadCampaigns(); }
    else alert(JSON.stringify(r.data?.errors || r.data));
};

(async function () { if (!api.token()) return location.href = '/login'; await loadInfluencers(); })();
</script>
@endsection
