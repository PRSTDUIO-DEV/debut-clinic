@extends('layouts.app')
@section('title', 'OPD Card')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/patients" class="text-cyan-700 hover:underline">← Patients</a>
            <h1 class="font-bold">OPD Card</h1>
            <span id="hn" class="ml-auto text-sm font-mono text-slate-500"></span>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6 space-y-4">
        <!-- Patient header -->
        <div class="bg-white rounded-xl shadow p-6">
            <div id="patient-header" class="grid grid-cols-1 md:grid-cols-3 gap-4">กำลังโหลด...</div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-xl shadow">
            <div id="tabs" class="flex border-b overflow-x-auto"></div>
            <div id="tab-body" class="p-5 min-h-[200px]"></div>
        </div>
    </main>
</div>

<dialog id="sign-dialog" class="rounded-xl p-0 w-[480px] max-w-full">
    <form id="sign-form" class="p-4 space-y-3">
        <h3 class="font-semibold text-lg">เซ็นเอกสาร</h3>
        <label class="block text-sm">ผู้เซ็น (ชื่อ)
            <input name="signed_by_name" required class="w-full border rounded px-2 py-1 mt-1">
        </label>
        <div>
            <div class="text-xs text-slate-500 mb-1">วาดลายเซ็น</div>
            <canvas id="sig-canvas" width="440" height="160" class="border rounded bg-white touch-none cursor-crosshair w-full"></canvas>
            <div class="mt-1 flex justify-end">
                <button type="button" id="sig-clear" class="text-xs text-slate-500 hover:underline">ล้างลายเซ็น</button>
            </div>
        </div>
        <div class="flex justify-end gap-2">
            <button type="button" class="dlg-cancel px-3 py-1 rounded bg-slate-100">ยกเลิก</button>
            <button type="submit" class="px-3 py-1 rounded bg-emerald-600 text-white">บันทึกการเซ็น</button>
        </div>
    </form>
</dialog>
@endsection

@section('scripts')
<script>
const TABS = [
    { id: 'profile',   label: '1. โปรไฟล์' },
    { id: 'visits',    label: '2. ประวัติการรักษา' },
    { id: 'photos',    label: '3. ภาพ Before/After' },
    { id: 'consents',  label: '4. เอกสารยินยอม' },
    { id: 'lab',       label: '5. ผลแล็บ' },
    { id: 'courses',   label: '6. คอร์ส/แพ็กเกจ' },
    { id: 'financial', label: '7. การเงิน' },
];

let patient = null;
let activeTab = 'profile';
const cache = {};

function getUuidFromPath() {
    const m = window.location.pathname.match(/^\/patients\/([^/]+)/);
    return m ? m[1] : null;
}

function fmtDate(s) { return s ? new Date(s).toLocaleString('th-TH', { dateStyle: 'medium', timeStyle: 'short' }) : '—'; }

function renderTabs() {
    const el = document.getElementById('tabs');
    el.innerHTML = TABS.map(t => `<button data-tab="${t.id}" class="px-4 py-3 text-sm font-medium whitespace-nowrap ${activeTab === t.id ? 'border-b-2 border-cyan-600 text-cyan-700' : 'text-slate-600 hover:text-slate-900'}">${t.label}</button>`).join('');
    el.querySelectorAll('[data-tab]').forEach(b => b.addEventListener('click', () => switchTab(b.dataset.tab)));
}

async function switchTab(id) {
    activeTab = id;
    renderTabs();
    const body = document.getElementById('tab-body');
    body.innerHTML = '<div class="text-slate-500">กำลังโหลด...</div>';
    if (!cache[id]) cache[id] = await loadTab(id);
    renderTab(id, cache[id]);
}

async function loadTab(id) {
    const uuid = patient.id;
    let endpoint;
    switch (id) {
        case 'profile':   return patient;
        case 'visits':    endpoint = `/patients/${uuid}/visits?per_page=50`; break;
        case 'photos':    endpoint = `/patients/${uuid}/photos`; break;
        case 'consents':  endpoint = `/patients/${uuid}/consents`; break;
        case 'lab':       endpoint = `/patients/${uuid}/lab-results`; break;
        case 'courses':   endpoint = `/patients/${uuid}/courses`; break;
        case 'financial': endpoint = `/patients/${uuid}/financial`; break;
    }
    if (!endpoint) return null;
    const r = await api.call(endpoint);
    return r.ok ? r.data.data : { error: r.data?.message || 'load failed' };
}

