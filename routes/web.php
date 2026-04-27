<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\PublicMarketingController;
use Illuminate\Support\Facades\Route;

// Public health endpoints (no auth, no session)
Route::get('/health', [HealthController::class, 'health'])->name('health');
Route::get('/ready', [HealthController::class, 'ready'])->name('ready');

Route::get('/', fn () => redirect()->route('login'));
Route::view('/login', 'auth.login')->name('login');
Route::view('/dashboard', 'app.dashboard')->name('dashboard');
Route::view('/patients', 'app.patients')->name('patients');
Route::view('/patients/{uuid}', 'app.opd-card')->name('opd-card')->where('uuid', '[0-9a-f-]{36}');
Route::view('/appointments', 'app.appointments')->name('appointments');
Route::view('/follow-ups', 'app.follow-ups')->name('follow-ups');
Route::view('/pos', 'app.pos')->name('pos');
Route::view('/commissions', 'app.commissions')->name('commissions');
Route::view('/mis', 'app.mis-dashboard')->name('mis');
Route::view('/reports', 'app.reports-index')->name('reports');
Route::view('/reports/payment-mix', 'app.payment-mix')->name('reports.payment-mix');
Route::view('/reports/daily-pl', 'app.report-daily-pl')->name('reports.daily-pl');
Route::view('/reports/doctor-performance', 'app.report-doctor-performance')->name('reports.doctor-performance');
Route::view('/reports/procedure-performance', 'app.report-procedure-performance')->name('reports.procedure-performance');
// Sprint 15: 15 reports via shared viewer
foreach ([
    'cohort-retention', 'demographics',
    'revenue-by-customer-group', 'revenue-by-source',
    'stock-value', 'receiving-history',
    'course-outstanding', 'wallet-outstanding',
    'member-topup-trend', 'commission-pending-vs-paid',
    'birthday-this-month', 'lab-turnaround',
    'doctor-utilization', 'room-utilization',
    'photo-upload-frequency', 'refund-history',
] as $slug) {
    Route::view("/reports/{$slug}", 'app.report-viewer')->name("reports.{$slug}");
}
Route::view('/expenses', 'app.expenses')->name('expenses');
Route::view('/closing', 'app.closing')->name('closing');
Route::view('/inventory', 'app.inventory')->name('inventory');
Route::view('/inventory/receiving', 'app.inventory-receiving')->name('inventory.receiving');
Route::view('/inventory/requisitions', 'app.inventory-requisitions')->name('inventory.requisitions');
Route::view('/members', 'app.members')->name('members');
Route::view('/courses', 'app.courses')->name('courses');
Route::view('/notifications', 'app.notifications')->name('notifications');
Route::view('/admin/birthday-campaigns', 'app.birthday-campaigns')->name('admin.birthday-campaigns');
Route::view('/admin/follow-up-rules', 'app.follow-up-rules')->name('admin.follow-up-rules');
Route::view('/admin/messaging-providers', 'app.messaging-providers')->name('admin.messaging-providers');
Route::view('/admin/messaging-logs', 'app.messaging-logs')->name('admin.messaging-logs');
Route::view('/liff/link-patient', 'liff.link-patient')->name('liff.link-patient');
Route::view('/admin/consent-templates', 'app.consent-templates')->name('admin.consent-templates');
Route::view('/admin/lab-tests', 'app.lab-tests')->name('admin.lab-tests');
Route::view('/lab/orders', 'app.lab-orders')->name('lab.orders');
Route::view('/crm/segments', 'app.crm-segments')->name('crm.segments');
Route::view('/crm/templates', 'app.crm-templates')->name('crm.templates');
Route::view('/crm/campaigns', 'app.crm-campaigns')->name('crm.campaigns');
Route::view('/accounting/pr', 'app.accounting-pr')->name('accounting.pr');
Route::view('/accounting/po', 'app.accounting-po')->name('accounting.po');
Route::view('/accounting/disbursements', 'app.accounting-disbursements')->name('accounting.disbursements');
Route::view('/accounting/tax-invoices', 'app.accounting-tax')->name('accounting.tax');
Route::view('/accounting/ledger', 'app.accounting-ledger')->name('accounting.ledger');
Route::view('/accounting/coa', 'app.accounting-coa')->name('accounting.coa');
Route::view('/admin/permissions', 'app.permissions')->name('admin.permissions');

