<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceiving;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockRequisition;
use App\Models\Warehouse;
use App\Services\ExpiryService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct(
        private StockService $stocks,
        private ExpiryService $expiry,
    ) {}

    public function products(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $perPage = (int) min(200, max(1, $request->integer('per_page', 50)));

        $q = Product::query()
            ->where('branch_id', $branchId)
            ->with('category:id,name')
            ->orderBy('name');

        if ($s = $request->query('q')) {
            $q->where(function ($qq) use ($s) {
                $qq->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%");
            });
        }

        $page = $q->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'unit' => $p->unit,
                'category' => $p->category?->name,
                'selling_price' => (float) $p->selling_price,
                'cost_price' => (float) $p->cost_price,
                'min_stock' => $p->min_stock,
                'reorder_point' => $p->reorder_point,
                'is_active' => $p->is_active,
            ]),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:30', Rule::unique('products')->where(fn ($q) => $q->where('branch_id', $branchId))],
            'name' => ['required', 'string', 'max:200'],
            'unit' => ['nullable', 'string', 'max:20'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'max_stock' => ['nullable', 'integer', 'min:0'],
            'reorder_point' => ['nullable', 'integer', 'min:0'],
            'block_dispensing_when_expired' => ['nullable', 'boolean'],
        ]);
        $data['branch_id'] = $branchId;
        $product = Product::create($data);

        return response()->json(['data' => $product], 201);
    }

    public function warehouses(): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $rows = Warehouse::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return response()->json(['data' => $rows]);
    }

    public function stockLevels(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $type = $request->query('warehouse_type');
        $warehouseId = (int) $request->query('warehouse_id', 0);

        $q = StockLevel::query()
            ->with(['product:id,name,sku,unit,reorder_point', 'warehouse:id,name,type,branch_id'])
            ->whereHas('warehouse', function ($w) use ($branchId, $type) {
                $w->where('branch_id', $branchId);
                if ($type) {
                    $w->where('type', $type);
                }
            })
            ->where('quantity', '>', 0);

        if ($warehouseId) {
            $q->where('warehouse_id', $warehouseId);
        }

        if ($s = $request->query('q')) {
            $q->whereHas('product', function ($p) use ($s) {
                $p->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%");
            });
        }

        $rows = $q->orderBy('expiry_date')->limit(500)->get();

        return response()->json([
            'data' => $rows->map(fn (StockLevel $l) => [
                'id' => $l->id,
                'product' => [
                    'id' => $l->product_id,
                    'name' => $l->product?->name,
                    'sku' => $l->product?->sku,
                    'unit' => $l->product?->unit,
                ],
                'warehouse' => [
                    'id' => $l->warehouse_id,
                    'name' => $l->warehouse?->name,
                    'type' => $l->warehouse?->type,
                ],
                'lot_no' => $l->lot_no,
                'expiry_date' => $l->expiry_date?->toDateString(),
                'quantity' => (int) $l->quantity,
                'cost_price' => (float) $l->cost_price,
                'expiry_bucket' => $this->expiry->classifyLevel($l),
            ]),
        ]);
    }

    public function lowStock(): JsonResponse
    {
        $branchId = (int) app('branch.id');

        $rows = DB::table('products')
            ->leftJoin('stock_levels', 'stock_levels.product_id', '=', 'products.id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')
            ->where('products.branch_id', $branchId)
            ->where('products.is_active', true)
            ->selectRaw('products.id, products.name, products.sku, products.unit, products.reorder_point, products.min_stock, COALESCE(SUM(stock_levels.quantity), 0) as total_qty')
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.unit', 'products.reorder_point', 'products.min_stock')
            ->havingRaw('COALESCE(SUM(stock_levels.quantity), 0) <= products.reorder_point')
            ->orderByDesc('products.reorder_point')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'product_id' => $r->id,
                'sku' => $r->sku,
                'name' => $r->name,
                'unit' => $r->unit,
                'total_qty' => (int) $r->total_qty,
                'reorder_point' => (int) $r->reorder_point,
                'min_stock' => (int) $r->min_stock,
                'shortage' => max(0, (int) $r->reorder_point - (int) $r->total_qty),
            ]),
        ]);
    }

    public function expiryAlerts(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $bucket = $request->query('bucket');

        $rows = StockLevel::query()
            ->with(['product:id,name,sku,unit', 'warehouse:id,name,type,branch_id'])
            ->whereHas('warehouse', fn ($w) => $w->where('branch_id', $branchId))
            ->whereNotNull('expiry_date')
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date')
            ->limit(500)
            ->get();

        $data = $rows
            ->map(function (StockLevel $l) {
                return [
                    'id' => $l->id,
                    'product' => $l->product?->name,
                    'sku' => $l->product?->sku,
                    'warehouse' => $l->warehouse?->name,
                    'lot_no' => $l->lot_no,
                    'expiry_date' => $l->expiry_date?->toDateString(),
                    'quantity' => (int) $l->quantity,
                    'bucket' => $this->expiry->classifyLevel($l),
                ];
            })
            ->when($bucket, fn ($c) => $c->where('bucket', $bucket))
            ->values();

        $summary = [
            'expired' => 0, 'red' => 0, 'orange' => 0, 'yellow' => 0, 'green' => 0,
        ];
        foreach ($data as $d) {
            $summary[$d['bucket']] = ($summary[$d['bucket']] ?? 0) + 1;
        }

        return response()->json(['data' => $data, 'meta' => ['summary' => $summary]]);
    }

    public function movements(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $perPage = (int) min(200, max(1, $request->integer('per_page', 50)));

        $q = StockMovement::query()
            ->where('branch_id', $branchId)
            ->with(['product:id,name,sku', 'warehouse:id,name', 'user:id,uuid,name'])
            ->orderByDesc('id');

        if ($p = $request->query('product_id')) {
            $q->where('product_id', $p);
        }
        if ($w = $request->query('warehouse_id')) {
            $q->where('warehouse_id', $w);
        }
        if ($t = $request->query('type')) {
            $q->where('type', $t);
        }

        $page = $q->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (StockMovement $m) => [
                'id' => $m->id,
                'type' => $m->type,
                'product' => $m->product?->name,
                'warehouse' => $m->warehouse?->name,
                'quantity' => (int) $m->quantity,
                'before_qty' => (int) $m->before_qty,
                'after_qty' => (int) $m->after_qty,
                'lot_no' => $m->lot_no,
                'expiry_date' => optional($m->expiry_date)->toDateString(),
                'reference_type' => $m->reference_type,
                'reference_id' => $m->reference_id,
                'user' => $m->user?->name,
                'created_at' => optional($m->created_at)->toIso8601String(),
            ]),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function receivings(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $rows = GoodsReceiving::query()
            ->where('branch_id', $branchId)
            ->with(['warehouse:id,name', 'supplier:id,name', 'receiver:id,name', 'items'])
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $rows->map(fn (GoodsReceiving $g) => [
            'id' => $g->id,
            'document_no' => $g->document_no,
            'receive_date' => $g->receive_date?->toDateString(),
            'warehouse' => $g->warehouse?->name,
            'supplier' => $g->supplier?->name,
            'received_by' => $g->receiver?->name,
            'subtotal' => (float) $g->subtotal,
            'vat_amount' => (float) $g->vat_amount,
            'total_amount' => (float) $g->total_amount,
            'status' => $g->status,
            'item_count' => $g->items->count(),
        ])]);
    }

    public function storeReceiving(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'receive_date' => ['required', 'date'],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.lot_no' => ['nullable', 'string', 'max:50'],
            'items.*.expiry_date' => ['nullable', 'date'],
        ]);

        return DB::transaction(function () use ($data, $branchId, $request) {
            $subtotal = 0.0;
            foreach ($data['items'] as $i) {
                $subtotal += $i['quantity'] * $i['unit_cost'];
            }
            $vat = (float) ($data['vat_amount'] ?? 0);

            $documentNo = 'GR'.date('Ymd').'-'.str_pad((string) (GoodsReceiving::count() + 1), 4, '0', STR_PAD_LEFT);

            $gr = GoodsReceiving::create([
                'branch_id' => $branchId,
                'warehouse_id' => $data['warehouse_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'document_no' => $documentNo,
                'receive_date' => $data['receive_date'],
                'subtotal' => $subtotal,
                'vat_amount' => $vat,
                'total_amount' => $subtotal + $vat,
                'status' => 'completed',
                'received_by' => $request->user()->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $i) {
                $gr->items()->create([
                    'product_id' => $i['product_id'],
                    'quantity' => $i['quantity'],
                    'unit_cost' => $i['unit_cost'],
                    'total' => $i['quantity'] * $i['unit_cost'],
                    'lot_no' => $i['lot_no'] ?? null,
                    'expiry_date' => $i['expiry_date'] ?? null,
                ]);
            }

            $this->stocks->applyReceiving($gr->fresh('items'), $request->user()->id);

            return response()->json(['data' => $gr->fresh('items')], 201);
        });
    }

    public function requisitions(): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $rows = StockRequisition::query()
            ->where('branch_id', $branchId)
            ->with(['source:id,name', 'destination:id,name', 'requester:id,name', 'approver:id,name', 'items.product:id,name,sku,unit'])
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $rows->map(fn (StockRequisition $r) => [
            'id' => $r->id,
            'document_no' => $r->document_no,
            'status' => $r->status,
            'source' => $r->source?->name,
            'destination' => $r->destination?->name,
            'requested_by' => $r->requester?->name,
            'approved_by' => $r->approver?->name,
            'requested_at' => $r->requested_at?->toIso8601String(),
            'approved_at' => $r->approved_at?->toIso8601String(),
            'items' => $r->items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'product_name' => $i->product?->name,
                'unit' => $i->product?->unit,
                'requested_qty' => $i->requested_qty,
                'approved_qty' => $i->approved_qty,
            ]),
        ])]);
    }

    public function storeRequisition(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'source_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'dest_warehouse_id' => ['required', 'integer', 'different:source_warehouse_id', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.requested_qty' => ['required', 'integer', 'min:1'],
        ]);

        return DB::transaction(function () use ($data, $branchId, $request) {
            $documentNo = 'RQ'.date('Ymd').'-'.str_pad((string) (StockRequisition::count() + 1), 4, '0', STR_PAD_LEFT);

            $req = StockRequisition::create([
                'branch_id' => $branchId,
                'document_no' => $documentNo,
                'source_warehouse_id' => $data['source_warehouse_id'],
                'dest_warehouse_id' => $data['dest_warehouse_id'],
                'status' => 'pending',
                'requested_by' => $request->user()->id,
                'requested_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $i) {
                $req->items()->create([
                    'product_id' => $i['product_id'],
                    'requested_qty' => $i['requested_qty'],
                    'approved_qty' => 0,
                ]);
            }

            return response()->json(['data' => $req->fresh('items')], 201);
        });
    }

    public function approveRequisition(Request $request, StockRequisition $requisition): JsonResponse
    {
        if ($requisition->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'ใบเบิกต้องอยู่สถานะ pending เท่านั้น']);
        }

        $data = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required_with:items', 'integer'],
            'items.*.approved_qty' => ['required_with:items', 'integer', 'min:0'],
        ]);

        return DB::transaction(function () use ($requisition, $data, $request) {
            $requisition->loadMissing('items');
            if (! empty($data['items'])) {
                foreach ($data['items'] as $row) {
                    $requisition->items()
                        ->where('id', $row['id'])
                        ->update(['approved_qty' => $row['approved_qty']]);
                }
            } else {
                foreach ($requisition->items as $i) {
                    $i->approved_qty = $i->requested_qty;
                    $i->save();
                }
            }

            $requisition->refresh()->loadMissing('items');
            $this->stocks->applyRequisition($requisition, $request->user()->id);

            $requisition->status = 'completed';
            $requisition->approved_by = $request->user()->id;
            $requisition->approved_at = now();
            $requisition->save();

            return response()->json(['data' => $requisition->fresh(['items', 'approver:id,name'])]);
        });
    }

    public function rejectRequisition(Request $request, StockRequisition $requisition): JsonResponse
    {
        if ($requisition->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'ใบเบิกต้องอยู่สถานะ pending เท่านั้น']);
        }
        $requisition->status = 'rejected';
        $requisition->approved_by = $request->user()->id;
        $requisition->approved_at = now();
        $requisition->save();

        return response()->json(['data' => $requisition->fresh()]);
    }

    public function adjust(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'delta' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
            'lot_no' => ['nullable', 'string', 'max:50'],
        ]);

        $level = $this->stocks->adjust(
            productId: (int) $data['product_id'],
            warehouseId: (int) $data['warehouse_id'],
            delta: (int) $data['delta'],
            reason: $data['reason'],
            branchId: $branchId,
            userId: (int) $request->user()->id,
            lotNo: $data['lot_no'] ?? null,
        );

        return response()->json(['data' => $level]);
    }
}
