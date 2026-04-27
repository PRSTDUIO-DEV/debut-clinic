<?php

namespace App\Services\Marketing;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Invoice;
use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * Generate one or many coupons.
     *
     * @return array<int, Coupon>
     */
    public function generate(int $branchId, array $template, int $count = 1, ?string $prefix = null): array
    {
        $created = [];
        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(($prefix ? trim($prefix, '-').'-' : '').Str::random(8));
            // Ensure unique
            while (Coupon::where('code', $code)->exists()) {
                $code = strtoupper(($prefix ? trim($prefix, '-').'-' : '').Str::random(8));
            }
            $created[] = Coupon::create(array_merge([
                'branch_id' => $branchId,
                'is_active' => true,
                'used_count' => 0,
            ], $template, ['code' => $code]));
        }

        return $created;
    }

    /**
     * Validate a coupon code for a patient + cart subtotal.
     *
     * @return array{coupon: Coupon, discount: float}
     *
     * @throws ValidationException
     */
    public function validate(string $code, ?Patient $patient, float $subtotal, ?int $branchId = null): array
    {
        $q = Coupon::where('code', strtoupper(trim($code)))->where('is_active', true);
        if ($branchId) {
            $q->where('branch_id', $branchId);
        }
        /** @var Coupon|null $coupon */
        $coupon = $q->first();
        if (! $coupon) {
            throw ValidationException::withMessages(['code' => 'ไม่พบคูปอง หรือคูปองถูกปิดใช้งาน']);
        }

        $today = Carbon::today();
        if ($today->lt($coupon->valid_from) || $today->gt($coupon->valid_to)) {
            throw ValidationException::withMessages(['code' => 'คูปองหมดอายุ หรือยังไม่ถึงวันที่ใช้ได้']);
        }

        if ($coupon->max_uses && $coupon->used_count >= $coupon->max_uses) {
            throw ValidationException::withMessages(['code' => 'คูปองถูกใช้ครบจำนวนแล้ว']);
        }

        if ($coupon->min_amount && $subtotal < (float) $coupon->min_amount) {
            throw ValidationException::withMessages(['code' => 'ยอดยังไม่ถึงขั้นต่ำของคูปอง: '.number_format($coupon->min_amount, 2)]);
        }

        if ($patient && $coupon->max_per_customer) {
            $usedByPatient = CouponRedemption::where('coupon_id', $coupon->id)
                ->where('patient_id', $patient->id)
                ->count();
            if ($usedByPatient >= $coupon->max_per_customer) {
                throw ValidationException::withMessages(['code' => 'ลูกค้าใช้คูปองนี้ครบสิทธิ์แล้ว']);
            }
        }

        $discount = $this->calculateDiscount($coupon, $subtotal);

        return ['coupon' => $coupon, 'discount' => $discount];
    }

    /**
     * Atomically redeem a coupon for an invoice.
     */
    public function redeem(Coupon $coupon, Invoice $invoice, float $amountDiscounted): CouponRedemption
    {
        return DB::transaction(function () use ($coupon, $invoice, $amountDiscounted) {
            $coupon = Coupon::lockForUpdate()->find($coupon->id);
            if ($coupon->max_uses && $coupon->used_count >= $coupon->max_uses) {
                throw ValidationException::withMessages(['code' => 'คูปองถูกใช้ครบจำนวนแล้ว']);
            }
            $coupon->increment('used_count');

            return CouponRedemption::create([
                'coupon_id' => $coupon->id,
                'patient_id' => $invoice->patient_id,
                'invoice_id' => $invoice->id,
                'amount_discounted' => $amountDiscounted,
                'redeemed_at' => now(),
            ]);
        });
    }

    public function calculateDiscount(Coupon $coupon, float $subtotal): float
    {
        $discount = $coupon->type === 'percent'
            ? round($subtotal * ((float) $coupon->value / 100), 2)
            : (float) $coupon->value;

        if ($coupon->max_discount) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        return min($discount, $subtotal);
    }
}
