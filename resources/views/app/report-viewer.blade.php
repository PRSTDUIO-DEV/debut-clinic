@extends('layouts.app')
@section('title', 'Report Viewer')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
            <a href="/reports" class="text-cyan-700 hover:underline">← Reports</a>
            <h1 id="title" class="font-bold">Report</h1>
            <button id="btn-export" class="ml-auto bg-emerald-600 text-white px-3 py-1 rounded text-sm">📥 Export CSV</button>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
        <section class="bg-white rounded-xl shadow p-4">
            <div id="filter" class="flex flex-wrap gap-2 items-end mb-3"></div>
            <div id="totals" class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-3"></div>
            <div id="content" class="overflow-x-auto"></div>
        </section>
    </main>
</div>
@endsection

@section('scripts')
<script>
const REPORTS = {
    'cohort-retention': {
        title: '🔁 Cohort Retention',
        endpoint: '/reports/cohort-retention',
        filters: [['months', 'จำนวนเดือน', 'number', 6]],
        columns: [
            ['cohort', 'Cohort'],
            ['cohort_size', 'ขนาด'],
            ['visited_1plus', '≥1 ครั้ง'],
            ['visited_2plus', '≥2 ครั้ง'],
            ['visited_3plus', '≥3 ครั้ง'],
            ['visited_4plus', '≥4 ครั้ง'],
            ['retention_2_pct', 'Retention 2+ (%)'],
            ['retention_3_pct', 'Retention 3+ (%)'],
        ],
    },
    'demographics': {
        title: '👥 Patient Demographics',
        endpoint: '/reports/demographics',
        filters: [],
        custom: renderDemographics,
    },
    'revenue-by-customer-group': {
        title: '🎯 รายได้ตามกลุ่มลูกค้า',
        endpoint: '/reports/revenue-by-customer-group',
        filters: [['from', 'จาก', 'date'], ['to', 'ถึง', 'date']],
        columns: [['name', 'กลุ่ม'], ['patients', 'จำนวนคน'], ['invoices', 'บิล'], ['revenue', 'รายได้', 'currency'], ['avg_per_patient', 'เฉลี่ย/คน', 'currency']],
        totals: ['revenue', 'patients', 'invoices'],
    },
    'revenue-by-source': {
        title: '📥 รายได้ตาม Source',
        endpoint: '/reports/revenue-by-source',
        filters: [['from', 'จาก', 'date'], ['to', 'ถึง', 'date']],
        columns: [['source', 'Source'], ['count', 'บิล'], ['revenue', 'รายได้', 'currency']],
        totals: ['revenue'],
    },
    'stock-value': {
        title: '📦 มูลค่าสต็อก',
        endpoint: '/reports/stock-value',
        filters: [],
        custom: renderStockValue,
    },
    'receiving-history': {
        title: '📥 ประวัติรับเข้าตามผู้ขาย',
        endpoint: '/reports/receiving-history',
        filters: [['from', 'จาก', 'date'], ['to', 'ถึง', 'date']],
        columns: [['name', 'ผู้ขาย'], ['count', 'จำนวนใบ'], ['total', 'มูลค่ารวม', 'currency']],
        totals: ['total', 'count'],
    },
    'course-outstanding': {
        title: '🎫 คอร์ส Outstanding',
        endpoint: '/reports/course-outstanding',
        filters: [],
        columns: [
            ['patient.name', 'ลูกค้า'],
            ['patient.hn', 'HN'],
            ['name', 'คอร์ส'],
            ['used', 'ใช้แล้ว'],
            ['remaining', 'เหลือ'],
            ['expires_at', 'หมดอายุ'],
            ['days_to_expiry', 'อีก (วัน)'],
        ],
        totals: ['courses', 'sessions'],
    },
    'wallet-outstanding': {
        title: '💎 Wallet ค้างจ่าย',
        endpoint: '/reports/wallet-outstanding',
        filters: [],
        columns: [
            ['patient.name', 'ลูกค้า'],
            ['patient.hn', 'HN'],
            ['balance', 'ยอดคงเหลือ', 'currency'],
            ['last_topup_at', 'เติมล่าสุด'],
            ['days_since_topup', 'ห่าง (วัน)'],
        ],
        totals: ['count', 'balance'],
    },
    'member-topup-trend': {
        title: '📈 Member Top-up Trend',
        endpoint: '/reports/member-topup-trend',
        filters: [['months', 'จำนวนเดือน', 'number', 12]],
        columns: [['month', 'เดือน'], ['count', 'จำนวนครั้ง'], ['total', 'รวม', 'currency']],
    },
    'commission-pending-vs-paid': {
        title: '💸 Commission Pending vs Paid',
        endpoint: '/reports/commission-pending-vs-paid',
        filters: [['month', 'เดือน', 'month']],
        columns: [['name', 'ผู้รับ'], ['count', 'รายการ'], ['paid', 'จ่ายแล้ว', 'currency'], ['pending', 'ค้างจ่าย', 'currency'], ['total', 'รวม', 'currency']],
        totals: ['paid', 'pending'],
    },
    'birthday-this-month': {
        title: '🎂 วันเกิดเดือนนี้',
        endpoint: '/reports/birthday-this-month',
        filters: [['month', 'เดือน', 'number', new Date().getMonth() + 1]],
        columns: [['name', 'ชื่อ'], ['hn', 'HN'], ['birthday', 'วันเกิด (mm-dd)'], ['turning_age', 'อายุครบ'], ['phone', 'เบอร์'], ['line_id', 'LINE']],
    },
    'lab-turnaround': {
        title: '🧪 Lab Turnaround Time',
        endpoint: '/reports/lab-turnaround',
        filters: [['from', 'จาก', 'date'], ['to', 'ถึง', 'date']],
        columns: [['order_no', 'Order No.'], ['ordered_at', 'สั่งวันที่'], ['result_date', 'ผลออก'], ['turnaround_days', 'จำนวนวัน']],
        totals: ['count', 'avg_days', 'max_days'],
    },
    'doctor-utilization': {
        title: '⏰ Doctor Utilization',
        endpoint: '/reports/doctor-utilization',
        filters: [['from', 'จาก', 'date'], ['to', 'ถึง', 'date']],
        columns: [['name', 'แพทย์'], ['visits', 'จำนวน Visit'], ['active_days', 'วันที่ทำงาน'], ['avg_per_day', 'เฉลี่ย/วัน']],
    },
    'room-utilization': {
        title: '🚪 Room Utilization',
        endpoint: '/reports/room-utilization',
        filters: [['from', 'จาก', 'date'], ['to', 'ถึง', 'date']],
        columns: [['name', 'ห้อง'], ['visits', 'Visit'], ['active_days', 'วันที่ใช้']],
    },
    'photo-upload-frequency': {
        title: '📷 Photo Upload Frequency',
        endpoint: '/reports/photo-upload-frequency',
        filters: [['days', 'จำนวนวัน', 'number', 90]],
        columns: [['name', 'ลูกค้า'], ['hn', 'HN'], ['photo_count', 'จำนวนภาพ']],
        totals: ['total_uploads'],
    },
    'refund-history': {
        title: '↩️ ประวัติคืนเงิน',
        endpoint: '/reports/refund-history',
        filters: [['from', 'จาก', 'date'], ['to', 'ถึง', 'date']],
        columns: [['patient.name', 'ลูกค้า'], ['patient.hn', 'HN'], ['amount', 'จำนวน', 'currency'], ['notes', 'หมายเหตุ'], ['created_at', 'วันที่']],
        totals: ['count', 'amount'],
    },
};