// Sprint 19: QC + Audit + System Health
Route::view('/qc/checklists', 'app.qc-checklists')->name('qc.checklists');
Route::view('/qc/runs', 'app.qc-runs')->name('qc.runs');
Route::view('/qc/runs/{id}', 'app.qc-run-detail')->name('qc.run.detail')->where('id', '[0-9]+');
Route::view('/audit-logs', 'app.audit-logs')->name('audit-logs');
Route::view('/admin/system-health', 'app.system-health')->name('admin.system-health');

// Settings hub + CRUD pages
Route::view('/admin/settings', 'app.settings-hub')->name('admin.settings');

Route::get('/admin/branches', fn () => view('app.settings-crud', ['config' => [
    'title' => '🏥 สาขา', 'endpoint' => '/admin/branches',
    'fields' => [
        ['key' => 'name', 'label' => 'ชื่อสาขา', 'type' => 'text', 'required' => true],
        ['key' => 'code', 'label' => 'รหัส', 'type' => 'text', 'required' => true],
        ['key' => 'phone', 'label' => 'เบอร์โทร', 'type' => 'text'],
        ['key' => 'address', 'label' => 'ที่อยู่', 'type' => 'textarea'],
        ['key' => 'is_active', 'label' => 'Active', 'type' => 'boolean', 'defaultValue' => true],
    ],
    'columns' => [['code', 'รหัส'], ['name', 'ชื่อ'], ['phone', 'เบอร์'], ['is_active', 'Active', 'boolean']],
]]))->name('admin.branches');

Route::get('/admin/rooms', fn () => view('app.settings-crud', ['config' => [
    'title' => '🚪 ห้องตรวจ', 'endpoint' => '/admin/rooms',
    'fields' => [
        ['key' => 'name', 'label' => 'ชื่อห้อง', 'type' => 'text', 'required' => true],
        ['key' => 'type', 'label' => 'ประเภท', 'type' => 'text'],
        ['key' => 'floor', 'label' => 'ชั้น', 'type' => 'text'],
        ['key' => 'position', 'label' => 'ลำดับ', 'type' => 'number'],
        ['key' => 'is_active', 'label' => 'Active', 'type' => 'boolean', 'defaultValue' => true],
    ],
    'columns' => [['name', 'ชื่อ'], ['type', 'ประเภท'], ['floor', 'ชั้น'], ['is_active', 'Active', 'boolean']],
]]))->name('admin.rooms');

Route::get('/admin/banks', fn () => view('app.settings-crud', ['config' => [
    'title' => '🏦 ธนาคาร', 'endpoint' => '/admin/banks',
    'fields' => [
        ['key' => 'name', 'label' => 'ชื่อธนาคาร', 'type' => 'text', 'required' => true],
        ['key' => 'account_no', 'label' => 'เลขบัญชี', 'type' => 'text'],
        ['key' => 'mdr_rate', 'label' => 'MDR Rate (%)', 'type' => 'pct'],
        ['key' => 'position', 'label' => 'ลำดับ', 'type' => 'number'],
        ['key' => 'is_active', 'label' => 'Active', 'type' => 'boolean', 'defaultValue' => true],
    ],
    'columns' => [['name', 'ธนาคาร'], ['account_no', 'เลขบัญชี'], ['mdr_rate', 'MDR (%)', 'pct'], ['is_active', 'Active', 'boolean']],
]]))->name('admin.banks');

Route::get('/admin/customer-groups', fn () => view('app.settings-crud', ['config' => [
    'title' => '🎯 กลุ่มลูกค้า', 'endpoint' => '/admin/customer-groups',
    'fields' => [
        ['key' => 'name', 'label' => 'ชื่อกลุ่ม', 'type' => 'text', 'required' => true],
        ['key' => 'discount_rate', 'label' => 'ส่วนลด (%)', 'type' => 'pct'],
        ['key' => 'description', 'label' => 'รายละเอียด', 'type' => 'textarea'],
        ['key' => 'color', 'label' => 'สี', 'type' => 'color', 'defaultValue' => '#0891b2'],
        ['key' => 'icon', 'label' => 'Icon (emoji)', 'type' => 'text'],
        ['key' => 'position', 'label' => 'ลำดับ', 'type' => 'number'],
        ['key' => 'is_active', 'label' => 'Active', 'type' => 'boolean', 'defaultValue' => true],
    ],
    'columns' => [['icon', 'Icon'], ['name', 'ชื่อ'], ['discount_rate', 'ส่วนลด (%)', 'pct'], ['color', 'สี', 'color'], ['is_active', 'Active', 'boolean']],
]]))->name('admin.customer-groups');

