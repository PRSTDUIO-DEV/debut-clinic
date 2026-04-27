<?php

namespace App\Services;

use App\Models\GoodsReceiving;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockRequisition;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    /**
     * Apply a receiving: per item upsert stock_level (lot match) and write movement.
     * Caller wraps in DB::transaction.
     */
    public function applyReceiving(GoodsReceiving $receiving, ?int $userId = null): void
    {
        DB::transaction(function () use ($receiving, $userId) {
            $receiving->loadMissing('items');
            foreach ($receiving->items as $item) {
                $this->incrementLot(
                    productId: (int) $item->product_id,
                    warehouseId: (int) $receiving->warehouse_id,
                    qty: (int) $item->quantity,
                    cost: (float) $item->unit_cost,
                    lotNo: $item->lot_no,
                    expiry: $item->expiry_date,
                    branchId: (int) $receiving->branch_id,
                    referenceType: 'goods_receiving',
                    referenceId: (int) $receiving->id,
                    userId: $userId,
                    type: 'receive',
                );
            }
        });
    }

    /**
     * Approve a requisition: deduct FIFO from source, add to destination, write movements.
     */
    public function applyRequisition(StockRequisition $req, ?int $userId = null): void
    {
        DB::transaction(function () use ($req, $userId) {
            $req->loadMissing('items');
            foreach ($req->items as $item) {
                $qty = (int) ($item->approved_qty ?: $item->requested_qty);
                if ($qty <= 0) {
                    continue;
                }
                $picks = $this->pickFifo(
                    productId: (int) $item->product_id,
                    warehouseId: (int) $req->source_warehouse_id,
                    qty: $qty,
                    allowExpired: false,
                );

                foreach ($picks as $pick) {
                    [$level, $deductQty] = $pick;

                    $this->writeMovement(
                        branchId: (int) $req->branch_id,
                        productId: (int) $level->product_id,
                        warehouseId: (int) $req->source_warehouse_id,
                        type: 'transfer_out',
                        qtyChange: -$deductQty,
                        before: (int) $level->quantity,
                        after: ((int) $level->quantity) - $deductQty,
                        lotNo: $level->lot_no,
                        expiry: $level->expiry_date,
                        cost: (float) $level->cost_price,
                        refType: 'stock_requisition',
                        refId: (int) $req->id,
                        userId: $userId,
                    );

                    $level->quantity = ((int) $level->quantity) - $deductQty;
                    $level->save();

                    $this->incrementLot(
                        productId: (int) $level->product_id,
                        warehouseId: (int) $req->dest_warehouse_id,
                        qty: $deductQty,
                        cost: (float) $level->cost_price,
                        lotNo: $level->lot_no,
                        expiry: $level->expiry_date,
                        branchId: (int) $req->branch_id,
                        referenceType: 'stock_requisition',
                        referenceId: (int) $req->id,
                        userId: $userId,
                        type: 'transfer_in',
                    );
                }
            }
        });
    }

    /**
     * Deduct from a warehouse using FIFO (oldest expiry first, then oldest received_at).
     * Returns array of [lot_no, qty, cost_price] consumed (for COGS calc).
     *
     * @return array<int, array{lot_no:?string, qty:int, cost_price:float, expiry_date:?string}>
     */
    public function deduct(
        int $productId,
        int $warehouseId,
        int $qty,
        int $branchId,
        string $refType,
        int $refId,
        ?int $userId = null,
        bool $allowExpired = false,
        ?string $movementType = 'pos_deduct',
    ): array {
        if ($qty <= 0) {
            throw ValidationException::withMessages(['quantity' => 'จำนวนต้องมากกว่า 0']);
        }

        return DB::transaction(function () use (
            $productId, $warehouseId, $qty, $branchId, $refType, $refId, $userId, $allowExpired, $movementType
        ) {
            $picks = $this->pickFifo($productId, $warehouseId, $qty, $allowExpired);

            $consumed = [];
            foreach ($picks as $pick) {
                [$level, $deductQty] = $pick;

                $this->writeMovement(
                    branchId: $branchId,
                    productId: $productId,
                    warehouseId: $warehouseId,
                    type: $movementType,
                    qtyChange: -$deductQty,
                    before: (int) $level->quantity,
                    after: ((int) $level->quantity) - $deductQty,
                    lotNo: $level->lot_no,
                    expiry: $level->expiry_date,
                    cost: (float) $level->cost_price,
                    refType: $refType,
                    refId: $refId,
                    userId: $userId,
                );

                $level->quantity = ((int) $level->quantity) - $deductQty;
                $level->save();

                $consumed[] = [
                    'lot_no' => $level->lot_no,
                    'qty' => $deductQty,
                    'cost_price' => (float) $level->cost_price,
                    'expiry_date' => $level->expiry_date?->toDateString(),
                ];
            }

            return $consumed;
        });
    }

    public function adjust(
        int $productId,
        int $warehouseId,
        int $delta,
        string $reason,
        int $branchId,
        ?int $userId = null,
        ?string $lotNo = null,
    ): StockLevel {
        return DB::transaction(function () use ($productId, $warehouseId, $delta, $reason, $branchId, $userId, $lotNo) {
            $level = StockLevel::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->when($lotNo, fn ($q) => $q->where('lot_no', $lotNo))
                ->lockForUpdate()
                ->first();

            if (! $level) {
                if ($delta < 0) {
                    throw ValidationException::withMessages(['adjust' => 'ไม่มี stock ให้ลด']);
                }
                $level = StockLevel::create([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => 0,
                    'lot_no' => $lotNo,
                ]);
            }

            $before = (int) $level->quantity;
            $after = $before + $delta;
            if ($after < 0) {
                throw ValidationException::withMessages(['adjust' => 'จำนวนหลังปรับต้องไม่ติดลบ']);
            }
            $level->quantity = $after;
            $level->save();

            $this->writeMovement(
                branchId: $branchId,
                productId: $productId,
                warehouseId: $warehouseId,
                type: 'adjust',
                qtyChange: $delta,
                before: $before,
                after: $after,
                lotNo: $level->lot_no,
                expiry: $level->expiry_date,
                cost: (float) $level->cost_price,
                refType: 'manual',
                refId: 0,
                userId: $userId,
                notes: $reason,
            );

            return $level->fresh();
        });
    }

    /**
     * Pick stock_levels FIFO (oldest expiry first, nulls last; tiebreaker by id).
     *
     * @return array<int, array{0: StockLevel, 1: int}>
     */
    private function pickFifo(int $productId, int $warehouseId, int $qty, bool $allowExpired): array
    {
        $levels = StockLevel::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('quantity', '>', 0)
            ->lockForUpdate()
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $product = Product::query()->find($productId);
        $today = Carbon::today();

        $remaining = $qty;
        $picks = [];

        foreach ($levels as $lv) {
            if ($remaining <= 0) {
                break;
            }
            if ($lv->expiry_date && $lv->expiry_date->lte($today)) {
                if (! $allowExpired && $product && $product->block_dispensing_when_expired) {
                    continue;
                }
            }
            $take = min((int) $lv->quantity, $remaining);
            if ($take <= 0) {
                continue;
            }
            $picks[] = [$lv, $take];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            $available = (int) StockLevel::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->sum('quantity');

            throw ValidationException::withMessages([
                'stock' => "สต็อกไม่พอ (ต้องการ {$qty}, มีจริง {$available})",
            ]);
        }

        return $picks;
    }

    private function incrementLot(
        int $productId,
        int $warehouseId,
        int $qty,
        float $cost,
        ?string $lotNo,
        $expiry,
        int $branchId,
        string $referenceType,
        int $referenceId,
        ?int $userId,
        string $type,
    ): StockLevel {
        $expiryDate = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : $expiry;

        $level = StockLevel::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where(function ($q) use ($lotNo) {
                $lotNo === null ? $q->whereNull('lot_no') : $q->where('lot_no', $lotNo);
            })
            ->where(function ($q) use ($expiryDate) {
                $expiryDate === null ? $q->whereNull('expiry_date') : $q->whereDate('expiry_date', $expiryDate);
            })
            ->lockForUpdate()
            ->first();

        $before = $level ? (int) $level->quantity : 0;

        if (! $level) {
            $level = StockLevel::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity' => $qty,
                'lot_no' => $lotNo,
                'expiry_date' => $expiryDate,
                'cost_price' => $cost,
                'received_at' => now(),
            ]);
        } else {
            $level->quantity = $before + $qty;
            if ($cost > 0) {
                $level->cost_price = $cost;
            }
            $level->received_at = now();
            $level->save();
        }

        $this->writeMovement(
            branchId: $branchId,
            productId: $productId,
            warehouseId: $warehouseId,
            type: $type,
            qtyChange: $qty,
            before: $before,
            after: $before + $qty,
            lotNo: $lotNo,
            expiry: $expiryDate,
            cost: $cost,
            refType: $referenceType,
            refId: $referenceId,
            userId: $userId,
        );

        return $level;
    }

    private function writeMovement(
        int $branchId,
        int $productId,
        int $warehouseId,
        string $type,
        int $qtyChange,
        int $before,
        int $after,
        ?string $lotNo,
        $expiry,
        ?float $cost,
        string $refType,
        int $refId,
        ?int $userId,
        ?string $notes = null,
    ): void {
        $expiryDate = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : $expiry;

        StockMovement::create([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'type' => $type,
            'quantity' => $qtyChange,
            'before_qty' => $before,
            'after_qty' => $after,
            'lot_no' => $lotNo,
            'expiry_date' => $expiryDate,
            'cost_price' => $cost,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'user_id' => $userId,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    public function totalAvailable(int $productId, int $warehouseId, bool $excludeExpired = true): int
    {
        $q = StockLevel::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId);
        if ($excludeExpired) {
            $today = Carbon::today()->toDateString();
            $q->where(function ($qq) use ($today) {
                $qq->whereNull('expiry_date')->orWhereDate('expiry_date', '>', $today);
            });
        }

        return (int) $q->sum('quantity');
    }

    public function defaultFloorWarehouse(int $branchId): ?Warehouse
    {
        return Warehouse::query()
            ->where('branch_id', $branchId)
            ->floor()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    public function defaultMainWarehouse(int $branchId): ?Warehouse
    {
        return Warehouse::query()
            ->where('branch_id', $branchId)
            ->main()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }
}