function fmt(v, type) {
    if (v === null || v === undefined) return '—';
    if (type === 'currency') return (+v).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
    if (typeof v === 'number') return (+v).toLocaleString();
    return v;
}

function getNested(obj, key) {
    return key.split('.').reduce((o, k) => (o ? o[k] : undefined), obj);
}

let currentReport = null;
let currentRows = [];

async function load() {
    const params = new URLSearchParams();
    document.querySelectorAll('#filter [data-key]').forEach(el => {
        if (el.value) params.set(el.dataset.key, el.value);
    });

    const r = await api.call(currentReport.endpoint + '?' + params.toString());
    if (!r.ok) return;
    const data = r.data.data;

    if (currentReport.custom) {
        currentReport.custom(data);
        return;
    }

    const rows = data.rows || data;
    currentRows = Array.isArray(rows) ? rows : [];

    const cols = currentReport.columns || [];
    document.getElementById('content').innerHTML = `
        <table class="min-w-full text-sm">
            <thead class="bg-slate-100"><tr>
                ${cols.map(([_, label, type]) => `<th class="px-3 py-2 ${type === 'currency' ? 'text-right' : 'text-left'}">${label}</th>`).join('')}
            </tr></thead>
            <tbody>${currentRows.map(row => `
                <tr class="border-t">
                    ${cols.map(([key, _, type]) => `<td class="px-3 py-1.5 ${type === 'currency' ? 'text-right' : ''}">${fmt(getNested(row, key), type)}</td>`).join('')}
                </tr>`).join('') || `<tr><td colspan="${cols.length}" class="text-center py-6 text-slate-500">ไม่มีข้อมูล</td></tr>`}</tbody>
        </table>`;

    const totals = currentReport.totals || [];
    if (totals.length && data.totals) {
        document.getElementById('totals').innerHTML = totals.map(k => `
            <div class="bg-slate-50 rounded p-3">
                <div class="text-xs text-slate-500">${k}</div>
                <div class="text-lg font-bold">${fmt(data.totals[k], typeof data.totals[k] === 'number' && k !== 'count' && k !== 'patients' && k !== 'invoices' && k !== 'units' && k !== 'visits' ? 'currency' : '')}</div>
            </div>`).join('');
    } else {
        document.getElementById('totals').innerHTML = '';
    }
}