function renderTab(id, data) {
    const body = document.getElementById('tab-body');
    if (!data) { body.innerHTML = '<em class="text-slate-500">no data</em>'; return; }
    if (data.error) { body.innerHTML = `<div class="text-red-600">${data.error}</div>`; return; }

    if (id === 'profile') {
        body.innerHTML = `
            <dl class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                <div><dt class="text-slate-500 text-xs">ชื่อเล่น</dt><dd>${data.nickname || '—'}</dd></div>
                <div><dt class="text-slate-500 text-xs">เพศ</dt><dd>${({male:'ชาย',female:'หญิง',other:'อื่น ๆ'})[data.gender] || '—'}</dd></div>
                <div><dt class="text-slate-500 text-xs">วันเกิด</dt><dd>${data.date_of_birth || '—'}</dd></div>
                <div><dt class="text-slate-500 text-xs">เบอร์โทร</dt><dd>${data.phone || '—'}</dd></div>
                <div><dt class="text-slate-500 text-xs">Email</dt><dd>${data.email || '—'}</dd></div>
                <div><dt class="text-slate-500 text-xs">LINE ID</dt><dd>${data.line_id || '—'}</dd></div>
                <div class="col-span-full"><dt class="text-slate-500 text-xs">ที่อยู่</dt><dd>${data.address || '—'}</dd></div>
                <div class="col-span-full"><dt class="text-slate-500 text-xs">แพ้ยา</dt><dd class="text-red-700">${data.allergies || '—'}</dd></div>
                <div class="col-span-full"><dt class="text-slate-500 text-xs">โรคประจำตัว</dt><dd>${data.underlying_diseases || '—'}</dd></div>
            </dl>`;
        return;
    }

    if (id === 'visits') {
        if (!data.length) { body.innerHTML = '<em class="text-slate-500">ยังไม่มี Visit</em>'; return; }
        const VL = { waiting: 'รอเข้าตรวจ', in_progress: 'กำลังรักษา', completed: 'เสร็จสิ้น', cancelled: 'ยกเลิก' };
        const VC = { waiting: 'bg-amber-100 text-amber-800', in_progress: 'bg-blue-100 text-blue-800', completed: 'bg-emerald-100 text-emerald-800', cancelled: 'bg-slate-200 text-slate-600' };
        const IL = { draft: 'แบบร่าง', paid: 'ชำระแล้ว', partial: 'ชำระบางส่วน', voided: 'ยกเลิก', refunded: 'คืนเงินแล้ว' };
        const IC = { draft: 'bg-slate-100 text-slate-700', paid: 'bg-emerald-100 text-emerald-800', partial: 'bg-amber-100 text-amber-800', voided: 'bg-red-100 text-red-800', refunded: 'bg-orange-100 text-orange-800' };
        body.innerHTML = `<div class="overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-slate-50"><tr>
                <th class="text-left px-3 py-2">Visit #</th><th class="text-left px-3 py-2">วันเวลา</th>
                <th class="text-left px-3 py-2">แพทย์</th><th class="text-left px-3 py-2">สถานะ</th>
                <th class="text-right px-3 py-2">ยอดรวม</th><th class="text-left px-3 py-2">บิล</th>
            </tr></thead><tbody>${data.map(v => `
                <tr class="border-b">
                    <td class="px-3 py-2 font-mono text-xs">${v.visit_number}</td>
                    <td class="px-3 py-2 text-xs">${fmtDate(v.check_in_at)}</td>
                    <td class="px-3 py-2">${v.doctor?.name || '—'}</td>
                    <td class="px-3 py-2"><span class="px-2 py-1 rounded text-xs font-semibold ${VC[v.status] || ''}">${VL[v.status] || v.status}</span></td>
                    <td class="px-3 py-2 text-right">${(v.total_amount || 0).toLocaleString()}</td>
                    <td class="px-3 py-2 text-xs">${v.invoice_number ? `<span class="font-mono mr-1">${v.invoice_number}</span><span class="px-2 py-0.5 rounded text-xs font-semibold ${IC[v.invoice_status] || ''}">${IL[v.invoice_status] || v.invoice_status || ''}</span>` : '—'}</td>
                </tr>
            `).join('')}</tbody></table></div>`;
        return;
    }

    if (id === 'photos') {
        const TL = { before: 'ก่อน', after: 'หลัง', general: 'ทั่วไป' };
        const TC = { before: 'bg-amber-100 text-amber-800', after: 'bg-emerald-100 text-emerald-800', general: 'bg-slate-100 text-slate-600' };
        body.innerHTML = `
            <div class="mb-4 p-3 border rounded-lg bg-slate-50">
                <h4 class="font-semibold text-sm mb-2">+ อัปโหลดภาพใหม่</h4>
                <form id="photo-form" class="grid grid-cols-1 md:grid-cols-4 gap-2 text-sm">
                    <input type="file" name="file" accept="image/jpeg,image/png,image/webp" required class="border rounded px-2 py-1 md:col-span-2">
                    <select name="type" class="border rounded px-2 py-1">
                        <option value="general">ทั่วไป</option>
                        <option value="before">ก่อน (Before)</option>
                        <option value="after">หลัง (After)</option>
                    </select>
                    <button type="submit" class="bg-cyan-600 text-white px-3 py-1 rounded">อัปโหลด</button>
                    <input type="text" name="notes" placeholder="หมายเหตุ (optional)" class="border rounded px-2 py-1 md:col-span-4">
                </form>
            </div>
            ${(!data.length) ? '<em class="text-slate-500">ยังไม่มีภาพ — ลองอัปโหลดด้านบน</em>' : ''}
            <div id="photo-grid" class="grid grid-cols-2 md:grid-cols-4 gap-3">${data.map(p => `
                <div class="border rounded p-2 group relative" data-id="${p.id}">
                    <a href="${p.url || p.thumbnail_url}" target="_blank">
                        <img src="${p.thumbnail_url || p.url}" class="w-full aspect-square object-cover rounded bg-slate-100" alt="">
                    </a>
                    <div class="mt-1 flex items-center gap-1 text-xs">
                        <span class="px-1.5 py-0.5 rounded ${TC[p.type]||''}">${TL[p.type]||p.type}</span>
                        <span class="text-slate-500">${fmtDate(p.taken_at)}</span>
                        <button class="ml-auto text-rose-600 hover:underline photo-del" data-id="${p.id}">ลบ</button>
                    </div>
                    ${p.notes ? `<div class="text-xs text-slate-400 mt-1">${p.notes}</div>` : ''}
                </div>`).join('')}</div>`;
        document.getElementById('photo-form').addEventListener('submit', async e => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const r = await api.upload(`/patients/${patient.id}/photos`, fd);
            if (!r.ok) return alert((r.data && r.data.message) || 'อัปโหลดไม่ได้');
            cache.photos = null;
            switchTab('photos');
        });
        document.querySelectorAll('.photo-del').forEach(b => b.addEventListener('click', async () => {
            if (!confirm('ลบภาพนี้?')) return;
            const r = await api.call(`/photos/${b.dataset.id}`, { method: 'DELETE' });
            if (!r.ok) return alert((r.data && r.data.message) || 'ลบไม่ได้');
            cache.photos = null;
            switchTab('photos');
        }));
        return;
    }

    if (id === 'consents') {
        const CL = { pending: 'รอเซ็น', signed: 'เซ็นแล้ว', expired: 'หมดอายุ' };
        const CC = { pending: 'bg-amber-100 text-amber-800', signed: 'bg-emerald-100 text-emerald-800', expired: 'bg-slate-200 text-slate-600' };

        body.innerHTML = `
            <div class="mb-4 p-3 border rounded-lg bg-slate-50">
                <h4 class="font-semibold text-sm mb-2">+ ออกเอกสารยินยอมจาก template</h4>
                <form id="consent-form" class="grid grid-cols-1 md:grid-cols-3 gap-2 text-sm">
                    <select name="template_id" required class="border rounded px-2 py-1 md:col-span-2"><option value="">— กำลังโหลด —</option></select>
                    <button type="submit" class="bg-cyan-600 text-white px-3 py-1 rounded">ออกเอกสาร</button>
                </form>
            </div>
            ${(!data.length) ? '<em class="text-slate-500">ยังไม่มีเอกสารยินยอม</em>' : ''}
            <ul id="consent-list" class="divide-y">${data.map(c => `
                <li class="py-3 text-sm flex items-start gap-3" data-id="${c.id}">
                    <div class="flex-1">
                        <div class="font-semibold">${c.name}</div>
                        <div class="text-xs text-slate-500">หมดอายุ: ${c.expires_at || '—'} • เซ็น: ${fmtDate(c.signed_at)} โดย ${c.signed_by_name||'—'}</div>
                        ${c.signature_url ? `<img src="${c.signature_url}" class="h-12 mt-1 bg-white border rounded">` : ''}
                    </div>
                    <span class="px-2 py-0.5 rounded text-xs font-semibold self-start ${CC[c.status] || ''}">${CL[c.status] || c.status}</span>
                    ${c.status === 'pending' ? `<button class="consent-sign bg-emerald-600 text-white text-xs px-2 py-1 rounded self-start" data-id="${c.id}">✍️ เซ็น</button>` : ''}
                </li>`).join('')}</ul>`;

        // Load templates
        api.call('/consent-templates').then(r => {
            if (!r.ok) return;
            const sel = document.querySelector('[name=template_id]');
            sel.innerHTML = '<option value="">— เลือก —</option>' + (r.data.data || []).map(t => `<option value="${t.id}">${t.title}</option>`).join('');
        });
        document.getElementById('consent-form').addEventListener('submit', async e => {
            e.preventDefault();
            const f = e.target;
            const r = await api.call(`/patients/${patient.id}/consents`, {
                method: 'POST',
                body: JSON.stringify({ template_id: +f.template_id.value || null }),
            });
            if (!r.ok) return alert((r.data && r.data.message) || 'ทำไม่ได้');
            cache.consents = null;
            switchTab('consents');
        });
        document.querySelectorAll('.consent-sign').forEach(b => b.addEventListener('click', () => openSignDialog(+b.dataset.id)));
        return;
    }

    if (id === 'lab') {
        if (!data.length) { body.innerHTML = '<em class="text-slate-500">ยังไม่มีรายการ Lab</em>'; return; }
        const FL = { normal: 'ปกติ', low: 'ต่ำ', high: 'สูง', critical: 'วิกฤต' };
        const FC = { normal: 'bg-emerald-100 text-emerald-800', low: 'bg-blue-100 text-blue-800', high: 'bg-amber-100 text-amber-800', critical: 'bg-red-100 text-red-800' };
        const SL = { draft: 'ร่าง', sent: 'ส่งแลบ', completed: 'ผลออกแล้ว', cancelled: 'ยกเลิก' };
        const SC = { draft: 'bg-slate-100 text-slate-700', sent: 'bg-amber-100 text-amber-800', completed: 'bg-emerald-100 text-emerald-800', cancelled: 'bg-red-100 text-red-800' };
        body.innerHTML = data.map(o => `
            <div class="border rounded-lg mb-3">
                <div class="flex items-center gap-2 p-3 bg-slate-50 border-b">
                    <span class="font-mono text-xs">${o.order_no}</span>
                    <span class="px-2 py-0.5 rounded text-xs font-semibold ${SC[o.status]||''}">${SL[o.status]||o.status}</span>
                    <span class="text-xs text-slate-500 ml-auto">${fmtDate(o.ordered_at)}${o.result_date ? ' • ผลออก '+o.result_date : ''}</span>
                    <span class="text-xs text-slate-500">โดย ${o.ordered_by||'-'}</span>
                    ${o.report_url ? `<a href="${o.report_url}" target="_blank" class="text-xs text-cyan-700 hover:underline">📎 ใบรายงาน</a>` : ''}
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-white"><tr>
                        <th class="text-left px-3 py-1.5">รายการตรวจ</th>
                        <th class="text-right px-3 py-1.5">ค่าที่วัด</th>
                        <th class="text-left px-3 py-1.5">หน่วย</th>
                        <th class="text-left px-3 py-1.5">ค่าอ้างอิง</th>
                        <th class="text-left px-3 py-1.5">สถานะ</th>
                    </tr></thead>
                    <tbody>${(o.rows||[]).map(r => {
                        const v = r.value_numeric ?? r.value_text ?? '—';
                        const ref = r.ref_min !== null && r.ref_max !== null ? `${r.ref_min} - ${r.ref_max}` : (r.ref_text || '—');
                        return `<tr class="border-t">
                            <td class="px-3 py-1.5">${r.code ? `<span class="font-mono text-xs text-slate-500 mr-1">${r.code}</span>` : ''}${r.name||''}</td>
                            <td class="px-3 py-1.5 text-right font-bold">${v}</td>
                            <td class="px-3 py-1.5 text-xs text-slate-500">${r.unit||''}</td>
                            <td class="px-3 py-1.5 text-xs text-slate-500">${ref}</td>
                            <td class="px-3 py-1.5">${r.abnormal_flag ? `<span class="px-2 py-0.5 rounded text-xs font-semibold ${FC[r.abnormal_flag]||''}">${FL[r.abnormal_flag]||r.abnormal_flag}</span>` : ''}</td>
                        </tr>`;
                    }).join('')}</tbody>
                </table>
            </div>`).join('');
        return;
    }

    if (id === 'courses') {
        if (!data.length) { body.innerHTML = '<em class="text-slate-500">ยังไม่มีคอร์ส</em>'; return; }
        const CL = { active: 'กำลังใช้งาน', expired: 'หมดอายุ', completed: 'ครบแล้ว', cancelled: 'ยกเลิก' };
        const CC = { active: 'bg-emerald-100 text-emerald-800', expired: 'bg-slate-200 text-slate-600', completed: 'bg-blue-100 text-blue-800', cancelled: 'bg-red-100 text-red-800' };
        body.innerHTML = `<table class="min-w-full text-sm">
            <thead class="bg-slate-50"><tr>
                <th class="text-left px-3 py-2">ชื่อคอร์ส</th>
                <th class="text-left px-3 py-2">ใช้แล้ว / ทั้งหมด</th>
                <th class="text-left px-3 py-2">คงเหลือ</th>
                <th class="text-left px-3 py-2">หมดอายุ</th>
                <th class="text-left px-3 py-2">สถานะ</th>
            </tr></thead><tbody>${data.map(c => `
                <tr class="border-b">
                    <td class="px-3 py-2">${c.name}</td>
                    <td class="px-3 py-2">${c.used_sessions} / ${c.total_sessions}</td>
                    <td class="px-3 py-2">${c.remaining_sessions}</td>
                    <td class="px-3 py-2 text-xs">${c.expires_at || '—'}</td>
                    <td class="px-3 py-2"><span class="px-2 py-1 rounded text-xs font-semibold ${CC[c.status] || ''}">${CL[c.status] || c.status}</span></td>
                </tr>`).join('')}</tbody></table>`;
        return;
    }

    if (id === 'financial') {
        const member = data.member_account;
        body.innerHTML = `
            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="rounded-lg bg-slate-50 p-3"><div class="text-xs text-slate-500">ยอดสะสม</div><div class="font-bold text-lg">${(data.total_spent || 0).toLocaleString()}</div></div>
                <div class="rounded-lg bg-slate-50 p-3"><div class="text-xs text-slate-500">มาใช้บริการ</div><div class="font-bold text-lg">${data.visit_count || 0} ครั้ง</div></div>
                <div class="rounded-lg bg-slate-50 p-3"><div class="text-xs text-slate-500">มาล่าสุด</div><div class="font-bold text-sm">${fmtDate(data.last_visit_at)}</div></div>
            </div>
            ${member ? `<div class="rounded-lg bg-cyan-50 p-3 mb-4 text-sm">Member: ${member.package_name || ''} — ฝาก ${member.total_deposit.toLocaleString()} / ใช้ ${member.total_used.toLocaleString()} / คงเหลือ <strong>${member.balance.toLocaleString()}</strong></div>` : ''}
            <div class="overflow-x-auto"><table class="min-w-full text-sm">
                <thead class="bg-slate-50"><tr>
                    <th class="text-left px-3 py-2">เลขที่บิล</th>
                    <th class="text-left px-3 py-2">วันที่</th>
                    <th class="text-right px-3 py-2">ยอดรวม</th>
                    <th class="text-left px-3 py-2">สถานะ</th>
                </tr></thead>
                <tbody>                ${(() => {
                    const IL = { draft: 'แบบร่าง', paid: 'ชำระแล้ว', partial: 'ชำระบางส่วน', voided: 'ยกเลิก', refunded: 'คืนเงินแล้ว' };
                    const IC = { draft: 'bg-slate-100 text-slate-700', paid: 'bg-emerald-100 text-emerald-800', partial: 'bg-amber-100 text-amber-800', voided: 'bg-red-100 text-red-800', refunded: 'bg-orange-100 text-orange-800' };
                    return (data.invoices || []).map(i => `
                        <tr class="border-b">
                            <td class="px-3 py-2 font-mono text-xs">${i.invoice_number}</td>
                            <td class="px-3 py-2 text-xs">${i.invoice_date}</td>
                            <td class="px-3 py-2 text-right">${(i.total_amount || 0).toLocaleString()}</td>
                            <td class="px-3 py-2"><span class="px-2 py-1 rounded text-xs font-semibold ${IC[i.status] || ''}">${IL[i.status] || i.status}</span></td>
                        </tr>`).join('');
                })()}</tbody>
            </table></div>`;
        return;
    }
}

