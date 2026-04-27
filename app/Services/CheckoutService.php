<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\Course;
use App\Models\FollowUp;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MemberAccount;
use App\Models\MemberTransaction;
use App\Models\Payment;
use App\Models\Procedure;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Models\Visit;
use App\Services\Accounting\AccountingPoster;
use App\Services\Cache\CacheService;
use App\Services\Marketing\CouponService;
use App\Services\Marketing\PromotionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private InvoiceNumberGenerator $invoiceNumbers,
        private CommissionService $commissions,
        private StockService $stocks,
        private CourseService $courses,
        private CouponService $coupons,
        private PromotionService $promotions,
    ) {}

    /**
     * Atomically close a visit:
     *  - lock the draft invoice (or create one)
     *  - validate payments total == invoice.total
     *  - apply member_credit deduction (block on insufficient balance)
     *  - persist payments + compute MDR for credit_card
     *  - create follow_ups for any procedure with follow_up_days > 0
     *  - update patient stats (total_spent, visit_count, last_visit_at)
     *  - mark visit completed + invoice paid
     *
     * @param array<int, array{method:string, amount:int|float, bank_id?:int|null, reference_no?:string|null}> $payments
     * @param array{coupon_code?:string|null, promotion_id?:int|null} $marketing
     */
    public function checkout(Visit $visit, array $payments, User $cashier, array $marketing = []): Invoice
    {
        return DB::transaction(function () use ($visit, $payments, $cashier, $marketing) {
            /** @var Visit $visit */
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);

            if ($visit->status === 'completed') {
                throw ValidationException::withMessages([
                    'visit' => 'Visit already completed',
                ]);
            }

            /** @var Invoice|null $invoice */
            $invoice = $visit->invoice()->lockForUpdate()->first();
            if (! $invoice) {
                throw ValidationException::withMessages([
                    'invoice' => 'Visit has no invoice items yet',
                ]);
            }

            $items = $invoice->items()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'invoice' => 'Cannot checkout invoice without items',
                ]);
            }

            // Consume course sessions for items where item_type='course' and course_id set.
            // These items must be zero-charge (already paid when course was bought).
            foreach ($items as $i) {
                if ($i->item_type !== 'course' || empty($i->course_id)) {
                    continue;
                }
                $course = Course::query()
                    ->where('id', $i->course_id)
                    ->where('branch_id', $invoice->branch_id)
                    ->where('patient_id', $invoice->patient_id)
                    ->first();
                if (! $course) {
                    throw ValidationException::withMessages(['course' => "ไม่พบคอร์ส #{$i->course_id} ของผู้ป่วย"]);
                }
                $qty = max(1, (int) $i->quantity);
                for ($n = 0; $n < $qty; $n++) {
                    $this->courses->useSession($course, $visit, $i->doctor_id ? User::query()->find($i->doctor_id) : null, "via invoice item #{$i->id}");
                }
            }

            // Deduct stock for product items (FIFO from floor warehouse).
            // Updates each item.cost_price to weighted average from consumed lots.
            $floor = $this->stocks->defaultFloorWarehouse((int) $invoice->branch_id);
            foreach ($items as $i) {
                if ($i->item_type !== 'product' || empty($i->item_id) || ! $floor) {
                    continue;
                }
                $product = Product::query()->find($i->item_id);
                if (! $product) {
                    continue;
                }
                $consumed = $this->stocks->deduct(
                    productId: (int) $product->id,
                    warehouseId: (int) $floor->id,
                    qty: (int) $i->quantity,
                    branchId: (int) $invoice->branch_id,
                    refType: 'invoice_item',
                    refId: (int) $i->id,
                    userId: (int) $cashier->id,
                    allowExpired: false,
                    movementType: 'pos_deduct',
                );
                $totalCost = 0.0;
                $totalQty = 0;
                foreach ($consumed as $c) {
                    $totalCost += $c['cost_price'] * $c['qty'];
                    $totalQty += $c['qty'];
                }
                $weighted = $totalQty > 0 ? round($totalCost / $totalQty, 2) : (float) $i->cost_price;
                if ($totalQty > 0) {
                    InvoiceItem::query()->where('id', $i->id)->update(['cost_price' => $weighted]);
                    $i->cost_price = $weighted;
                }
            }

            // Recompute totals from items (snapshot is canonical)
            $subtotal = 0;
            $totalCogs = 0;
            foreach ($items as $i) {
                $subtotal = (int) ((($subtotal * 100) + (((float) $i->total) * 100)) + 0.5) / 100;
                $totalCogs = (int) ((($totalCogs * 100) + (((float) $i->cost_price * (int) $i->quantity) * 100)) + 0.5) / 100;
            }

            $invoice->subtotal = $subtotal;

            // Apply marketing: coupon + promotion (additive on top of any manual discount)
            $couponDiscount = 0.0;
            $promoDiscount = 0.0;
            $couponObj = null;
            $promoObj = null;

            if (! empty($marketing['coupon_code'])) {
                $patient = $invoice->patient()->first();
                $result = $this->coupons->validate(
                    $marketing['coupon_code'],
                    $patient,
                    (float) $subtotal,
                    (int) $invoice->branch_id,
                );
                $couponObj = $result['coupon'];
                $couponDiscount = (float) $result['discount'];
                $invoice->coupon_id = $couponObj->id;
            }

            if (! empty($marketing['promotion_id'])) {
                $promoObj = Promotion::query()
                    ->where('branch_id', $invoice->branch_id)
                    ->where('is_active', true)
                    ->find($marketing['promotion_id']);
                if ($promoObj) {
                    $cart = [
                        'subtotal' => (float) $subtotal,
                        'items' => $items->map(fn ($i) => [
                            'item_id' => $i->item_id,
                            'item_type' => $i->item_type,
                            'category_id' => null,
                            'unit_price' => (float) $i->unit_price,
                            'quantity' => (int) $i->quantity,
                            'total' => (float) $i->total,
                        ])->all(),
                    ];
                    $r = $this->promotions->applyToCart($promoObj, $cart);
                    if ($r['applied']) {
                        $promoDiscount = (float) $r['discount'];
                        $invoice->promotion_id = $promoObj->id;
                    }
                }
            }

            $invoice->coupon_discount = $couponDiscount;
            $invoice->promotion_discount = $promoDiscount;
            $totalDiscount = (float) $invoice->discount_amount + $couponDiscount + $promoDiscount;
            $invoice->total_amount = max(0, $subtotal - $totalDiscount + (float) $invoice->vat_amount);
            $invoice->total_cogs = $totalCogs;

            // Validate payments
            $paymentsSum = collect($payments)->sum(fn ($p) => (float) ($p['amount'] ?? 0));
            if (round($paymentsSum, 2) !== round((float) $invoice->total_amount, 2)) {
                throw ValidationException::withMessages([
                    'payments' => 'ยอดชำระไม่ตรงกับยอดบิล',
                ])->status(422);
            }

            // Apply member_credit (deduct balance with FOR UPDATE)
            foreach ($payments as $p) {
                if (($p['method'] ?? null) === 'member_credit') {
                    $member = MemberAccount::query()
                        ->where('patient_id', $invoice->patient_id)
                        ->lockForUpdate()
                        ->first();
                    if (! $member) {
                        throw ValidationException::withMessages([
                            'payments' => 'ผู้ป่วยไม่มีบัญชีสมาชิก',
                        ])->status(422);
                    }
                    $amount = (float) $p['amount'];
                    if ((float) $member->balance < $amount) {
                        throw ValidationException::withMessages([
                            'payments' => "ยอดเงินสมาชิกไม่เพียงพอ (คงเหลือ: {$member->balance})",
                        ])->status(422);
                    }
                    $before = (float) $member->balance;
                    $member->balance = $before - $amount;
                    $member->total_used = (float) $member->total_used + $amount;
                    $member->save();

                    MemberTransaction::create([
                        'member_account_id' => $member->id,
                        'type' => 'usage',
                        'amount' => $amount,
                        'balance_before' => $before,
                        'balance_after' => $member->balance,
                        'invoice_id' => $invoice->id,
                        'notes' => 'Used for invoice '.$invoice->invoice_number,
                        'created_by' => $cashier->id,
                    ]);
                }
            }

            // Persist payments (with MDR for credit_card)
            foreach ($payments as $p) {
                $row = [
                    'branch_id' => $invoice->branch_id,
                    'invoice_id' => $invoice->id,
                    'method' => $p['method'],
                    'amount' => (float) $p['amount'],
                    'bank_id' => $p['bank_id'] ?? null,
                    'reference_no' => $p['reference_no'] ?? null,
                    'payment_date' => now()->toDateString(),
                ];
                if ($p['method'] === 'credit_card' && ! empty($p['bank_id'])) {
                    $bank = Bank::query()->find($p['bank_id']);
                    if ($bank) {
                        $row['mdr_rate'] = $bank->mdr_rate;
                        $row['mdr_amount'] = round((float) $p['amount'] * (float) $bank->mdr_rate / 100, 2);
                    }
                }
                Payment::create($row);
            }

            // Auto follow-up for procedures with follow_up_days > 0;
            // also auto-create Course when procedure is a package.
            $procedureItems = $items->where('item_type', 'procedure')->values();
            foreach ($procedureItems as $item) {
                $proc = Procedure::query()->find($item->item_id);
                if (! $proc) {
                    continue;
                }
                if ((int) $proc->follow_up_days > 0) {
                    FollowUp::create([
                        'branch_id' => $invoice->branch_id,
                        'patient_id' => $invoice->patient_id,
                        'visit_id' => $visit->id,
                        'procedure_id' => $proc->id,
                        'doctor_id' => $item->doctor_id,
                        'follow_up_date' => now()->addDays((int) $proc->follow_up_days)->toDateString(),
                        'priority' => 'normal',
                        'status' => 'pending',
                    ]);
                }
                if ($proc->is_package && (int) $proc->package_sessions > 0) {
                    $this->courses->purchaseFromInvoiceItem($item, (int) $invoice->patient_id, (int) $invoice->branch_id);
                }
            }

            // Compute and persist commissions per invoice item
            $totalCommission = 0.0;
            foreach ($items as $item) {
                $rows = $this->commissions->buildForItem($item, (int) $invoice->branch_id, now());
                $this->commissions->persist($rows);
                foreach ($rows as $r) {
                    $totalCommission += (float) $r['amount'];
                }
            }
            $invoice->total_commission = round($totalCommission, 2);

            // MDR aggregate from credit_card payments (already saved above)
            $mdrTotal = (float) Payment::query()
                ->where('invoice_id', $invoice->id)
                ->whereNotNull('mdr_amount')
                ->sum('mdr_amount');

            $invoice->gross_profit = round(
                (float) $invoice->total_amount
                - (float) $invoice->total_cogs
                - (float) $invoice->total_commission
                - $mdrTotal,
                2,
            );

            // Update patient denormalized stats
            $patient = $invoice->patient()->lockForUpdate()->first();
            $patient->total_spent = (float) $patient->total_spent + (float) $invoice->total_amount;
            $patient->visit_count = (int) $patient->visit_count + 1;
            $patient->last_visit_at = now();
            $patient->save();

            // Close invoice + visit
            $invoice->status = 'paid';
            $invoice->cashier_id = $cashier->id;
            $invoice->save();

            $visit->status = 'completed';
            $visit->check_out_at = now();
            $visit->total_amount = $invoice->total_amount;
            $visit->save();

            // Redeem coupon (atomic increment of used_count + log)
            if ($couponObj && $couponDiscount > 0) {
                $this->coupons->redeem($couponObj, $invoice, $couponDiscount);
            }

            // Post double-entry accounting (idempotent via document_type+id check)
            try {
                $poster = app(AccountingPoster::class);
                $invoice->refresh();
                $invoice->load(['items', 'payments']);
                $poster->postInvoice($invoice);
                $poster->postCommissionsForInvoice($invoice);
            } catch (\Throwable $e) {
                Log::warning('Accounting post failed for invoice '.$invoice->id.': '.$e->getMessage());
            }

            // Invalidate dashboard / MIS / daily P/L caches for this branch
            try {
                app(CacheService::class)->forgetBranch((int) $invoice->branch_id);
            } catch (\Throwable $e) {
            }

            return $invoice->fresh(['items', 'payments']);
        });
    }
}
