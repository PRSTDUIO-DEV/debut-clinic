<?php

namespace App\Services\Accounting;

use App\Models\GoodsReceiving;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcurementService
{
    public function __construct(private StockService $stocks) {}

    public function nextPrNumber(int $branchId): string
    {
        $prefix = 'PR'.now()->format('Ymd').'-';
        $last = PurchaseRequest::query()
            ->where('branch_id', $branchId)
            ->where('pr_number', 'like', $prefix.'%')
            ->orderByDesc('pr_number')
            ->value('pr_number');
        $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function nextPoNumber(int $branchId): string
    {
        $prefix = 'PO'.now()->format('Ymd').'-';
        $last = PurchaseOrder::query()
            ->where('branch_id', $branchId)
            ->where('po_number', 'like', $prefix.'%')
            ->orderByDesc('po_number')
            ->value('po_number');
        $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function submitPr(PurchaseRequest $pr): PurchaseRequest
    {
        if ($pr->status !== 'draft') {
            throw ValidationException::withMessages(['status' => "PR must be draft (current: {$pr->status})"]);
        }
        $pr->status = 'submitted';
        $pr->submitted_at = now();
        $pr->save();

        return $pr->fresh();
    }

    public function approvePr(PurchaseRequest $pr, User $approver): PurchaseRequest
    {
        if (! in_array($pr->status, ['draft', 'submitted'], true)) {
            throw ValidationException::withMessages(['status' => "PR must be draft/submitted (current: {$pr->status})"]);
        }
        $pr->status = 'approved';
        $pr->approved_by = $approver->id;
        $pr->approved_at = now();
        $pr->save();

        return $pr->fresh();
    }

    public function rejectPr(PurchaseRequest $pr, string $reason, User $approver): PurchaseRequest
    {
        if (! in_array($pr->status, ['draft', 'submitted'], true)) {
            throw ValidationException::withMessages(['status' => "PR cannot be rejected (current: {$pr->status})"]);
        }
        $pr->status = 'rejected';
        $pr->approved_by = $approver->id;
        $pr->approved_at = now();
        $pr->rejection_reason = $reason;
        $pr->save();

        return $pr->fresh();
    }

    public function convertToPo(PurchaseRequest $pr, Supplier $supplier, ?string $expectedDate = null, ?float $vatPercent = 7.0): PurchaseOrder
    {
        if ($pr->status !== 'approved') {
            throw ValidationException::withMessages(['status' => "PR must be approved (current: {$pr->status})"]);
        }
        if ($pr->branch_id !== $supplier->branch_id) {
            throw ValidationException::withMessages(['supplier' => 'Supplier must be in same branch as PR']);
        }

        return DB::transaction(function () use ($pr, $supplier, $expectedDate, $vatPercent) {
            $pr->loadMissing('items');

            $subtotal = 0.0;
            foreach ($pr->items as $i) {
                $subtotal += (int) $i->quantity * (float) $i->estimated_cost;
            }
            $vat = round($subtotal * ((float) $vatPercent) / 100, 2);

            $po = PurchaseOrder::create([
                'branch_id' => $pr->branch_id,
                'pr_id' => $pr->id,
                'supplier_id' => $supplier->id,
                'po_number' => $this->nextPoNumber((int) $pr->branch_id),
                'order_date' => now()->toDateString(),
                'expected_date' => $expectedDate,
                'status' => 'draft',
                'subtotal' => $subtotal,
                'vat_amount' => $vat,
                'total' => $subtotal + $vat,
            ]);

            foreach ($pr->items as $i) {
                PurchaseOrderItem::create([
                    'po_id' => $po->id,
                    'product_id' => $i->product_id,
                    'description' => $i->description,
                    'quantity' => $i->quantity,
                    'unit_cost' => $i->estimated_cost,
                    'total' => (int) $i->quantity * (float) $i->estimated_cost,
                ]);
            }

            $pr->status = 'converted';
            $pr->save();

            return $po->fresh('items');
        });
    }

    public function sendPo(PurchaseOrder $po, User $user): PurchaseOrder
    {
        if ($po->status !== 'draft') {
            throw ValidationException::withMessages(['status' => "PO must be draft (current: {$po->status})"]);
        }
        $po->status = 'sent';
        $po->sent_at = now();
        $po->approved_by = $user->id;
        $po->save();

        return $po->fresh();
    }

    /**
     * Receive items from PO. Optionally generates a GoodsReceiving record + writes stock.
     *
     * @param array<int, array{po_item_id:int, qty:int, lot_no?:string, expiry_date?:string}> $rows
     */
    public function receivePo(PurchaseOrder $po, array $rows, int $warehouseId, User $user): GoodsReceiving
    {
        if (! in_array($po->status, ['sent', 'partial_received'], true)) {
            throw ValidationException::withMessages(['status' => "PO must be sent or partial_received (current: {$po->status})"]);
        }

        return DB::transaction(function () use ($po, $rows, $warehouseId, $user) {
            $po->loadMissing('items');
            $itemsById = $po->items->keyBy('id');

            $documentNo = 'GR-PO'.$po->id.'-'.now()->format('His').'-'.substr(uniqid('', true), -4);
            $gr = GoodsReceiving::create([
                'branch_id' => $po->branch_id,
                'warehouse_id' => $warehouseId,
                'supplier_id' => $po->supplier_id,
                'document_no' => $documentNo,
                'receive_date' => now()->toDateString(),
                'subtotal' => 0,
                'vat_amount' => 0,
                'total_amount' => 0,
                'status' => 'completed',
                'received_by' => $user->id,
                'notes' => "Receiving for PO {$po->po_number}",
            ]);

            $subtotal = 0.0;
            foreach ($rows as $row) {
                $poItem = $itemsById->get($row['po_item_id']);
                if (! $poItem) {
                    throw ValidationException::withMessages(['rows' => 'po_item_id '.$row['po_item_id'].' not in PO']);
                }
                $qty = (int) $row['qty'];
                if ($qty <= 0) {
                    continue;
                }
                $remaining = (int) $poItem->quantity - (int) $poItem->received_qty;
                if ($qty > $remaining) {
                    throw ValidationException::withMessages(['rows' => "qty {$qty} > remaining {$remaining} for item {$poItem->description}"]);
                }

                if ($poItem->product_id) {
                    $gr->items()->create([
                        'product_id' => $poItem->product_id,
                        'quantity' => $qty,
                        'unit_cost' => $poItem->unit_cost,
                        'total' => (float) $poItem->unit_cost * $qty,
                        'lot_no' => $row['lot_no'] ?? null,
                        'expiry_date' => $row['expiry_date'] ?? null,
                    ]);
                }
                $poItem->received_qty = (int) $poItem->received_qty + $qty;
                $poItem->save();
                $subtotal += (float) $poItem->unit_cost * $qty;
            }

            $gr->subtotal = $subtotal;
            $gr->total_amount = $subtotal;
            $gr->save();

            // Apply stock movements + create stock_levels lots
            $this->stocks->applyReceiving($gr->fresh('items'), $user->id);

            // Update PO status
            $po->refresh()->loadMissing('items');
            $allReceived = $po->items->every(fn ($i) => (int) $i->received_qty >= (int) $i->quantity);
            $anyReceived = $po->items->contains(fn ($i) => (int) $i->received_qty > 0);
            $po->status = $allReceived ? 'received' : ($anyReceived ? 'partial_received' : $po->status);
            $po->save();

            return $gr->fresh('items');
        });
    }

    public function cancelPo(PurchaseOrder $po, string $reason): PurchaseOrder
    {
        if ($po->status === 'received') {
            throw ValidationException::withMessages(['status' => 'PO already received cannot be cancelled']);
        }
        $po->status = 'cancelled';
        $po->notes = trim(($po->notes ? $po->notes."\n" : '').'[CANCEL] '.$reason);
        $po->save();

        return $po->fresh();
    }
}