// ─── Signature canvas helpers ─────────────────────────────
let _activeConsentId = null;
function setupSignatureCanvas() {
    const canvas = document.getElementById('sig-canvas');
    if (!canvas || canvas.dataset.bound) return;
    canvas.dataset.bound = '1';
    const ctx = canvas.getContext('2d');
    let drawing = false;
    let last = null;

    function pos(e) {
        const r = canvas.getBoundingClientRect();
        const x = (e.touches?.[0]?.clientX ?? e.clientX) - r.left;
        const y = (e.touches?.[0]?.clientY ?? e.clientY) - r.top;
        return [x * canvas.width / r.width, y * canvas.height / r.height];
    }
    function start(e) { drawing = true; last = pos(e); e.preventDefault?.(); }
    function move(e) {
        if (!drawing) return;
        const [x, y] = pos(e);
        ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#0f172a';
        ctx.beginPath(); ctx.moveTo(last[0], last[1]); ctx.lineTo(x, y); ctx.stroke();
        last = [x, y];
        e.preventDefault?.();
    }
    function stop() { drawing = false; last = null; }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    canvas.addEventListener('mouseup', stop);
    canvas.addEventListener('mouseleave', stop);
    canvas.addEventListener('touchstart', start);
    canvas.addEventListener('touchmove', move);
    canvas.addEventListener('touchend', stop);

    document.getElementById('sig-clear').addEventListener('click', () => ctx.clearRect(0, 0, canvas.width, canvas.height));
}
function clearSignature() {
    const c = document.getElementById('sig-canvas');
    c?.getContext('2d').clearRect(0, 0, c.width, c.height);
}
function openSignDialog(consentId) {
    _activeConsentId = consentId;
    setupSignatureCanvas();
    clearSignature();
    document.getElementById('sign-dialog').showModal();
}
document.addEventListener('submit', async e => {
    if (e.target.id !== 'sign-form') return;
    e.preventDefault();
    const f = e.target;
    const dataUrl = document.getElementById('sig-canvas').toDataURL('image/png');
    if (dataUrl.length < 200) return alert('โปรดวาดลายเซ็นก่อน');
    const r = await api.call(`/consents/${_activeConsentId}/sign`, {
        method: 'POST',
        body: JSON.stringify({ signed_by_name: f.signed_by_name.value, signature: dataUrl }),
    });
    if (!r.ok) return alert((r.data && r.data.message) || 'บันทึกไม่ได้');
    f.reset();
    document.getElementById('sign-dialog').close();
    cache.consents = null;
    switchTab('consents');
});
document.addEventListener('click', e => {
    if (e.target.classList.contains('dlg-cancel')) e.target.closest('dialog').close();
});

(async function () {
    if (!api.token()) { window.location.href = '/login'; return; }
    const uuid = getUuidFromPath();
    if (!uuid) { window.location.href = '/patients'; return; }
    const r = await api.call('/patients/' + uuid);
    if (!r.ok) { document.getElementById('patient-header').innerHTML = '<span class="text-red-600">โหลดไม่สำเร็จ</span>'; return; }
    patient = r.data.data;
    document.getElementById('hn').textContent = patient.hn;
    document.getElementById('patient-header').innerHTML = `
        <div><div class="text-xs text-slate-500">ชื่อ-นามสกุล</div><div class="font-bold text-lg">${patient.first_name} ${patient.last_name}</div></div>
        <div><div class="text-xs text-slate-500">HN</div><div class="font-mono">${patient.hn}</div></div>
        <div><div class="text-xs text-slate-500">เบอร์โทร</div><div>${patient.phone || '—'}</div></div>`;
    cache.profile = patient;
    renderTabs();
    switchTab('profile');
})();
</script>
@endsection
