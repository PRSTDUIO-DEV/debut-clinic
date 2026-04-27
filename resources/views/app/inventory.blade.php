@extends('layouts.app')
@section('title', 'คลังสินค้า')

@section('body')
<div class="min-h-screen">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="/dashboard" class="text-cyan-700 hover:underline">← Dashboard</a>
            <h1 class="font-bold">📦 คลังสินค้า</h1>
            <span class="ml-auto text-sm text-slate-600" id="active-branch"></span>
            <a href="/inventory/receiving" class="text-sm bg-cyan-600 text-white px-3 py-1 rounded">รับเข้าสินค้า</a>
            <a href="/inventory/requisitions" class="text-sm bg-amber-600 text-white px-3 py-1 rounded">ใบเบิก</a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6 space-y-4">
        <div class="flex gap-2 flex-wrap" id="tabs">
            <button data-tab="products" class="tab-btn px-3 py-2 rounded bg-cyan-600 text-white">สินค้า</button>
            <button data-tab="main" class="tab-btn px-3 py-2 rounded bg-white border">สต็อกบน (Main)</button>
            <button data-tab="floor" class="tab-btn px-3 py-2 rounded bg-white border">สต็อกล่าง (Floor)</button>
            <button data-tab="low" class="tab-btn px-3 py-2 rounded bg-white border">สต็อกต่ำ</button>
            <button data-tab="expiry" class="tab-btn px-3 py-2 rounded bg-white border">วันหมดอายุ</button>
            <button data-tab="movements" class="tab-btn px-3 py-2 rounded bg-white border">เคลื่อนไหว</button>
        </div>

        <section id="panel" class="bg-white rounded-xl shadow p-4 min-h-[400px]"></section>
    </main>
</div>
@endsection

@section('scripts')
<script>
const BUCKET_LABEL = { green: 'ปกติ', yellow: '6 เดือน', orange: '3 เดือน', red: '1 เดือน', expired: 'หมดอายุ' };
const BUCKET_COLOR = {
    green: 'bg-emerald-100 text-emerald-800',
    yellow: 'bg-yellow-100 text-yellow-800',
    orange: 'bg-orange-100 text-orange-800',
    red: 'bg-red-100 text-red-800',
    expired: 'bg-slate-700 text-white',
};
const TYPE_LABEL = {
    receive: 'รับเข้า', issue: 'จ่ายออก',
    transfer_in: 'โอนเข้า', transfer_out: 'โอนออก',
    adjust: 'ปรับสต็อก', return: 'รับคืน',
    pos_deduct: 'ตัดที่ POS', void_restore: 'คืนจาก void',
};
const TYPE_COLOR = {
    receive: 'bg-emerald-100 text-emerald-800',
    pos_deduct: 'bg-blue-100 text-blue-800',
    transfer_in: 'bg-cyan-100 text-cyan-800',
    transfer_out: 'bg-amber-100 text-amber-800',
    adjust: 'bg-purple-100 text-purple-800',
    return: 'bg-rose-100 text-rose-800',
};

let active = 'products';

function setActiveButton(t) {
    document.querySelectorAll('.tab-btn').forEach(b => {
        const on = b.dataset.tab === t;
        b.className = `tab-btn px-3 py-2 rounded ${on ? 'bg-cyan-600 text-white' : 'bg-white border'}`;
    });
}

async function loadProducts() {
    const r = await api.call('/inventory/products?per_page=100');
    if (!r.ok) return panel.innerHTML = '<em class="text-red-600">โหลดไม่ได้</em>';
    const rows = r.data.data || [];
    panel.innerHTML = `
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-100"><tr>
            <th class="px-3 py-2 text-left">SKU</th><th class="px-3 py-2 text-left">ชื่อ</th>
            <th class="px-3 py-2 text-left">หมวด</th><th class="px-3 py-2 text-right">ราคาขาย</th>
            <th class="px-3 py-2 text-right">ทุน</th><th class="px-3 py-2 text-right">Reorder</th>
          </tr></thead>
          <tbody>${rows.map(p => `
            <tr class="border-t">
              <td class="px-3 py-2 font-mono text-xs">${p.sku}</td>
              <td class="px-3 py-2">${p.name}</td>
              <td class="px-3 py-2 text-slate-600">${p.category || '-'}</td>
              <td class="px-3 py-2 text-right">${(+p.selling_price).toFixed(2)}</td>
              <td class="px-3 py-2 text-right">${(+p.cost_price).toFixed(2)}</td>
              <td class="px-3 py-2 text-right">${p.reorder_point}</td>
            </tr>`).join('')}</tbody>
        </table>
      </div>`;
}