Route::get('/admin/suppliers', fn () => view('app.settings-crud', ['config' => [
    'title' => '🚚 ผู้ขาย', 'endpoint' => '/admin/suppliers',
    'fields' => [
        ['key' => 'name', 'label' => 'ชื่อผู้ขาย', 'type' => 'text', 'required' => true],
        ['key' => 'contact_person', 'label' => 'ผู้ติดต่อ', 'type' => 'text'],
        ['key' => 'phone', 'label' => 'เบอร์โทร', 'type' => 'text'],
        ['key' => 'email', 'label' => 'อีเมล', 'type' => 'email'],
        ['key' => 'address', 'label' => 'ที่อยู่', 'type' => 'textarea'],
        ['key' => 'tax_id', 'label' => 'เลขผู้เสียภาษี', 'type' => 'text'],
        ['key' => 'payment_terms', 'label' => 'เงื่อนไขการชำระ', 'type' => 'text'],
        ['key' => 'is_active', 'label' => 'Active', 'type' => 'boolean', 'defaultValue' => true],
    ],
    'columns' => [['name', 'ชื่อ'], ['contact_person', 'ผู้ติดต่อ'], ['phone', 'เบอร์'], ['payment_terms', 'เครดิต'], ['is_active', 'Active', 'boolean']],
]]))->name('admin.suppliers');

Route::get('/admin/expense-categories', fn () => view('app.settings-crud', ['config' => [
    'title' => '💸 หมวดรายจ่าย', 'endpoint' => '/admin/expense-categories',
    'fields' => [
        ['key' => 'name', 'label' => 'ชื่อหมวด', 'type' => 'text', 'required' => true],
        ['key' => 'color', 'label' => 'สี', 'type' => 'color', 'defaultValue' => '#dc2626'],
        ['key' => 'icon', 'label' => 'Icon (emoji)', 'type' => 'text'],
        ['key' => 'position', 'label' => 'ลำดับ', 'type' => 'number'],
        ['key' => 'is_active', 'label' => 'Active', 'type' => 'boolean', 'defaultValue' => true],
    ],
    'columns' => [['icon', 'Icon'], ['name', 'ชื่อ'], ['color', 'สี', 'color'], ['is_active', 'Active', 'boolean']],
]]))->name('admin.expense-categories');

Route::get('/admin/procedures', fn () => view('app.settings-crud', ['config' => [
    'title' => '💉 หัตถการ', 'endpoint' => '/admin/procedures',
    'fields' => [
        ['key' => 'code', 'label' => 'รหัส', 'type' => 'text', 'required' => true],
        ['key' => 'name', 'label' => 'ชื่อ', 'type' => 'text', 'required' => true],
        ['key' => 'category', 'label' => 'หมวด', 'type' => 'text'],
        ['key' => 'price', 'label' => 'ราคา', 'type' => 'currency', 'required' => true],
        ['key' => 'cost', 'label' => 'ต้นทุน', 'type' => 'currency'],
        ['key' => 'doctor_fee_rate', 'label' => 'ค่ามือแพทย์ (%)', 'type' => 'pct'],
        ['key' => 'staff_commission_rate', 'label' => 'ค่าคอมพนักงาน (%)', 'type' => 'pct'],
        ['key' => 'duration_minutes', 'label' => 'เวลา (นาที)', 'type' => 'number'],
        ['key' => 'follow_up_days', 'label' => 'นัด Follow-up (วัน)', 'type' => 'number'],
        ['key' => 'is_package', 'label' => 'เป็นแพ็กเกจ', 'type' => 'boolean'],
        ['key' => 'package_sessions', 'label' => 'จำนวนครั้ง (Package)', 'type' => 'number'],
        ['key' => 'package_validity_days', 'label' => 'อายุ Package (วัน)', 'type' => 'number'],
        ['key' => 'is_active', 'label' => 'Active', 'type' => 'boolean', 'defaultValue' => true],
    ],
    'columns' => [
        ['code', 'รหัส'], ['name', 'ชื่อ'], ['category', 'หมวด'],
        ['price', 'ราคา', 'currency'], ['doctor_fee_rate', 'ค่ามือ (%)', 'pct'],
        ['follow_up_days', 'F/U (วัน)'], ['is_package', 'Pkg', 'boolean'], ['is_active', 'Active', 'boolean'],
    ],
]]))->name('admin.procedures');

