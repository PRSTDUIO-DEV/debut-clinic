<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Bank;
use App\Models\BirthdayCampaign;
use App\Models\Branch;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastMessage;
use App\Models\BroadcastSegment;
use App\Models\BroadcastTemplate;
use App\Models\CommissionRate;
use App\Models\CommissionTransaction;
use App\Models\CompensationRule;
use App\Models\ConsentTemplate;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Course;
use App\Models\CustomerGroup;
use App\Models\Disbursement;
use App\Models\EmployeeProfile;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FollowUp;
use App\Models\FollowUpRule;
use App\Models\GoodsReceiving;
use App\Models\Influencer;
use App\Models\InfluencerCampaign;
use App\Models\InfluencerReferral;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\LabOrder;
use App\Models\LabResultValue;
use App\Models\LabTest;
use App\Models\LineRichMenu;
use App\Models\MemberAccount;
use App\Models\MemberTransaction;
use App\Models\MessagingProvider;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\PatientConsent;
use App\Models\PatientPhoto;
use App\Models\Payment;
use App\Models\Procedure;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Models\PurchaseRequest;
use App\Models\QcChecklist;
use App\Models\QcChecklistItem;
use App\Models\Review;
use App\Models\Room;
use App\Models\StockLevel;
use App\Models\StockRequisition;
use App\Models\Supplier;
use App\Models\TimeClock;
use App\Models\User;
use App\Models\Visit;
use App\Models\Warehouse;
use App\Services\Accounting\AccountingPoster;
use App\Services\Accounting\DisbursementService;
use App\Services\Accounting\ProcurementService;
use App\Services\Accounting\TaxInvoiceService;
use App\Services\BroadcastService;
use App\Services\ClosingService;
use App\Services\Hr\PayrollService;
use App\Services\Hr\TimeClockService;
use App\Services\LabOrderNumberGenerator;
use App\Services\Marketing\InfluencerService;
use App\Services\Marketing\ReviewService;
use App\Services\NotificationService;
use App\Services\Qc\QcService;
use App\Services\SegmentService;
use App\Services\StockService;
use App\Services\UrgentFollowUpScanner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::query()->first();
        if (! $branch) {
            return;
        }

        $doctor = User::query()->where('email', 'doctor@debut-clinic.local')->first();
        $cashier = User::query()->where('email', 'super@debut-clinic.local')->first();
        $rooms = Room::query()->where('branch_id', $branch->id)->get();
        $banks = Bank::query()->where('branch_id', $branch->id)->get();
        $customerGroups = CustomerGroup::query()->where('branch_id', $branch->id)->get()->keyBy('name');
        $procedures = Procedure::query()->where('branch_id', $branch->id)->get()->keyBy('code');
        $procBotox = $procedures['BTX-100'] ?? $procedures->first();
        $procFiller = $procedures['FILLER-1'] ?? $procedures->first();
        $procConsult = $procedures['CONSULT'] ?? $procedures->first();

        // Commission rate examples (one per type at "all" applicable so calculator always finds something)
        if ($doctor) {
            CommissionRate::firstOrCreate(
                ['branch_id' => $branch->id, 'type' => 'doctor_fee', 'applicable_type' => 'all', 'applicable_id' => null, 'user_id' => $doctor->id],
                ['rate' => 35, 'is_active' => true],
            );
        }
        CommissionRate::firstOrCreate(
            ['branch_id' => $branch->id, 'type' => 'staff_commission', 'applicable_type' => 'all', 'applicable_id' => null, 'user_id' => null],
            ['rate' => 5, 'is_active' => true],
        );

        $samples = [
            ['hn' => 'DC01-260101-0101', 'first' => 'สมชาย',  'last' => 'ใจดี',     'gender' => 'male',   'phone' => '081-100-0001', 'line' => 'somchai_jaidee',  'email' => 'somchai@example.com',  'group' => 'VIP'],
            ['hn' => 'DC01-260101-0102', 'first' => 'สมหญิง', 'last' => 'มีสุข',     'gender' => 'female', 'phone' => '081-100-0002', 'line' => 'somying_meesuk',  'email' => 'somying@example.com',  'group' => 'สมาชิกเงินฝาก'],
            ['hn' => 'DC01-260101-0103', 'first' => 'พิรญาณ์', 'last' => 'รักษ์ดี',   'gender' => 'female', 'phone' => '081-100-0003', 'line' => null,              'email' => 'piranya@example.com',  'group' => 'ลูกค้าทั่วไป'],
            ['hn' => 'DC01-260101-0104', 'first' => 'อรพิน',  'last' => 'รัตนกุล',   'gender' => 'female', 'phone' => '081-100-0004', 'line' => 'orphin_rat',      'email' => null,                    'group' => 'ลูกค้าทั่วไป'],
            ['hn' => 'DC01-260101-0105', 'first' => 'นพดล',  'last' => 'รวยวงศ์',   'gender' => 'male',   'phone' => '081-100-0005', 'line' => 'noppadon_vip',    'email' => 'noppadon@example.com', 'group' => 'VIP', 'dormant' => true, 'line_user_id' => 'U12345abcdef67890noppadon'],
        ];

        $patients = [];
        foreach ($samples as $i => $s) {
            $cgId = $customerGroups[$s['group']]->id ?? null;
            $p = Patient::firstOrCreate(
                ['hn' => $s['hn'], 'branch_id' => $branch->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'first_name' => $s['first'],
                    'last_name' => $s['last'],
                    'gender' => $s['gender'],
                    'phone' => $s['phone'],
                    'line_id' => $s['line'] ?? null,
                    'line_user_id' => $s['line_user_id'] ?? null,
                    'line_linked_at' => isset($s['line_user_id']) ? Carbon::today()->subDays(30) : null,
                    'email' => $s['email'] ?? null,
                    'date_of_birth' => Carbon::create(1985 + $i, 5 + $i, 10 + $i)->toDateString(),
                    'address' => 'ที่อยู่ตัวอย่าง '.($i + 1),
                    'allergies' => $i === 0 ? 'แพ้ Penicillin' : null,
                    'underlying_diseases' => $i === 1 ? 'ความดันโลหิตสูง' : null,
                    'source' => 'walk_in',
                    'customer_group_id' => $cgId,
                    // VIP "dormant": last visit 60 days ago to satisfy "ไม่มา ≥ 30 วัน" segment
                    'last_visit_at' => ! empty($s['dormant']) ? Carbon::today()->subDays(60) : null,
                    'visit_count' => ! empty($s['dormant']) ? 3 : 0,
                    'total_spent' => ! empty($s['dormant']) ? 75000 : 0,
                ],
            );
            $patients[] = $p;
        }

        // Follow-ups (4 priority levels)
        $followRows = [
            ['days' => -10, 'priority' => 'critical', 'patient' => 0, 'notes' => '[critical] รายงานผลข้างเคียง โทรกลับด่วน', 'attempts' => 2],
            ['days' => -4,  'priority' => 'high',     'patient' => 1, 'notes' => 'ยังไม่ตอบกลับ ลองโทรอีกครั้ง', 'attempts' => 1],
            ['days' => 0,   'priority' => 'normal',   'patient' => 2, 'notes' => null, 'attempts' => 0],
            ['days' => 7,   'priority' => 'normal',   'patient' => 3, 'notes' => 'นัดประเมินผล 1 สัปดาห์', 'attempts' => 0],
        ];
        foreach ($followRows as $row) {
            FollowUp::firstOrCreate(
                ['branch_id' => $branch->id, 'patient_id' => $patients[$row['patient']]->id, 'follow_up_date' => Carbon::today()->addDays($row['days'])->toDateString()],
                [
                    'doctor_id' => $doctor?->id,
                    'procedure_id' => $procBotox?->id,
                    'priority' => $row['priority'],
                    'status' => 'pending',
                    'contact_attempts' => $row['attempts'],
                    'notes' => $row['notes'],
                ],
            );
        }

        // ─── Past completed visits + invoices for OPD Card history ───
        // Patient 0 (สมชาย): 2 completed visits last month
        $this->makeCompletedVisit(
            branch: $branch, patient: $patients[0], doctor: $doctor, cashier: $cashier,
            room: $rooms->first(), procedure: $procBotox, daysAgo: 30, paymentMethod: 'credit_card', bank: $banks->first(),
        );
        $this->makeCompletedVisit(
            branch: $branch, patient: $patients[0], doctor: $doctor, cashier: $cashier,
            room: $rooms->first(), procedure: $procFiller, daysAgo: 14, paymentMethod: 'cash',
        );
        // Recent visits (yesterday + today) for daily P/L + closing demo
        $this->makeCompletedVisit(
            branch: $branch, patient: $patients[2], doctor: $doctor, cashier: $cashier,
            room: $rooms->first(), procedure: $procBotox, daysAgo: 1, paymentMethod: 'cash',
        );
        $this->makeCompletedVisit(
            branch: $branch, patient: $patients[3], doctor: $doctor, cashier: $cashier,
            room: $rooms->first(), procedure: $procFiller, daysAgo: 1, paymentMethod: 'credit_card', bank: $banks->first(),
        );
        $this->makeCompletedVisit(
            branch: $branch, patient: $patients[2], doctor: $doctor, cashier: $cashier,
            room: $rooms->first(), procedure: $procConsult, daysAgo: 0, paymentMethod: 'cash',
        );

        // Patient 1 (สมหญิง): 1 completed visit + Member account + 1 active course
        $this->makeCompletedVisit(
            branch: $branch, patient: $patients[1], doctor: $doctor, cashier: $cashier,
            room: $rooms->first(), procedure: $procBotox, daysAgo: 7, paymentMethod: 'transfer', bank: $banks->skip(1)->first(),
        );

        $member = MemberAccount::firstOrCreate(
            ['branch_id' => $branch->id, 'patient_id' => $patients[1]->id],
            [
                'package_name' => 'Standard 50,000',
                'total_deposit' => 50000,
                'total_used' => 12000,
                'balance' => 38000,
                'expires_at' => Carbon::today()->addYear()->toDateString(),
                'status' => 'active',
                'last_topup_at' => Carbon::today()->subDays(60),
                'last_used_at' => Carbon::today()->subDays(7),
                'lifetime_topups' => 1,
            ],
        );
        if ($member->wasRecentlyCreated) {
            MemberTransaction::create([
                'member_account_id' => $member->id,
                'type' => 'deposit',
                'amount' => 50000,
                'balance_before' => 0,
                'balance_after' => 50000,
                'notes' => 'Initial deposit',
                'created_by' => $cashier?->id,
                'created_at' => Carbon::today()->subDays(60),
            ]);
            MemberTransaction::create([
                'member_account_id' => $member->id,
                'type' => 'usage',
                'amount' => 12000,
                'balance_before' => 50000,
                'balance_after' => 38000,
                'notes' => 'Used for invoice (demo)',
                'created_by' => $cashier?->id,
                'created_at' => Carbon::today()->subDays(7),
            ]);
        }

        // Second wallet (smaller balance)
        $member2 = MemberAccount::firstOrCreate(
            ['branch_id' => $branch->id, 'patient_id' => $patients[3]->id],
            [
                'package_name' => 'VIP 20,000',
                'total_deposit' => 20000,
                'total_used' => 5000,
                'balance' => 15000,
                'expires_at' => Carbon::today()->addMonths(8)->toDateString(),
                'status' => 'active',
                'last_topup_at' => Carbon::today()->subDays(20),
                'last_used_at' => Carbon::today()->subDays(3),
                'lifetime_topups' => 2,
            ],
        );
        if ($member2->wasRecentlyCreated) {
            MemberTransaction::create([
                'member_account_id' => $member2->id, 'type' => 'deposit',
                'amount' => 10000, 'balance_before' => 0, 'balance_after' => 10000,
                'notes' => 'Initial deposit', 'created_by' => $cashier?->id,
                'created_at' => Carbon::today()->subDays(45),
            ]);
            MemberTransaction::create([
                'member_account_id' => $member2->id, 'type' => 'deposit',
                'amount' => 10000, 'balance_before' => 10000, 'balance_after' => 20000,
                'notes' => 'Top-up #2', 'created_by' => $cashier?->id,
                'created_at' => Carbon::today()->subDays(20),
            ]);
            MemberTransaction::create([
                'member_account_id' => $member2->id, 'type' => 'usage',
                'amount' => 5000, 'balance_before' => 20000, 'balance_after' => 15000,
                'notes' => 'Used for invoice (demo)', 'created_by' => $cashier?->id,
                'created_at' => Carbon::today()->subDays(3),
            ]);
        }

        Course::firstOrCreate(
            ['branch_id' => $branch->id, 'patient_id' => $patients[1]->id, 'name' => 'Botox 5 ครั้ง'],
            [
                'total_sessions' => 5,
                'used_sessions' => 2,
                'remaining_sessions' => 3,
                'expires_at' => Carbon::today()->addMonths(6)->toDateString(),
                'status' => 'active',
            ],
        );

        // IPL Package course (active, not yet used)
        Course::firstOrCreate(
            ['branch_id' => $branch->id, 'patient_id' => $patients[3]->id, 'name' => 'IPL Package 6 ครั้ง'],
            [
                'total_sessions' => 6,
                'used_sessions' => 0,
                'remaining_sessions' => 6,
                'expires_at' => Carbon::today()->addYear()->toDateString(),
                'status' => 'active',
            ],
        );

        // HIFU Package course (1 used, 2 left)
        Course::firstOrCreate(
            ['branch_id' => $branch->id, 'patient_id' => $patients[2]->id, 'name' => 'HIFU Package 3 ครั้ง'],
            [
                'total_sessions' => 3,
                'used_sessions' => 1,
                'remaining_sessions' => 2,
                'expires_at' => Carbon::today()->addMonths(6)->toDateString(),
                'status' => 'active',
            ],
        );

        // ─── Patient consents (for OPD Card consent tab) ───
        foreach ([0, 1] as $i) {
            PatientConsent::firstOrCreate(
                ['branch_id' => $branch->id, 'patient_id' => $patients[$i]->id, 'name' => 'แบบยินยอมรักษา (PDPA)'],
                [
                    'status' => 'signed',
                    'signed_at' => Carbon::today()->subDays(30 + $i * 3),
                    'expires_at' => Carbon::today()->addYear()->toDateString(),
                    'uploaded_by' => $cashier?->id,
                    'notes' => 'เอกสารตัวอย่าง',
                ],
            );
        }
        PatientConsent::firstOrCreate(
            ['branch_id' => $branch->id, 'patient_id' => $patients[2]->id, 'name' => 'แบบยินยอมรักษา (PDPA)'],
            ['status' => 'pending', 'uploaded_by' => $cashier?->id, 'notes' => 'รอเซ็นในวันที่นัด'],
        );

        // ─── Active visit today for POS ───
        // Patient 2 (พิรญาณ์): in_progress visit, draft invoice with one item
        $todayVisit = Visit::query()->where('branch_id', $branch->id)
            ->where('patient_id', $patients[2]->id)
            ->whereDate('visit_date', Carbon::today())
            ->first();
        if (! $todayVisit) {
            $todayVisit = Visit::create([
                'branch_id' => $branch->id,
                'patient_id' => $patients[2]->id,
                'doctor_id' => $doctor?->id,
                'room_id' => $rooms->first()?->id,
                'visit_number' => 'VN-'.Carbon::today()->format('Ymd').'-9001',
                'visit_date' => Carbon::today()->toDateString(),
                'check_in_at' => Carbon::now()->subMinutes(20),
                'status' => 'in_progress',
                'source' => 'walk_in',
                'vital_signs' => ['bp' => '120/80', 'pulse' => 72, 'weight' => 55, 'height' => 165, 'bmi' => 20.2],
                'chief_complaint' => 'ปรึกษาเพิ่ม Filler บริเวณคาง',
            ]);
            $invoice = Invoice::create([
                'branch_id' => $branch->id,
                'visit_id' => $todayVisit->id,
                'patient_id' => $patients[2]->id,
                'invoice_number' => 'INV-'.Carbon::today()->format('Ym').'-9001',
                'invoice_date' => Carbon::today()->toDateString(),
                'status' => 'draft',
            ]);
            if ($procConsult) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => 'procedure',
                    'item_id' => $procConsult->id,
                    'item_name' => $procConsult->name,
                    'quantity' => 1,
                    'unit_price' => $procConsult->price,
                    'discount' => 0,
                    'total' => $procConsult->price,
                    'cost_price' => $procConsult->cost,
                    'doctor_id' => $doctor?->id,
                ]);
                $invoice->subtotal = $procConsult->price;
                $invoice->total_amount = $procConsult->price;
                $invoice->save();
            }
        }

        // ─── Appointments (today + upcoming) ───
        if ($doctor) {
            // Patient 3 (อรพิน): tomorrow 10:00
            Appointment::firstOrCreate(
                [
                    'branch_id' => $branch->id,
                    'patient_id' => $patients[3]->id,
                    'doctor_id' => $doctor->id,
                    'appointment_date' => Carbon::tomorrow()->toDateString(),
                    'start_time' => '10:00:00',
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'room_id' => $rooms->first()?->id,
                    'procedure_id' => $procBotox?->id,
                    'end_time' => '10:30:00',
                    'status' => 'confirmed',
                    'source' => 'manual',
                    'notes' => 'นัดทำ Botox ครั้งแรก',
                    'created_by' => $cashier?->id ?? $doctor->id,
                ],
            );
            // Patient 1 (สมหญิง): today 14:00 (arrived)
            Appointment::firstOrCreate(
                [
                    'branch_id' => $branch->id,
                    'patient_id' => $patients[1]->id,
                    'doctor_id' => $doctor->id,
                    'appointment_date' => Carbon::today()->toDateString(),
                    'start_time' => '14:00:00',
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'room_id' => $rooms->first()?->id,
                    'procedure_id' => $procFiller?->id,
                    'end_time' => '14:45:00',
                    'status' => 'arrived',
                    'source' => 'manual',
                    'created_by' => $cashier?->id ?? $doctor->id,
                ],
            );
            // Patient 0 (สมชาย): in 3 days 11:00 (pending)
            Appointment::firstOrCreate(
                [
                    'branch_id' => $branch->id,
                    'patient_id' => $patients[0]->id,
                    'doctor_id' => $doctor->id,
                    'appointment_date' => Carbon::today()->addDays(3)->toDateString(),
                    'start_time' => '11:00:00',
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'room_id' => $rooms->skip(1)->first()?->id,
                    'procedure_id' => $procConsult?->id,
                    'end_time' => '11:20:00',
                    'status' => 'pending',
                    'source' => 'manual',
                    'created_by' => $cashier?->id ?? $doctor->id,
                ],
            );
        }

        $this->seedInventory($branch, $cashier, $doctor);
        $this->seedMediaAndConsents($branch, $patients, $cashier);
        $this->seedLabs($branch, $patients, $doctor, $cashier);
        $this->seedCrm($branch, $cashier);
        $this->seedExpensesAndClosing($branch, $cashier);
        $this->seedNotifications($branch, $patients, $cashier);
        $this->seedMessagingProviders($branch);
        $this->seedAccounting($branch, $cashier);
        $this->seedMarketing($branch, $patients);
        $this->seedHr($branch);
        $this->seedQc($branch);
    }

    private function seedQc(Branch $branch): void
    {
        if (QcChecklist::where('branch_id', $branch->id)->exists()) {
            return;
        }

        $cl1 = QcChecklist::create([
            'branch_id' => $branch->id,
            'name' => 'Daily Cleaning + Equipment Check',
            'description' => 'รายการตรวจประจำวัน',
            'frequency' => 'daily',
            'applicable_role' => 'nurse',
        ]);
        foreach ([
            'ทำความสะอาดห้องตรวจ + เครื่องมือ',
            'ตรวจสอบยาในตู้ + วันหมดอายุ',
            'ทดสอบการทำงานของเครื่องมือ',
            'เช็คอุณหภูมิตู้เย็นเก็บยา',
            'ทำความสะอาดเตียง + เปลี่ยนผ้าปู',
            'ตรวจสอบสต็อกของใช้สิ้นเปลือง',
        ] as $i => $title) {
            QcChecklistItem::create([
                'checklist_id' => $cl1->id, 'position' => $i, 'title' => $title,
                'requires_note' => $i === 1, 'requires_photo' => false,
            ]);
        }

        $cl2 = QcChecklist::create([
            'branch_id' => $branch->id,
            'name' => 'Weekly Equipment Calibration',
            'description' => 'ปรับเทียบเครื่องมือรายสัปดาห์',
            'frequency' => 'weekly',
            'applicable_role' => 'nurse',
        ]);
        foreach ([
            'ปรับเทียบเครื่องวัด BP',
            'ปรับเทียบเครื่องชั่งน้ำหนัก',
            'ตรวจสอบเครื่องเลเซอร์',
            'ทดสอบ AED battery',
        ] as $i => $title) {
            QcChecklistItem::create([
                'checklist_id' => $cl2->id, 'position' => $i, 'title' => $title,
                'requires_photo' => true,
            ]);
        }

        // Performer
        $performer = User::query()
            ->whereHas('roles', fn ($q) => $q->where('roles.name', 'nurse'))
            ->where('branch_id', $branch->id)
            ->first()
            ?? User::query()->where('branch_id', $branch->id)->first();

        if (! $performer) {
            return;
        }

        // 5 daily runs (last 5 days), all completed with mostly pass
        $today = Carbon::today();
        $svc = app(QcService::class);
        for ($d = 5; $d >= 1; $d--) {
            $run = $svc->startRun($cl1, $performer, $today->copy()->subDays($d)->toDateString());
            foreach ($cl1->items as $idx => $item) {
                // 90% pass rate, 7% fail, 3% n/a
                $rand = rand(1, 100);
                $status = $rand <= 90 ? 'pass' : ($rand <= 97 ? 'fail' : 'na');
                $note = $status === 'fail' ? 'พบปัญหา — แจ้งช่างซ่อมแล้ว' : null;
                $svc->recordItem($run, $item, $status, $note);
            }
            $svc->completeRun($run);
        }

        // Today's run still in_progress
        $svc->startRun($cl1, $performer, $today->toDateString());
    }

    private function seedHr(Branch $branch): void
    {
        if (TimeClock::where('branch_id', $branch->id)->exists()) {
            return;
        }

        $tc = app(TimeClockService::class);

        // Pull all users in branch
        $users = User::query()
            ->whereHas('branches', fn ($q) => $q->where('branches.id', $branch->id))
            ->get();
        if ($users->isEmpty()) {
            return;
        }

        // Set PIN + employee profile + compensation rule for each user
        foreach ($users as $idx => $u) {
            if (! $u->pin_hash) {
                $u->pin_hash = Hash::make(str_pad((string) (1234 + $idx), 4, '0', STR_PAD_LEFT));
                $u->save();
            }

            EmployeeProfile::updateOrCreate(['user_id' => $u->id], [
                'employee_no' => $u->employee_code ?? 'EMP-'.str_pad((string) $u->id, 4, '0', STR_PAD_LEFT),
                'position' => $u->position ?? ($u->is_doctor ? 'แพทย์' : 'พนักงาน'),
                'department' => $u->is_doctor ? 'Medical' : 'Operations',
                'hire_date' => now()->subYears(rand(1, 4))->subMonths(rand(0, 11))->toDateString(),
                'bank_name' => collect(['KBANK', 'SCB', 'KTB', 'BBL'])->random(),
                'bank_account' => sprintf('%03d-%07d', rand(1, 999), rand(1000000, 9999999)),
                'national_id' => '1'.str_pad((string) rand(0, 999999999999), 12, '0', STR_PAD_LEFT),
                'emergency_contact' => 'ผู้ติดต่อฉุกเฉิน '.($idx + 1),
                'emergency_phone' => '08'.rand(10000000, 99999999),
            ]);

            $type = $u->is_doctor ? 'monthly' : 'monthly';
            $base = $u->is_doctor ? rand(40000, 60000) : rand(15000, 25000);
            CompensationRule::firstOrCreate(
                ['branch_id' => $branch->id, 'user_id' => $u->id, 'type' => $type, 'is_active' => true],
                ['base_amount' => $base, 'valid_from' => now()->subYear()->toDateString()],
            );
        }

        // Generate 30 days of clock-in/out for each user (skip weekends randomly)
        $today = Carbon::today();
        foreach ($users as $u) {
            for ($d = 30; $d >= 1; $d--) {
                $date = $today->copy()->subDays($d);
                if ($date->isWeekend() && rand(1, 10) > 3) {
                    continue;
                }
                if (rand(1, 20) === 1) {
                    continue; // ~5% absent
                }
                $inHour = 9;
                $inMin = rand(-15, 35); // some come early, some late
                $outHour = 18;
                $outMin = rand(-10, 90); // sometimes OT
                $clockIn = $date->copy()->setTime($inHour, max(0, $inMin), 0)
                    ->addMinutes($inMin < 0 ? $inMin : 0);
                $clockOut = $date->copy()->setTime($outHour, 0, 0)->addMinutes($outMin);

                $totalMin = abs((int) round($clockIn->diffInMinutes($clockOut, false)));
                $shiftStart = $clockIn->copy()->setTime(9, 0, 0);
                $late = $clockIn->gt($shiftStart->copy()->addMinutes(15)) ? abs((int) round($clockIn->diffInMinutes($shiftStart, false))) : 0;
                $shiftEnd = $clockIn->copy()->setTime(18, 0, 0);
                $ot = $clockOut->gt($shiftEnd->copy()->addMinutes(30)) ? abs((int) round($shiftEnd->diffInMinutes($clockOut, false))) : 0;

                TimeClock::create([
                    'user_id' => $u->id, 'branch_id' => $branch->id,
                    'clock_in' => $clockIn, 'clock_out' => $clockOut,
                    'total_minutes' => $totalMin,
                    'late_minutes' => $late,
                    'overtime_minutes' => $ot,
                    'source' => 'kiosk',
                ]);
            }
        }

        // Create payroll for previous month (paid) + current month (draft)
        $svc = app(PayrollService::class);
        $admin = $users->first();

        try {
            $prev = $today->copy()->subMonth();
            $payroll = $svc->generatePreview($branch->id, (int) $prev->format('Y'), (int) $prev->format('m'));
            $svc->finalize($payroll, $admin);
            $svc->markPaid($payroll->fresh(), $admin, 'transfer', 'BANK-DEMO-001');
        } catch (\Throwable $e) {
            Log::warning('Demo payroll prev failed: '.$e->getMessage());
        }

        try {
            $svc->generatePreview($branch->id, (int) $today->format('Y'), (int) $today->format('m'));
        } catch (\Throwable $e) {
            Log::warning('Demo payroll current failed: '.$e->getMessage());
        }
    }

    private function seedMarketing(Branch $branch, array $patients): void
    {
        if (Coupon::where('branch_id', $branch->id)->exists()) {
            return;
        }

        // 3 coupons (1 active, 1 used, 1 future)
        $coupon1 = Coupon::create([
            'branch_id' => $branch->id, 'code' => 'WELCOME10', 'name' => 'ส่วนลดต้อนรับ',
            'type' => 'percent', 'value' => 10, 'min_amount' => 1000, 'max_discount' => 500,
            'max_uses' => 100, 'max_per_customer' => 1,
            'valid_from' => now()->subWeek()->toDateString(),
            'valid_to' => now()->addMonths(2)->toDateString(),
            'is_active' => true,
        ]);
        Coupon::create([
            'branch_id' => $branch->id, 'code' => 'BTX-SUMMER', 'name' => 'Botox Summer',
            'type' => 'fixed', 'value' => 1500, 'min_amount' => 8000,
            'max_uses' => 50, 'max_per_customer' => 1,
            'valid_from' => now()->subWeek()->toDateString(),
            'valid_to' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ]);
        Coupon::create([
            'branch_id' => $branch->id, 'code' => 'COMING-SOON', 'name' => 'แคมเปญถัดไป',
            'type' => 'percent', 'value' => 15, 'max_per_customer' => 1,
            'valid_from' => now()->addMonth()->toDateString(),
            'valid_to' => now()->addMonths(2)->toDateString(),
            'is_active' => true,
        ]);

        // Mark first coupon as used by 2 patients
        if (count($patients) >= 2) {
            CouponRedemption::create([
                'coupon_id' => $coupon1->id, 'patient_id' => $patients[0]->id,
                'amount_discounted' => 500, 'redeemed_at' => now()->subDays(3),
            ]);
            CouponRedemption::create([
                'coupon_id' => $coupon1->id, 'patient_id' => $patients[1]->id,
                'amount_discounted' => 350, 'redeemed_at' => now()->subDays(1),
            ]);
            $coupon1->increment('used_count', 2);
        }

        // 2 promotions
        Promotion::create([
            'branch_id' => $branch->id, 'name' => 'ลด 20% เมื่อซื้อครบ 5,000',
            'type' => 'percent',
            'rules' => ['value' => 20, 'min_amount' => 5000, 'max_discount' => 2000],
            'valid_from' => now()->subDay()->toDateString(),
            'valid_to' => now()->addMonth()->toDateString(),
            'is_active' => true, 'priority' => 10,
        ]);
        Promotion::create([
            'branch_id' => $branch->id, 'name' => 'Botox 1 แถม 1',
            'type' => 'buy_x_get_y',
            'rules' => ['buy_qty' => 1, 'get_qty' => 1],
            'valid_from' => now()->subDay()->toDateString(),
            'valid_to' => now()->addWeek()->toDateString(),
            'is_active' => true, 'priority' => 5,
        ]);

        // 2 influencers + campaigns + referrals
        $inf1 = Influencer::create([
            'branch_id' => $branch->id, 'name' => 'น้องมิ้น (IG)',
            'channel' => 'instagram', 'handle' => '@minmin', 'commission_rate' => 5,
            'is_active' => true,
        ]);
        $inf2 = Influencer::create([
            'branch_id' => $branch->id, 'name' => 'หมอแพรว (TikTok)',
            'channel' => 'tiktok', 'handle' => '@drprev', 'commission_rate' => 8,
            'is_active' => true,
        ]);

        $svc = app(InfluencerService::class);
        $camp1 = InfluencerCampaign::create([
            'branch_id' => $branch->id, 'influencer_id' => $inf1->id,
            'name' => 'Spring Beauty 2026', 'shortcode' => $svc->generateShortcode(),
            'utm_source' => 'instagram', 'utm_medium' => 'social', 'utm_campaign' => 'spring2026',
            'landing_url' => 'https://debut-clinic.com/promo',
            'start_date' => now()->subWeeks(2)->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'total_budget' => 15000, 'status' => 'active',
        ]);
        $camp2 = InfluencerCampaign::create([
            'branch_id' => $branch->id, 'influencer_id' => $inf2->id,
            'name' => 'Filler Awareness', 'shortcode' => $svc->generateShortcode(),
            'utm_source' => 'tiktok', 'utm_medium' => 'video', 'utm_campaign' => 'filler-awareness',
            'landing_url' => 'https://debut-clinic.com/filler',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->subWeek()->toDateString(),
            'total_budget' => 8000, 'status' => 'ended',
        ]);

        // Referrals: some clicks (no patient), some signups
        for ($i = 0; $i < 12; $i++) {
            InfluencerReferral::create([
                'campaign_id' => $camp1->id,
                'patient_id' => null,
                'ip' => '203.0.113.'.rand(1, 255),
                'referred_at' => now()->subDays(rand(1, 14)),
            ]);
        }
        // 4 signups
        foreach (array_slice($patients, 0, 4) as $idx => $p) {
            InfluencerReferral::create([
                'campaign_id' => $camp1->id,
                'patient_id' => $p->id,
                'referred_at' => now()->subDays(10 - $idx),
                'first_visit_at' => now()->subDays(8 - $idx),
                'lifetime_value' => (float) $p->total_spent,
            ]);
        }
        // Camp2 ended with mixed results
        for ($i = 0; $i < 5; $i++) {
            InfluencerReferral::create([
                'campaign_id' => $camp2->id,
                'patient_id' => null,
                'referred_at' => now()->subWeeks(2)->subDays($i),
            ]);
        }
        if (count($patients) >= 6) {
            InfluencerReferral::create([
                'campaign_id' => $camp2->id,
                'patient_id' => $patients[5]->id,
                'referred_at' => now()->subWeeks(3),
                'first_visit_at' => now()->subWeeks(2),
                'lifetime_value' => (float) ($patients[5]->total_spent ?? 5000),
            ]);
        }

        // 5 reviews (mix of ratings + statuses)
        $reviewService = app(ReviewService::class);
        if (! empty($patients)) {
            foreach ([5, 5, 4, 3, 5] as $idx => $rating) {
                $p = $patients[$idx % count($patients)];
                Review::create([
                    'branch_id' => $branch->id, 'patient_id' => $p->id,
                    'rating' => $rating,
                    'title' => match ($rating) {
                        5 => 'ประทับใจมาก',
                        4 => 'ดี',
                        3 => 'ใช้ได้',
                        default => 'พอใช้',
                    },
                    'body' => 'รีวิวจากลูกค้าจริง #'.($idx + 1),
                    'source' => 'line',
                    'status' => $rating >= 4 ? 'published' : 'pending',
                    'public_token' => Str::random(48),
                    'requested_at' => now()->subDays($idx + 1),
                    'submitted_at' => now()->subDays($idx),
                ]);
            }
        }

        // 1 rich menu
        LineRichMenu::create([
            'branch_id' => $branch->id,
            'name' => 'Main Menu',
            'layout' => 'compact_6',
            'buttons' => [
                ['label' => 'จองคิว', 'action' => 'url', 'value' => '/booking'],
                ['label' => 'โปรโมชั่น', 'action' => 'url', 'value' => '/promo'],
                ['label' => 'รีวิว', 'action' => 'message', 'value' => 'review'],
                ['label' => 'แพ็กเกจ', 'action' => 'url', 'value' => '/packages'],
                ['label' => 'ติดต่อ', 'action' => 'message', 'value' => 'contact'],
                ['label' => 'แผนที่', 'action' => 'url', 'value' => '/map'],
            ],
            'is_active' => true,
        ]);
    }

    private function seedAccounting(Branch $branch, ?User $cashier): void
    {
        if (PurchaseRequest::query()->where('branch_id', $branch->id)->exists()) {
            return;
        }

        $procurement = app(ProcurementService::class);
        $disbSvc = app(DisbursementService::class);
        $taxSvc = app(TaxInvoiceService::class);

        // 1 PR fully through PO + receive
        $supplier = Supplier::query()->where('branch_id', $branch->id)->first();
        $warehouse = Warehouse::query()->where('branch_id', $branch->id)->main()->first();
        if ($supplier && $warehouse && $cashier) {
            $pr = PurchaseRequest::create([
                'branch_id' => $branch->id,
                'pr_number' => $procurement->nextPrNumber($branch->id),
                'request_date' => Carbon::today()->subDays(7),
                'requested_by' => $cashier->id,
                'status' => 'draft',
                'estimated_total' => 0,
                'notes' => 'รับเข้ารายเดือน (seed)',
            ]);
            $items = [
                ['description' => 'Botox 100u', 'quantity' => 5, 'estimated_cost' => 8500],
                ['description' => 'Filler 1ml', 'quantity' => 5, 'estimated_cost' => 5200],
            ];
            $total = 0;
            foreach ($items as $i) {
                $pr->items()->create($i);
                $total += $i['quantity'] * $i['estimated_cost'];
            }
            $pr->update(['estimated_total' => $total]);

            $procurement->submitPr($pr->fresh());
            $procurement->approvePr($pr->fresh(), $cashier);
            $po = $procurement->convertToPo($pr->fresh(), $supplier, Carbon::today()->subDays(2)->toDateString(), 7.0);
            $procurement->sendPo($po->fresh(), $cashier);

            // Receive partial (60%) — leaves PO at partial_received for UI demo
            $po->loadMissing('items');
            $rows = [];
            foreach ($po->items as $i) {
                $rows[] = ['po_item_id' => $i->id, 'qty' => (int) ceil($i->quantity * 0.6), 'lot_no' => 'SEED-'.$i->id];
            }

            try {
                $procurement->receivePo($po->fresh(), $rows, $warehouse->id, $cashier);
            } catch (\Throwable) {
                // skip if conflicts
            }

            // 1 PR awaiting approval
            $pendingPr = PurchaseRequest::create([
                'branch_id' => $branch->id,
                'pr_number' => $procurement->nextPrNumber($branch->id),
                'request_date' => Carbon::today(),
                'requested_by' => $cashier->id,
                'status' => 'submitted',
                'submitted_at' => now(),
                'estimated_total' => 0,
                'notes' => 'รอผู้จัดการอนุมัติ',
            ]);
            $pendingPr->items()->create([
                'description' => 'Vit C Serum 30ml',
                'quantity' => 20,
                'estimated_cost' => 580,
            ]);
            $pendingPr->update(['estimated_total' => 20 * 580]);
        }

        // 2 disbursements (1 paid, 1 awaiting payment)
        if ($cashier) {
            $rent = Disbursement::create([
                'branch_id' => $branch->id,
                'disbursement_no' => $disbSvc->nextNumber($branch->id),
                'disbursement_date' => Carbon::today()->subDays(2)->toDateString(),
                'type' => 'rent',
                'amount' => 25000,
                'payment_method' => 'transfer',
                'vendor' => 'อาคาร ABC Plaza',
                'reference' => 'TXN20260424-001',
                'description' => 'ค่าเช่าเดือนเมษายน 2026',
                'requested_by' => $cashier->id,
                'status' => 'draft',
            ]);
            $disbSvc->approve($rent->fresh(), $cashier);
            $disbSvc->pay($rent->fresh(), 'TXN20260424-001', $cashier);

            Disbursement::create([
                'branch_id' => $branch->id,
                'disbursement_no' => $disbSvc->nextNumber($branch->id),
                'disbursement_date' => Carbon::today()->toDateString(),
                'type' => 'utilities',
                'amount' => 4500,
                'payment_method' => 'transfer',
                'vendor' => 'PEA / MEA',
                'description' => 'ค่าน้ำ-ค่าไฟเดือนนี้ (รออนุมัติ)',
                'requested_by' => $cashier->id,
                'status' => 'draft',
            ]);
        }

        // Backfill accounting entries for invoices created via makeCompletedVisit (which bypasses CheckoutService)
        $poster = app(AccountingPoster::class);
        $paidInvoices = Invoice::query()
            ->where('branch_id', $branch->id)
            ->where('status', 'paid')
            ->with(['items', 'payments'])
            ->get();
        foreach ($paidInvoices as $inv) {
            try {
                $poster->postInvoice($inv);
                $poster->postCommissionsForInvoice($inv);
            } catch (\Throwable) {
                // skip
            }
        }

        // Backfill accounting for member transactions seeded
        foreach (MemberTransaction::query()->get() as $txn) {
            try {
                $poster->postMemberTransaction($txn->fresh('memberAccount'));
            } catch (\Throwable) {
            }
        }

        // Backfill accounting for expenses seeded
        foreach (Expense::query()->where('branch_id', $branch->id)->get() as $exp) {
            try {
                $poster->postExpense($exp);
            } catch (\Throwable) {
            }
        }

        // Issue 1-2 tax invoices for paid invoices
        foreach ($paidInvoices->take(2) as $idx => $inv) {
            try {
                $taxSvc->issue(
                    $inv,
                    'บริษัท ลูกค้าตัวอย่าง '.($idx + 1).' จำกัด',
                    str_pad((string) (1234567890123 + $idx), 13, '0', STR_PAD_LEFT),
                    'กรุงเทพมหานคร',
                    7.0,
                    $cashier,
                );
            } catch (\Throwable) {
                // skip if already issued
            }
        }
    }

    private function seedMessagingProviders(Branch $branch): void
    {
        // LINE provider (sandbox creds — replace via UI in production)
        MessagingProvider::firstOrCreate(
            ['branch_id' => $branch->id, 'type' => 'line', 'name' => 'LINE OA (sandbox)'],
            [
                'config' => [
                    'channel_id' => 'sandbox-channel-id',
                    'channel_secret' => 'sandbox-channel-secret',
                    'channel_access_token' => 'sandbox-token-replace-me',
                ],
                'is_active' => true,
                'is_default' => true,
                'status' => 'unknown',
            ],
        );

        // SMS provider (sandbox mode — no real send)
        MessagingProvider::firstOrCreate(
            ['branch_id' => $branch->id, 'type' => 'sms', 'name' => 'SMS Sandbox'],
            [
                'config' => ['mode' => 'sandbox'],
                'is_active' => true,
                'is_default' => true,
                'status' => 'ok',
            ],
        );

        // Email (uses default Laravel Mail config)
        MessagingProvider::firstOrCreate(
            ['branch_id' => $branch->id, 'type' => 'email', 'name' => 'Email Default'],
            [
                'config' => [
                    'from_address' => 'noreply@debut-clinic.local',
                    'from_name' => 'Debut Clinic',
                ],
                'is_active' => true,
                'is_default' => true,
                'status' => 'ok',
            ],
        );
    }

    private function seedNotifications(Branch $branch, array $patients, ?User $cashier): void
    {
        // Birthday campaign default
        BirthdayCampaign::firstOrCreate(
            ['branch_id' => $branch->id, 'name' => 'แคมเปญวันเกิดเริ่มต้น'],
            [
                'description' => 'ส่งทักทาย 30/7/0 + ติดตาม +3 หลังวันเกิด',
                'templates' => [
                    '30' => [
                        'channel' => 'in_app',
                        'title' => 'วันเกิดของ {{first_name}} ใกล้ถึงแล้ว',
                        'body' => 'อีก 30 วันคือวันเกิดของ {{first_name}} {{nickname}} (HN {{hn}}) — เตรียมส่งคำอวยพรล่วงหน้า',
                    ],
                    '7' => [
                        'channel' => 'line',
                        'title' => 'สุขสันต์วันเกิดล่วงหน้าค่ะ คุณ {{nickname}}',
                        'body' => 'Debut Clinic ขอส่งคำอวยพรวันเกิดถึงคุณ {{first_name}} ❤️ พิเศษเฉพาะคุณ ส่วนลด 20% ทุกคอร์สในเดือนเกิด',
                    ],
                    '0' => [
                        'channel' => 'line',
                        'title' => '🎂 สุขสันต์วันเกิดค่ะคุณ {{nickname}}!',
                        'body' => 'Debut Clinic ขอมอบคูปองวันเกิด ฿1,000 เป็นของขวัญพิเศษ ใช้ได้ภายใน 30 วันเท่านั้น',
                    ],
                    '+3' => [
                        'channel' => 'in_app',
                        'title' => 'ติดตามผู้ป่วย {{first_name}} หลังวันเกิด',
                        'body' => 'ผู้ป่วย {{first_name}} (HN {{hn}}) เพิ่งครบรอบวันเกิด 3 วันแล้ว — โทรเชิญเข้ามาใช้คูปองวันเกิด',
                    ],
                ],
                'is_active' => true,
                'created_by' => $cashier?->id,
            ],
        );

        // Follow-up rules (4 default)
        $rules = [
            ['name' => 'เลยกำหนดนัด ≥ 3 วัน', 'priority' => 'high', 'condition_type' => 'overdue_days', 'condition_value' => ['days' => 3]],
            ['name' => 'VIP เลยนัด ≥ 7 วัน', 'priority' => 'critical', 'condition_type' => 'vip_overdue_days', 'condition_value' => ['days' => 7]],
            ['name' => 'คอร์สใกล้หมดอายุ ≤ 14 วัน', 'priority' => 'high', 'condition_type' => 'course_expiring_days', 'condition_value' => ['days' => 14]],
            ['name' => 'ไม่มาคลินิก ≥ 90 วัน (Dormant)', 'priority' => 'low', 'condition_type' => 'dormant_days', 'condition_value' => ['days' => 90]],
        ];
        foreach ($rules as $r) {
            FollowUpRule::firstOrCreate(
                ['branch_id' => $branch->id, 'name' => $r['name']],
                $r + [
                    'notify_branch_admin' => true,
                    'notify_doctor' => $r['priority'] === 'critical',
                    'preferred_channel' => 'in_app',
                    'is_active' => true,
                ],
            );
        }

        // Run scanners once to seed sample notifications
        if (Notification::query()->count() === 0) {
            app(UrgentFollowUpScanner::class)->run($branch->id);

            // Add a few in-app notifications for the cashier as samples
            if ($cashier) {
                $svc = app(NotificationService::class);
                $svc->write(
                    'user', $cashier->id, 'expiry_alert',
                    'ยา Vit C Serum 30ml หมดอายุแล้ว',
                    'Lot VC-25 หมดอายุ '.now()->subDays(3)->toDateString().' • คลังกลาง • คงเหลือ 4',
                    'critical', 'in_app', 'stock_level', null, $branch->id,
                );
                $svc->write(
                    'user', $cashier->id, 'low_stock',
                    'สต็อกต่ำ: Botox 100u',
                    'คงเหลือ 6 unit ในคลังกลาง (reorder point = 8)',
                    'warning', 'in_app', null, null, $branch->id,
                );
                $svc->write(
                    'user', $cashier->id, 'birthday',
                    'วันเกิดผู้ป่วยที่ใกล้ถึง',
                    'มีผู้ป่วยวันเกิดใน 7 วัน 2 ราย — เตรียมส่งคำอวยพร',
                    'info', 'in_app', null, null, $branch->id,
                );
            }
        }
    }

    private function seedExpensesAndClosing(Branch $branch, ?User $cashier): void
    {
        $cats = [
            ['name' => 'ค่าเช่าสถานที่'],
            ['name' => 'ค่าน้ำ-ค่าไฟ'],
            ['name' => 'ค่ายาและวัสดุสิ้นเปลือง'],
            ['name' => 'ค่าทำความสะอาด'],
            ['name' => 'ค่าโฆษณา/การตลาด'],
        ];
        $catModels = [];
        foreach ($cats as $c) {
            $catModels[$c['name']] = ExpenseCategory::firstOrCreate(
                ['branch_id' => $branch->id, 'name' => $c['name']],
                ['is_active' => true],
            );
        }

        if (Expense::query()->where('branch_id', $branch->id)->exists()) {
            return;
        }

        $expenses = [
            [Carbon::today()->subDays(2), 'ค่าน้ำ-ค่าไฟ', 4500, 'transfer', 'PEA/MEA', 'บิลเดือนนี้'],
            [Carbon::today()->subDay(), 'ค่าทำความสะอาด', 1200, 'cash', 'พี่นภา', 'จ่ายรายวัน'],
            [Carbon::today(), 'ค่ายาและวัสดุสิ้นเปลือง', 2800, 'cash', 'ร้านยาประจำ', 'เติมสต็อก gloves + alcohol'],
            [Carbon::today(), 'ค่าโฆษณา/การตลาด', 5000, 'credit_card', 'Facebook Ads', 'แคมเปญ promo เดือนนี้'],
        ];
        foreach ($expenses as [$date, $catName, $amount, $method, $vendor, $desc]) {
            Expense::create([
                'branch_id' => $branch->id,
                'category_id' => $catModels[$catName]->id,
                'expense_date' => $date->toDateString(),
                'amount' => $amount,
                'payment_method' => $method,
                'vendor' => $vendor,
                'description' => $desc,
                'paid_by' => $cashier?->id,
            ]);
        }

        // Auto-prepare + commit closing for yesterday so reports/closing UI has data
        $svc = app(ClosingService::class);
        $yesterday = Carbon::yesterday()->toDateString();
        $closing = $svc->prepare($branch->id, $yesterday);
        if ($closing->status !== 'closed') {
            $svc->commit($closing, (float) $closing->expected_cash, $cashier, 'ปิดยอดอัตโนมัติ (seed)');
        }

        // Prepare today's closing as draft for UI demo
        $svc->prepare($branch->id, Carbon::today()->toDateString());
    }

    private function seedCrm(Branch $branch, ?User $cashier): void
    {
        $vipGroup = CustomerGroup::query()
            ->where('branch_id', $branch->id)
            ->where('name', 'VIP')
            ->first();

        $segVipDormant = BroadcastSegment::firstOrCreate(
            ['branch_id' => $branch->id, 'name' => 'VIP ที่ไม่มา ≥ 30 วัน'],
            [
                'description' => 'ลูกค้า VIP ที่ไม่มาคลินิกเกิน 30 วัน หรือไม่เคยมา',
                'rules' => [
                    'customer_group_ids' => $vipGroup ? [$vipGroup->id] : [],
                    'last_visit_days_min' => 30,
                ],
                'is_active' => true,
                'created_by' => $cashier?->id,
            ],
        );
        $segActiveCourse = BroadcastSegment::firstOrCreate(
            ['branch_id' => $branch->id, 'name' => 'มี Course Active'],
            [
                'description' => 'ลูกค้าที่มี course ที่ยังเหลือ session',
                'rules' => ['has_active_course' => true],
                'is_active' => true,
                'created_by' => $cashier?->id,
            ],
        );
        $segNewbies = BroadcastSegment::firstOrCreate(
            ['branch_id' => $branch->id, 'name' => 'ลูกค้าใหม่ (มาภายใน 30 วัน)'],
            [
                'description' => 'ลูกค้าที่มาคลินิกครั้งล่าสุดในช่วง 30 วัน',
                'rules' => ['last_visit_days_max' => 30],
                'is_active' => true,
                'created_by' => $cashier?->id,
            ],
        );

        // Touch stats so UI shows initial counts
        $segSvc = app(SegmentService::class);
        $segSvc->touchStats($segVipDormant);
        $segSvc->touchStats($segActiveCourse);
        $segSvc->touchStats($segNewbies);

        $tplVip = BroadcastTemplate::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'LINE-WELCOME-BACK'],
            [
                'name' => 'ทักทายลูกค้า VIP กลับมา',
                'channel' => 'line',
                'body' => "สวัสดีคุณ {{first_name}} 💎\n".
                    "เรามีโปรโมชั่นพิเศษสำหรับสมาชิก VIP เดือนนี้\n".
                    "Botox + Filler ลด 20% เมื่อจองภายในวันที่ 30\n".
                    'ยอดสะสม: {{total_spent}} บาท ❤️',
                'is_active' => true,
                'created_by' => $cashier?->id,
            ],
        );
        $tplCoursePromo = BroadcastTemplate::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'SMS-COURSE-PROMO'],
            [
                'name' => 'Promo คอร์ส',
                'channel' => 'sms',
                'body' => 'Debut Clinic: คอร์ส IPL/HIFU โปรโมชั่นซื้อ 1 แถม 1 ถึงสิ้นเดือน นัดได้เลย คุณ {{nickname}} โทร 02-555-1234',
                'is_active' => true,
                'created_by' => $cashier?->id,
            ],
        );
        $tplBirthday = BroadcastTemplate::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'EMAIL-BIRTHDAY'],
            [
                'name' => 'ของขวัญวันเกิด',
                'channel' => 'email',
                'subject' => '🎂 Happy Birthday จาก Debut Clinic',
                'body' => "เรียนคุณ {{first_name}}\n\nDebut Clinic ขอมอบส่วนลด 1,000 บาทเป็นของขวัญวันเกิดให้คุณ\nใช้ได้ภายใน 30 วันที่คลินิกเรา ❤️\n\nDebut Clinic Team",
                'is_active' => true,
                'created_by' => $cashier?->id,
            ],
        );

        if (BroadcastCampaign::query()->where('branch_id', $branch->id)->exists()) {
            return;
        }

        // Completed campaign (sent yesterday) — materialize messages from live segment
        $broadcastSvc = app(BroadcastService::class);
        $resolved = $segSvc->resolve($segVipDormant);
        $sent = BroadcastCampaign::create([
            'branch_id' => $branch->id,
            'segment_id' => $segVipDormant->id,
            'template_id' => $tplVip->id,
            'name' => 'VIP Welcome-Back ตุลาคม',
            'status' => 'completed',
            'started_at' => Carbon::now()->subDay(),
            'completed_at' => Carbon::now()->subDay()->addMinutes(2),
            'created_by' => $cashier?->id,
        ]);

        $sentCount = 0;
        $skippedCount = 0;
        foreach ($resolved as $p) {
            $hasLine = ! empty($p->line_id);
            BroadcastMessage::create([
                'campaign_id' => $sent->id,
                'patient_id' => $p->id,
                'channel' => 'line',
                'recipient_address' => $hasLine ? $p->line_id : null,
                'status' => $hasLine ? 'sent' : 'skipped',
                'payload' => $broadcastSvc->render($tplVip->body, $p),
                'sent_at' => $hasLine ? Carbon::now()->subDay()->addMinute() : null,
                'error' => $hasLine ? null : 'no recipient address for channel line',
            ]);
            $hasLine ? $sentCount++ : $skippedCount++;
        }
        $sent->update([
            'total_recipients' => $resolved->count(),
            'sent_count' => $sentCount,
            'skipped_count' => $skippedCount,
        ]);

        // Scheduled campaign (next week)
        BroadcastCampaign::create([
            'branch_id' => $branch->id,
            'segment_id' => $segActiveCourse->id,
            'template_id' => $tplCoursePromo->id,
            'name' => 'Course Promo สิ้นเดือน',
            'status' => 'scheduled',
            'scheduled_at' => Carbon::now()->addDays(7),
            'created_by' => $cashier?->id,
        ]);
    }

    private function seedLabs(Branch $branch, array $patients, ?User $doctor, ?User $cashier): void
    {
        $catalog = [
            ['CBC', 'Complete Blood Count (WBC)', 'Hematology', '×10^9/L', 4.0, 10.0, null, 250],
            ['HGB', 'Hemoglobin', 'Hematology', 'g/dL', 12.0, 16.0, null, 120],
            ['FBS', 'Fasting Blood Sugar', 'Chemistry', 'mg/dL', 70.0, 110.0, null, 80],
            ['HBA1C', 'HbA1c', 'Chemistry', '%', 4.0, 5.7, null, 350],
            ['CHOL', 'Total Cholesterol', 'Chemistry', 'mg/dL', null, 200.0, '<200', 150],
            ['AST', 'AST (SGOT)', 'Liver', 'U/L', null, 40.0, '<40', 120],
            ['UA', 'Urinalysis', 'Urine', null, null, null, 'Negative for protein/glucose', 100],
        ];
        $tests = [];
        foreach ($catalog as [$code, $name, $cat, $unit, $min, $max, $refText, $price]) {
            $tests[$code] = LabTest::firstOrCreate(
                ['branch_id' => $branch->id, 'code' => $code],
                [
                    'name' => $name, 'category' => $cat, 'unit' => $unit,
                    'ref_min' => $min, 'ref_max' => $max, 'ref_text' => $refText,
                    'price' => $price, 'is_active' => true,
                ],
            );
        }

        if (LabOrder::query()->where('branch_id', $branch->id)->exists()) {
            return;
        }

        // Order 1: completed for patient[0] with one abnormal flag
        $orderNumbers = app(LabOrderNumberGenerator::class);
        $order1 = LabOrder::create([
            'branch_id' => $branch->id, 'patient_id' => $patients[0]->id,
            'order_no' => $orderNumbers->next($branch->id),
            'ordered_at' => Carbon::today()->subDays(5),
            'ordered_by' => $doctor?->id,
            'status' => 'completed',
            'result_date' => Carbon::today()->subDays(4)->toDateString(),
            'notes' => 'Annual checkup',
        ]);
        $samples = [
            ['CBC', 8.2, 'normal'],
            ['HGB', 13.5, 'normal'],
            ['FBS', 142, 'high'],   // diabetes alert
            ['CHOL', 195, 'normal'],
            ['AST', 28, 'normal'],
        ];
        foreach ($samples as [$code, $val, $flag]) {
            $t = $tests[$code];
            $order1->items()->firstOrCreate(['lab_test_id' => $t->id], ['price' => $t->price]);
            LabResultValue::firstOrCreate(
                ['lab_order_id' => $order1->id, 'lab_test_id' => $t->id],
                [
                    'value_numeric' => $val,
                    'abnormal_flag' => $flag,
                    'measured_at' => Carbon::today()->subDays(4),
                    'recorded_by' => $cashier?->id ?? $doctor?->id,
                ],
            );
        }

        // Order 2: sent for patient[1] (waiting for results)
        $order2 = LabOrder::create([
            'branch_id' => $branch->id, 'patient_id' => $patients[1]->id,
            'order_no' => $orderNumbers->next($branch->id),
            'ordered_at' => Carbon::today()->subHours(2),
            'ordered_by' => $doctor?->id,
            'status' => 'sent',
            'notes' => 'Pre-Botox screening',
        ]);
        foreach (['CBC', 'AST'] as $code) {
            $t = $tests[$code];
            $order2->items()->firstOrCreate(['lab_test_id' => $t->id], ['price' => $t->price]);
        }
    }

    private function seedMediaAndConsents(Branch $branch, array $patients, ?User $cashier): void
    {
        // Templates
        $templates = [
            ['code' => 'PDPA', 'title' => 'แบบยินยอม PDPA', 'body_html' => '<p>ข้าพเจ้ายินยอมให้เก็บข้อมูลส่วนบุคคลตาม พ.ร.บ.คุ้มครองข้อมูลส่วนบุคคล...</p>', 'validity_days' => 730],
            ['code' => 'BTX', 'title' => 'แบบยินยอมฉีด Botox', 'body_html' => '<p>รับทราบความเสี่ยง: ฟกช้ำ บวม กล้ามเนื้ออ่อนแรงชั่วคราว...</p>', 'validity_days' => 180],
            ['code' => 'FILLER', 'title' => 'แบบยินยอมฉีด Filler', 'body_html' => '<p>รับทราบความเสี่ยง: ก้อน ปวด หรืออาการแพ้...</p>', 'validity_days' => 180],
        ];
        foreach ($templates as $t) {
            ConsentTemplate::firstOrCreate(
                ['branch_id' => $branch->id, 'code' => $t['code']],
                $t + ['branch_id' => $branch->id, 'require_signature' => true, 'is_active' => true],
            );
        }

        // Sample photos for patient[0] (synthesize tiny PNGs so /inventory and OPD card show non-empty grids)
        if (! PatientPhoto::query()->where('patient_id', $patients[0]->id)->exists()) {
            $disk = Storage::disk('public');
            foreach ([['before', '#fde68a'], ['after', '#a7f3d0']] as $i => [$type, $color]) {
                $img = $this->makeColoredPng(800, 600, $color);
                $thumb = $this->makeColoredPng(256, 192, $color);
                $folder = sprintf('photos/%d/%d/%s', $branch->id, $patients[0]->id, now()->format('Ym'));
                $uuid = (string) Str::uuid();
                $disk->put("$folder/$uuid.png", $img);
                $disk->put("$folder/{$uuid}_thumb.jpg", $thumb);
                PatientPhoto::create([
                    'branch_id' => $branch->id,
                    'patient_id' => $patients[0]->id,
                    'type' => $type,
                    'file_path' => "$folder/$uuid.png",
                    'thumbnail_path' => "$folder/{$uuid}_thumb.jpg",
                    'width' => 800, 'height' => 600,
                    'mime_type' => 'image/png', 'file_size' => strlen($img),
                    'storage_disk' => 'public',
                    'taken_at' => Carbon::today()->subDays(30 - $i * 14),
                    'uploaded_by' => $cashier?->id,
                    'notes' => 'ตัวอย่าง '.$type,
                ]);
            }
        }

        // Link existing demo consents to PDPA template + add signed signature for patient[0]
        $pdpa = ConsentTemplate::query()->where('branch_id', $branch->id)->where('code', 'PDPA')->first();
        if ($pdpa) {
            PatientConsent::query()
                ->where('branch_id', $branch->id)
                ->whereNull('template_id')
                ->update(['template_id' => $pdpa->id]);

            $signed = PatientConsent::query()
                ->where('patient_id', $patients[0]->id)
                ->where('status', 'signed')
                ->first();
            if ($signed && ! $signed->signature_path) {
                $disk = Storage::disk('public');
                $sig = $this->makeColoredPng(440, 160, '#ffffff', text: 'สมชาย ใจดี');
                $rel = sprintf('consents/signatures/%d/%d/%s.png', $branch->id, $patients[0]->id, Str::uuid());
                $disk->put($rel, $sig);
                $signed->update([
                    'signature_path' => $rel,
                    'signed_by_name' => 'สมชาย ใจดี',
                ]);
            }
        }
    }

    private function makeColoredPng(int $w, int $h, string $hex, ?string $text = null): string
    {
        $im = imagecreatetruecolor($w, $h);
        $rgb = sscanf(ltrim($hex, '#'), '%02x%02x%02x');
        $bg = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
        imagefill($im, 0, 0, $bg);
        if ($text) {
            $fg = imagecolorallocate($im, 30, 30, 30);
            imagestring($im, 5, 30, intdiv($h, 2) - 10, $text, $fg);
        }
        ob_start();
        imagepng($im);
        $bin = ob_get_clean();
        imagedestroy($im);

        return $bin;
    }

    private function seedInventory(Branch $branch, ?User $cashier, ?User $doctor): void
    {
        $stocks = app(StockService::class);

        $main = Warehouse::firstOrCreate(
            ['branch_id' => $branch->id, 'name' => 'คลังกลาง (Main)'],
            ['type' => 'main', 'is_active' => true],
        );
        $floor = Warehouse::firstOrCreate(
            ['branch_id' => $branch->id, 'name' => 'หน้าห้อง (Floor)'],
            ['type' => 'floor', 'is_active' => true],
        );

        $catInjectables = ProductCategory::firstOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Injectables'],
            ['commission_rate' => 5, 'is_active' => true],
        );
        $catSkincare = ProductCategory::firstOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Skincare'],
            ['commission_rate' => 10, 'is_active' => true],
        );
        $catConsumable = ProductCategory::firstOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Consumable'],
            ['commission_rate' => 0, 'is_active' => true],
        );

        $catalog = [
            ['INJ-BTX-100', 'Botox 100u', $catInjectables->id, 'vial', 18000, 9000, 5, 20, 8],
            ['INJ-FIL-1ML', 'Filler 1ml', $catInjectables->id, 'syringe', 12000, 5500, 5, 30, 10],
            ['INJ-LIDO', 'Lidocaine 2% 20ml', $catConsumable->id, 'amp', 80, 30, 50, 200, 80],
            ['SKN-VITC', 'Vit C Serum 30ml', $catSkincare->id, 'ขวด', 1800, 600, 10, 50, 20],
            ['SKN-SUN', 'Sunscreen SPF50', $catSkincare->id, 'หลอด', 1200, 400, 10, 50, 20],
            ['CON-SYRINGE', 'Syringe 5ml', $catConsumable->id, 'pcs', 8, 3, 200, 1000, 300],
            ['CON-NEEDLE', 'Needle 30G', $catConsumable->id, 'pcs', 12, 4, 200, 1000, 300],
        ];

        $products = [];
        foreach ($catalog as [$sku, $name, $cat, $unit, $sell, $cost, $min, $max, $reorder]) {
            $products[$sku] = Product::firstOrCreate(
                ['branch_id' => $branch->id, 'sku' => $sku],
                [
                    'category_id' => $cat, 'name' => $name, 'unit' => $unit,
                    'selling_price' => $sell, 'cost_price' => $cost,
                    'min_stock' => $min, 'max_stock' => $max, 'reorder_point' => $reorder,
                    'is_active' => true, 'block_dispensing_when_expired' => true,
                ],
            );
        }

        if (StockLevel::query()->whereIn('product_id', collect($products)->pluck('id'))->exists()) {
            return;
        }

        $supplier = Supplier::firstOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Allergan TH'],
            ['contact_person' => 'คุณนุช', 'phone' => '02-555-1234', 'is_active' => true],
        );

        $today = Carbon::today();
        $lots = [
            'INJ-BTX-100' => [['BTX-2026A', 12, 8500, $today->copy()->addMonths(8)], ['BTX-2026B', 6, 8800, $today->copy()->addDays(45)]],
            'INJ-FIL-1ML' => [['FIL-2026A', 10, 5200, $today->copy()->addMonths(10)], ['FIL-2025X', 3, 5000, $today->copy()->addDays(20)]],
            'INJ-LIDO' => [['LIDO-26', 100, 28, $today->copy()->addMonths(14)]],
            'SKN-VITC' => [['VC-26A', 15, 580, $today->copy()->addMonths(11)], ['VC-25', 4, 600, $today->copy()->subDays(3)]],
            'SKN-SUN' => [['SUN-26', 20, 380, $today->copy()->addMonths(18)]],
            'CON-SYRINGE' => [['SY-2026', 600, 2.8, $today->copy()->addMonths(24)]],
            'CON-NEEDLE' => [['NDL-2026', 500, 3.5, $today->copy()->addMonths(36)]],
        ];

        $gr = GoodsReceiving::create([
            'branch_id' => $branch->id, 'warehouse_id' => $main->id,
            'supplier_id' => $supplier->id,
            'document_no' => 'GR'.$today->format('Ymd').'-DEMO',
            'receive_date' => $today->toDateString(),
            'subtotal' => 0, 'vat_amount' => 0, 'total_amount' => 0,
            'status' => 'completed',
            'received_by' => $cashier?->id,
            'notes' => 'รับเข้าตัวอย่าง (seed)',
        ]);
        $subtotal = 0.0;
        foreach ($lots as $sku => $entries) {
            $product = $products[$sku];
            foreach ($entries as [$lotNo, $qty, $cost, $expiry]) {
                $gr->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                    'total' => $qty * $cost,
                    'lot_no' => $lotNo,
                    'expiry_date' => $expiry->toDateString(),
                ]);
                $subtotal += $qty * $cost;
            }
        }
        $gr->subtotal = $subtotal;
        $gr->total_amount = $subtotal;
        $gr->save();
        $stocks->applyReceiving($gr->fresh('items'), $cashier?->id);

        // Move smaller portion to floor via approved requisition
        $req = StockRequisition::create([
            'branch_id' => $branch->id,
            'document_no' => 'RQ'.$today->format('Ymd').'-DEMO',
            'source_warehouse_id' => $main->id,
            'dest_warehouse_id' => $floor->id,
            'status' => 'pending',
            'requested_by' => $cashier?->id ?? $doctor?->id,
            'requested_at' => $today,
            'notes' => 'ใบเบิกตัวอย่าง (seed)',
        ]);
        $picks = [
            'INJ-BTX-100' => 4,
            'INJ-FIL-1ML' => 4,
            'INJ-LIDO' => 30,
            'SKN-VITC' => 6,
            'SKN-SUN' => 8,
            'CON-SYRINGE' => 200,
            'CON-NEEDLE' => 150,
        ];
        foreach ($picks as $sku => $qty) {
            $req->items()->create([
                'product_id' => $products[$sku]->id,
                'requested_qty' => $qty,
                'approved_qty' => $qty,
            ]);
        }
        $stocks->applyRequisition($req->fresh('items'), $cashier?->id);
        $req->status = 'completed';
        $req->approved_by = $cashier?->id;
        $req->approved_at = $today;
        $req->save();

        // One pending requisition for UI demo
        $pending = StockRequisition::create([
            'branch_id' => $branch->id,
            'document_no' => 'RQ'.$today->format('Ymd').'-PENDING',
            'source_warehouse_id' => $main->id,
            'dest_warehouse_id' => $floor->id,
            'status' => 'pending',
            'requested_by' => $cashier?->id ?? $doctor?->id,
            'requested_at' => $today,
            'notes' => 'ใบเบิกรออนุมัติ (สำหรับทดสอบหน้า UI)',
        ]);
        $pending->items()->create([
            'product_id' => $products['INJ-BTX-100']->id,
            'requested_qty' => 2,
            'approved_qty' => 0,
        ]);
        $pending->items()->create([
            'product_id' => $products['SKN-VITC']->id,
            'requested_qty' => 4,
            'approved_qty' => 0,
        ]);
    }

    /**
     * Helper: create completed visit + paid invoice with one procedure item.
     */
    private function makeCompletedVisit(
        Branch $branch,
        Patient $patient,
        ?User $doctor,
        ?User $cashier,
        ?Room $room,
        ?Procedure $procedure,
        int $daysAgo,
        string $paymentMethod = 'cash',
        ?Bank $bank = null,
    ): void {
        if (! $procedure) {
            return;
        }

        $when = Carbon::today()->subDays($daysAgo);
        // Make unique across patients on the same day by mixing patient_id into the seq
        $seq = 8000 + ($daysAgo * 17) + ($patient->id * 3) + ((int) ($procedure->id ?? 0));
        $vn = 'VN-'.$when->format('Ymd').'-'.str_pad((string) ($seq % 10000), 4, '0', STR_PAD_LEFT);

        $visit = Visit::query()->where('branch_id', $branch->id)
            ->where('patient_id', $patient->id)
            ->where('visit_number', $vn)
            ->first();
        if ($visit) {
            return;
        }

        $visit = Visit::create([
            'branch_id' => $branch->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor?->id,
            'room_id' => $room?->id,
            'visit_number' => $vn,
            'visit_date' => $when->toDateString(),
            'check_in_at' => $when->copy()->setTime(10, 0),
            'check_out_at' => $when->copy()->setTime(11, 0),
            'status' => 'completed',
            'source' => 'walk_in',
            'total_amount' => $procedure->price,
        ]);

        $invoice = Invoice::create([
            'branch_id' => $branch->id,
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'invoice_number' => 'INV-'.$when->format('Ym').'-'.str_pad((string) ($seq % 10000), 4, '0', STR_PAD_LEFT),
            'invoice_date' => $when->toDateString(),
            'subtotal' => $procedure->price,
            'total_amount' => $procedure->price,
            'total_cogs' => $procedure->cost,
            'status' => 'paid',
            'cashier_id' => $cashier?->id,
        ]);

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_type' => 'procedure',
            'item_id' => $procedure->id,
            'item_name' => $procedure->name,
            'quantity' => 1,
            'unit_price' => $procedure->price,
            'discount' => 0,
            'total' => $procedure->price,
            'cost_price' => $procedure->cost,
            'doctor_id' => $doctor?->id,
        ]);

        // Backfill demo commission (35% doctor fee on procedure)
        if ($doctor) {
            $amount = round((float) $procedure->price * 0.35, 2);
            CommissionTransaction::create([
                'branch_id' => $branch->id,
                'invoice_item_id' => $item->id,
                'user_id' => $doctor->id,
                'type' => 'doctor_fee',
                'base_amount' => $procedure->price,
                'rate' => 35,
                'amount' => $amount,
                'commission_date' => $when->toDateString(),
                'is_paid' => false,
                'created_at' => $when,
            ]);
            $invoice->total_commission = $amount;
            $invoice->gross_profit = round((float) $invoice->total_amount - (float) $invoice->total_cogs - $amount, 2);
            $invoice->save();
        }

        Payment::create([
            'branch_id' => $branch->id,
            'invoice_id' => $invoice->id,
            'method' => $paymentMethod,
            'amount' => $procedure->price,
            'bank_id' => $bank?->id,
            'mdr_rate' => $bank?->mdr_rate,
            'mdr_amount' => $bank ? round((float) $procedure->price * (float) $bank->mdr_rate / 100, 2) : null,
            'payment_date' => $when->toDateString(),
        ]);

        // Update patient denormalized stats
        $patient->total_spent = (float) $patient->total_spent + (float) $procedure->price;
        $patient->visit_count = (int) $patient->visit_count + 1;
        $patient->last_visit_at = $when;
        $patient->save();
    }
}