async function loadStock(type) {
    const r = await api.call(`/inventory/stock-levels?warehouse_type=${type}`);
    if (!r.ok) return panel.innerHTML = '<em class="text-red-600">โหลดไม่ได้</em>';
    const rows = r.data.data || [];
    panel.innerHTML = `
      <h3 class="font-semibold mb-3">${type === 'main' ? 'สต็อกบน (Main Warehouse)' : 'สต็อกล่าง (Floor)'}</h3>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-100"><tr>
            <th class="px-3 py-2 text-left">สินค้า</th>
            <th class="px-3 py-2 text-left">คลัง</th>
            <th class="px-3 py-2 text-left">Lot</th>
            <th class="px-3 py-2 text-left">วันหมดอายุ</th>
            <th class="px-3 py-2 text-right">คงเหลือ</th>
            <th class="px-3 py-2 text-right">ทุน</th>
            <th class="px-3 py-2 text-center">สถานะ</th>
          </tr></thead>
          <tbody>${rows.map(l => `
            <tr class="border-t">
              <td class="px-3 py-2"><div class="font-medium">${l.product.name}</div><div class="text-xs text-slate-500 font-mono">${l.product.sku}</div></td>
              <td class="px-3 py-2 text-slate-600">${l.warehouse.name}</td>
              <td class="px-3 py-2 font-mono text-xs">${l.lot_no || '-'}</td>
              <td class="px-3 py-2">${l.expiry_date || '-'}</td>
              <td class="px-3 py-2 text-right font-semibold">${l.quantity}</td>
              <td class="px-3 py-2 text-right">${(+l.cost_price).toFixed(2)}</td>
              <td class="px-3 py-2 text-center"><span class="px-2 py-1 rounded text-xs ${BUCKET_COLOR[l.expiry_bucket]||''}">${BUCKET_LABEL[l.expiry_bucket]||l.expiry_bucket}</span></td>
            </tr>`).join('') || '<tr><td colspan="7" class="text-center py-6 text-slate-500">ไม่มีข้อมูล</td></tr>'}</tbody>
        </table>
      </div>`;
}

async function loadLowStock() {
    const r = await api.call('/inventory/low-stock');
    if (!r.ok) return panel.innerHTML = '<em class="text-red-600">โหลดไม่ได้</em>';
    const rows = r.data.data || [];
    panel.innerHTML = `
      <h3 class="font-semibold mb-3">สต็อกต่ำกว่า reorder point</h3>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-100"><tr>
            <th class="px-3 py-2 text-left">SKU</th>
            <th class="px-3 py-2 text-left">ชื่อ</th>
            <th class="px-3 py-2 text-right">คงเหลือ</th>
            <th class="px-3 py-2 text-right">Reorder</th>
            <th class="px-3 py-2 text-right">ขาด</th>
          </tr></thead>
          <tbody>${rows.map(p => `
            <tr class="border-t ${p.shortage > 0 ? 'bg-rose-50' : ''}">
              <td class="px-3 py-2 font-mono text-xs">${p.sku}</td>
              <td class="px-3 py-2">${p.name}</td>
              <td class="px-3 py-2 text-right font-semibold">${p.total_qty} ${p.unit||''}</td>
              <td class="px-3 py-2 text-right">${p.reorder_point}</td>
              <td class="px-3 py-2 text-right ${p.shortage > 0 ? 'text-rose-700 font-bold' : 'text-slate-400'}">${p.shortage}</td>
            </tr>`).join('') || '<tr><td colspan="5" class="text-center py-6 text-emerald-700">ไม่มีสินค้าใต้ระดับ reorder</td></tr>'}</tbody>
        </table>
      </div>`;
}

