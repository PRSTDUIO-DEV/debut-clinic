<?php

namespace App\Services\Marketing;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class PromotionService
{
    /**
     * Active promotions for branch on a given date.
     *
     * @return Collection<int, Promotion>
     */
    public function applicableNow(int $branchId, ?string $date = null)
    {
        $date = $date ?: Carbon::today()->toDateString();

        return Promotion::where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $date)
            ->whereDate('valid_to', '>=', $date)
            ->orderByDesc('priority')
            ->get();
    }

    /**
     * Evaluate a promotion against a cart and return the discount + breakdown.
     *
     * Cart format: [
     *   'subtotal' => 5000,
     *   'items' => [['item_id'=>1, 'item_type'=>'procedure', 'category_id'=>2, 'unit_price'=>500, 'quantity'=>2, 'total'=>1000], ...],
     * ]
     *
     * @return array{discount: float, applied: bool, reason: string}
     */
    public function applyToCart(Promotion $promo, array $cart): array
    {
        $rules = $promo->rules ?? [];
        $subtotal = (float) ($cart['subtotal'] ?? 0);

        if (! empty($rules['min_amount']) && $subtotal < (float) $rules['min_amount']) {
            return ['discount' => 0, 'applied' => false, 'reason' => 'ยอดต่ำกว่า min_amount'];
        }

        $applicable = $cart['items'] ?? [];
        if (! empty($rules['applicable_category'])) {
            $cat = (int) $rules['applicable_category'];
            $applicable = array_values(array_filter($applicable, fn ($i) => (int) ($i['category_id'] ?? 0) === $cat));
        }
        if (! empty($rules['applicable_procedure'])) {
            $proc = (int) $rules['applicable_procedure'];
            $applicable = array_values(array_filter($applicable, fn ($i) => (int) ($i['item_id'] ?? 0) === $proc && ($i['item_type'] ?? '') === 'procedure'));
        }
        if (! empty($rules['applicable_item_type'])) {
            $type = $rules['applicable_item_type'];
            $applicable = array_values(array_filter($applicable, fn ($i) => ($i['item_type'] ?? '') === $type));
        }

        $applicableSubtotal = array_sum(array_map(fn ($i) => (float) ($i['total'] ?? ($i['unit_price'] * $i['quantity'])), $applicable));

        // For percent/fixed promo we use applicable subtotal (or full cart if no filter)
        $base = empty($rules['applicable_category']) && empty($rules['applicable_procedure']) && empty($rules['applicable_item_type'])
            ? $subtotal : $applicableSubtotal;

        if ($base <= 0) {
            return ['discount' => 0, 'applied' => false, 'reason' => 'ไม่มีรายการที่เข้าเงื่อนไข'];
        }

        $discount = match ($promo->type) {
            'percent' => round($base * ((float) ($rules['value'] ?? 0) / 100), 2),
            'fixed' => min($base, (float) ($rules['value'] ?? 0)),
            'buy_x_get_y' => $this->calcBuyXGetY($applicable, (int) ($rules['buy_qty'] ?? 1), (int) ($rules['get_qty'] ?? 1)),
            'bundle' => min($base, (float) ($rules['fixed_bundle_price'] ?? 0)) > 0 ? max(0, $base - (float) $rules['fixed_bundle_price']) : 0,
            default => 0,
        };

        if (! empty($rules['max_discount'])) {
            $discount = min($discount, (float) $rules['max_discount']);
        }
        $discount = min($discount, $subtotal);

        return ['discount' => round($discount, 2), 'applied' => $discount > 0, 'reason' => 'OK'];
    }

    /**
     * Find best (highest discount) promotion for cart.
     *
     * @return array{promotion: ?Promotion, discount: float}
     */
    public function bestForCart(int $branchId, array $cart): array
    {
        $best = ['promotion' => null, 'discount' => 0.0];
        foreach ($this->applicableNow($branchId) as $p) {
            $r = $this->applyToCart($p, $cart);
            if ($r['applied'] && $r['discount'] > $best['discount']) {
                $best = ['promotion' => $p, 'discount' => $r['discount']];
            }
        }

        return $best;
    }

    private function calcBuyXGetY(array $items, int $buyQty, int $getQty): float
    {
        if ($buyQty <= 0 || $getQty <= 0) {
            return 0;
        }
        // Sort items by unit_price asc; cheapest will be the "free" ones
        $expanded = [];
        foreach ($items as $i) {
            $qty = (int) ($i['quantity'] ?? 1);
            for ($k = 0; $k < $qty; $k++) {
                $expanded[] = (float) ($i['unit_price'] ?? 0);
            }
        }
        sort($expanded);
        $totalQty = count($expanded);
        $sets = intdiv($totalQty, $buyQty + $getQty);
        $freeCount = $sets * $getQty;

        return round(array_sum(array_slice($expanded, 0, $freeCount)), 2);
    }
}