Route::get('/admin/products', fn () => view('app.settings-crud', ['config' => [
    'title' => '📦 สินค้า', 'endpoint' => '/admin/products',
    'fields' => [
        ['key' => 'sku', 'label' => 'SKU', 'type' => 'text', 'required' => true],
        ['key' => 'name', 'label' => 'ชื่อสินค้า', 'type' => 'text', 'required' => true],
        ['key' => 'unit', 'label' => 'หน่วย', 'type' => 'text'],
        ['key' => 'selling_price', 'label' => 'ราคาขาย', 'type' => 'currency', 'required' => true],
        ['key' => 'cost_price', 'label' => 'ต้นทุน', 'type' => 'currency'],
        ['key' => 'min_stock', 'label' => 'Stock ต่ำสุด', 'type' => 'number'],
        ['key' => 'max_stock', 'label' => 'Stock สูงสุด', 'type' => 'number'],
        ['key' => 'is_active', 'label' => 'Active', 'type' => 'boolean', 'defaultValue' => true],
    ],
    'columns' => [
        ['sku', 'SKU'], ['name', 'ชื่อ'], ['unit', 'หน่วย'],
        ['selling_price', 'ราคาขาย', 'currency'], ['cost_price', 'ต้นทุน', 'currency'],
        ['min_stock', 'Min'], ['is_active', 'Active', 'boolean'],
    ],
]]))->name('admin.products');

Route::get('/admin/product-categories', fn () => view('app.settings-crud', ['config' => [
    'title' => '📂 หมวดสินค้า', 'endpoint' => '/admin/product-categories',
    'fields' => [
        ['key' => 'name', 'label' => 'ชื่อหมวด', 'type' => 'text', 'required' => true],
        ['key' => 'commission_rate', 'label' => 'ค่าคอม (%)', 'type' => 'pct'],
        ['key' => 'color', 'label' => 'สี', 'type' => 'color', 'defaultValue' => '#0891b2'],
        ['key' => 'icon', 'label' => 'Icon (emoji)', 'type' => 'text'],
        ['key' => 'position', 'label' => 'ลำดับ', 'type' => 'number'],
        ['key' => 'is_active', 'label' => 'Active', 'type' => 'boolean', 'defaultValue' => true],
    ],
    'columns' => [['icon', 'Icon'], ['name', 'ชื่อ'], ['commission_rate', 'ค่าคอม (%)', 'pct'], ['color', 'สี', 'color'], ['is_active', 'Active', 'boolean']],
]]))->name('admin.product-categories');

// HR UI
Route::view('/admin/staff', 'app.staff-list')->name('admin.staff');
Route::view('/admin/staff/{uuid}', 'app.staff-detail')->name('admin.staff.detail')->where('uuid', '[0-9a-f-]{36}');
Route::view('/admin/payroll', 'app.payroll-list')->name('admin.payroll');
Route::view('/admin/payroll/{year}/{month}', 'app.payroll-detail')->name('admin.payroll.detail')->where(['year' => '[0-9]{4}', 'month' => '[0-9]{1,2}']);
Route::view('/time-clock', 'app.time-clock-kiosk')->name('time-clock');

// Marketing UI
Route::view('/marketing/coupons', 'app.marketing-coupons')->name('marketing.coupons');
Route::view('/marketing/promotions', 'app.marketing-promotions')->name('marketing.promotions');
Route::view('/marketing/influencers', 'app.marketing-influencers')->name('marketing.influencers');
Route::view('/marketing/reviews', 'app.marketing-reviews')->name('marketing.reviews');
Route::view('/admin/line-rich-menu', 'app.line-rich-menu')->name('admin.line-rich-menu');

// Public landing routes
Route::get('/r/{shortcode}', [PublicMarketingController::class, 'utmLand'])->name('public.utm');
Route::get('/reviews/{token}', [PublicMarketingController::class, 'showReviewForm'])->name('public.review');