async function loadExpiry() {
    const r = await api.call('/inventory/expiry-alerts');
    if (!r.ok) return panel.innerHTML = '<em class="text-red-600">โหลดไม่ได้</em>';
    const rows = r.data.data || [];
    const summary = r.data.meta?.summary || {};
    panel.innerHTML = `
      <h3 class="font-semibold mb-3">วันหมดอายุ</h3>
      <div class="grid grid-cols-2 md:grid-cols-5 gap-2 mb-4">
        ${['expired','red','orange','yellow','green'].map(b => `
          <div class="p-3 rounded-lg ${BUCKET_COLOR[b]} text-center">
            <div class="text-xs">${BUCKET_LABEL[b]}</div>
            <div class="text-2xl font-bold">${summary[b] || 0}</div>
          </div>`).join('')}
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-100"><tr>
            <th class="px-3 py-2 text-left">สินค้า</th>
            <th class="px-3 py-2 text-left">คลัง</th>
            <th class="px-3 py-2 text-left">Lot</th>
            <th class="px-3 py-2 text-left">หมดอายุ</th>
            <th class="px-3 py-2 text-right">คงเหลือ</th>
            <th class="px-3 py-2 text-center">สถานะ</th>
          </tr></thead>
          <tbody>${rows.map(l => `
            <tr class="border-t">
              <td class="px-3 py-2">${l.product}<div class="text-xs text-slate-500 font-mono">${l.sku}</div></td>
              <td class="px-3 py-2">${l.warehouse}</td>
              <td class="px-3 py-2 font-mono text-xs">${l.lot_no || '-'}</td>
              <td class="px-3 py-2">${l.expiry_date}</td>
              <td class="px-3 py-2 text-right font-semibold">${l.quantity}</td>
              <td class="px-3 py-2 text-center"><span class="px-2 py-1 rounded text-xs ${BUCKET_COLOR[l.bucket]||''}">${BUCKET_LABEL[l.bucket]||l.bucket}</span></td>
            </tr>`).join('') || '<tr><td colspan="6" class="text-center py-6 text-slate-500">ไม่มี Lot ที่ใกล้หมดอายุ</td></tr>'}</tbody>
        </table>
      </div>`;
}

async function loadMovements() {
    const r = await api.call('/inventory/movements?per_page=50');
    if (!r.ok) return panel.innerHTML = '<em class="text-red-600">โหลดไม่ได้</em>';
    const rows = r.data.data || [];
    panel.innerHTML = `
      <h3 class="font-semibold mb-3">ประวัติเคลื่อนไหว</h3>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-100"><tr>
            <th class="px-3 py-2 text-left">เวลา</th>
            <th class="px-3 py-2 text-left">ประเภท</th>
            <th class="px-3 py-2 text-left">สินค้า</th>
            <th class="px-3 py-2 text-left">คลัง</th>
            <th class="px-3 py-2 text-right">เปลี่ยน</th>
            <th class="px-3 py-2 text-right">คงเหลือ</th>
            <th class="px-3 py-2 text-left">อ้างอิง</th>
          </tr></thead>
          <tbody>${rows.map(m => `
            <tr class="border-t">
              <td class="px-3 py-2 text-xs text-slate-500">${m.created_at?.replace('T',' ').slice(0,16)}</td>
              <td class="px-3 py-2"><span class="px-2 py-1 rounded text-xs ${TYPE_COLOR[m.type]||'bg-slate-100'}">${TYPE_LABEL[m.type]||m.type}</span></td>
              <td class="px-3 py-2">${m.product||'-'}</td>
              <td class="px-3 py-2">${m.warehouse||'-'}</td>
              <td class="px-3 py-2 text-right ${m.quantity>=0?'text-emerald-700':'text-rose-700'} font-bold">${m.quantity>=0?'+':''}${m.quantity}</td>
              <td class="px-3 py-2 text-right">${m.after_qty}</td>
              <td class="px-3 py-2 text-xs text-slate-500">${m.reference_type||''}#${m.reference_id||''}</td>
            </tr>`).join('') || '<tr><td colspan="7" class="text-center py-6 text-slate-500">ยังไม่มีรายการ</td></tr>'}</tbody>
        </table>
      </div>`;
}

async function activate(t) {
    active = t;
    setActiveButton(t);
    panel.innerHTML = '<div class="text-slate-400">โหลด...</div>';
    if (t === 'products') return loadProducts();
    if (t === 'main' || t === 'floor') return loadStock(t);
    if (t === 'low') return loadLowStock();
    if (t === 'expiry') return loadExpiry();
    if (t === 'movements') return loadMovements();
}

const panel = document.getElementById('panel');

async function bootstrap() {
    if (!api.token()) return window.location.href = '/login';
    const me = await api.call('/auth/me');
    if (!me.ok) { api.clear(); return window.location.href = '/login'; }
    const branches = me.data.data.branches || [];
    document.getElementById('active-branch').textContent = 'สาขา: ' + (branches.find(b => b.id == api.branchId())?.name || '?');

    document.querySelectorAll('.tab-btn').forEach(b => b.addEventListener('click', () => activate(b.dataset.tab)));
    const initial = (location.hash || '#products').replace('#', '');
    activate(['products','main','floor','low','expiry','movements'].includes(initial) ? initial : 'products');
}
bootstrap();
</script>
@endsection