function renderDemographics(data) {
    document.getElementById('content').innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <h3 class="font-semibold mb-2">เพศ</h3>
                <table class="min-w-full text-sm"><tbody>
                    ${Object.entries(data.by_gender).map(([k, v]) => `<tr class="border-t"><td class="px-2 py-1">${k}</td><td class="px-2 py-1 text-right">${v}</td></tr>`).join('')}
                </tbody></table>
            </div>
            <div>
                <h3 class="font-semibold mb-2">อายุ</h3>
                <table class="min-w-full text-sm"><tbody>
                    ${Object.entries(data.by_age).map(([k, v]) => `<tr class="border-t"><td class="px-2 py-1">${k}</td><td class="px-2 py-1 text-right">${v}</td></tr>`).join('')}
                </tbody></table>
            </div>
            <div>
                <h3 class="font-semibold mb-2">กลุ่มลูกค้า</h3>
                <table class="min-w-full text-sm"><tbody>
                    ${(data.by_customer_group||[]).map(g => `<tr class="border-t"><td class="px-2 py-1">${g.name}</td><td class="px-2 py-1 text-right">${g.count}</td></tr>`).join('')}
                </tbody></table>
            </div>
        </div>
        <div class="mt-3 text-xs text-slate-500">รวม ${data.total} คน</div>`;
    document.getElementById('totals').innerHTML = '';
}

function renderStockValue(data) {
    document.getElementById('content').innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <h3 class="font-semibold mb-2">ตามคลัง</h3>
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100"><tr><th class="px-2 py-1 text-left">คลัง</th><th class="px-2 py-1 text-left">Type</th><th class="px-2 py-1 text-right">Lot</th><th class="px-2 py-1 text-right">มูลค่า</th></tr></thead>
                    <tbody>${data.by_warehouse.map(r => `<tr class="border-t"><td class="px-2 py-1">${r.name}</td><td class="px-2 py-1">${r.type}</td><td class="px-2 py-1 text-right">${r.lot_count}</td><td class="px-2 py-1 text-right">${fmt(r.value, 'currency')}</td></tr>`).join('')}</tbody>
                </table>
            </div>
            <div>
                <h3 class="font-semibold mb-2">ตามหมวดสินค้า</h3>
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100"><tr><th class="px-2 py-1 text-left">หมวด</th><th class="px-2 py-1 text-right">หน่วย</th><th class="px-2 py-1 text-right">มูลค่า</th></tr></thead>
                    <tbody>${data.by_category.map(r => `<tr class="border-t"><td class="px-2 py-1">${r.name}</td><td class="px-2 py-1 text-right">${r.units}</td><td class="px-2 py-1 text-right">${fmt(r.value, 'currency')}</td></tr>`).join('')}</tbody>
                </table>
            </div>
        </div>
        <div class="mt-3 text-xs text-slate-500">As of ${data.as_of} • รวมมูลค่า: <b>${fmt(data.total_value, 'currency')}</b></div>`;
    document.getElementById('totals').innerHTML = '';
}

function buildFilters() {
    const filters = currentReport.filters || [];
    document.getElementById('filter').innerHTML = filters.map(([key, label, type, def]) => {
        let val = def !== undefined ? def : '';
        if (type === 'date' && !val) {
            const today = new Date();
            val = (key === 'from'
                ? new Date(today.getFullYear(), today.getMonth(), 1)
                : today).toISOString().slice(0, 10);
        }
        if (type === 'month' && !val) val = new Date().toISOString().slice(0, 7);

        return `
            <label class="text-sm">${label}
                <input data-key="${key}" type="${type}" value="${val}" class="border rounded px-2 py-1 mt-1">
            </label>`;
    }).join('') + (filters.length > 0 ? `<button id="btn-load" class="bg-cyan-600 text-white px-3 py-1 rounded text-sm">โหลด</button>` : '');
    document.getElementById('btn-load')?.addEventListener('click', load);
}

document.getElementById('btn-export').addEventListener('click', () => {
    if (!currentReport || !currentReport.columns) return alert('Export ไม่รองรับ report นี้');
    const cols = currentReport.columns;
    const headers = cols.map(([_, label]) => label).join(',');
    const lines = currentRows.map(row =>
        cols.map(([k]) => {
            let v = getNested(row, k);
            v = v === null || v === undefined ? '' : String(v);
            // CSV escape
            if (v.includes(',') || v.includes('"') || v.includes('\n')) v = '"' + v.replace(/"/g, '""') + '"';
            return v;
        }).join(',')
    );
    const csv = '\uFEFF' + headers + '\n' + lines.join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = (location.pathname.split('/').pop() || 'report') + '.csv';
    a.click();
    URL.revokeObjectURL(url);
});

(async function () {
    if (!api.token()) return window.location.href = '/login';
    const slug = location.pathname.split('/').pop();
    currentReport = REPORTS[slug];
    if (!currentReport) {
        document.getElementById('content').innerHTML = `<div class="text-rose-600">Unknown report: ${slug}</div>`;
        return;
    }
    document.getElementById('title').textContent = currentReport.title;
    buildFilters();
    await load();
})();
</script>
@endsection
