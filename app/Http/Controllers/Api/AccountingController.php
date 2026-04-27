<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\Disbursement;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\TaxInvoice;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\DisbursementService;
use App\Services\Accounting\ProcurementService;
use App\Services\Accounting\TaxInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountingController extends Controller
{
    public function __construct(
        private ProcurementService $procurement,
        private DisbursementService $disbursements,
        private TaxInvoiceService $taxInvoices,
        private AccountingReportService $reports,
    ) {}

    // ───── CoA ─────

    public function coaIndex(): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $rows = ChartOfAccount::query()
            ->where('branch_id', $branchId)
            ->orderBy('code')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function coaStore(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('chart_of_accounts')->where(fn ($q) => $q->where('branch_id', $branchId))],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(ChartOfAccount::TYPES)],
            'parent_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['branch_id'] = $branchId;
        $data['is_active'] ??= true;

        return response()->json(['data' => ChartOfAccount::create($data)], 201);
    }

    public function coaUpdate(Request $request, ChartOfAccount $account): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($account->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        if ($account->is_system) {
            return response()->json(['message' => 'system account is read-only'], 422);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'parent_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $account->fill($data)->save();

        return response()->json(['data' => $account]);
    }

    // ───── Purchase Requests ─────

    public function prIndex(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $q = PurchaseRequest::query()
            ->where('branch_id', $branchId)
            ->with(['requester:id,name', 'approver:id,name', 'items.product:id,name,sku'])
            ->orderByDesc('id');
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }

        return response()->json(['data' => $q->limit(100)->get()]);
    }

    public function prShow(PurchaseRequest $pr): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($pr->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $pr->load(['requester:id,name', 'approver:id,name', 'items.product:id,name,sku']);

        return response()->json(['data' => $pr]);
    }

    public function prStore(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'request_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.description' => ['required', 'string', 'max:200'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.estimated_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $estimatedTotal = 0;
        foreach ($data['items'] as $i) {
            $estimatedTotal += $i['quantity'] * $i['estimated_cost'];
        }

        $pr = PurchaseRequest::create([
            'branch_id' => $branchId,
            'pr_number' => $this->procurement->nextPrNumber($branchId),
            'request_date' => $data['request_date'],
            'requested_by' => $request->user()->id,
            'status' => 'draft',
            'estimated_total' => $estimatedTotal,
            'notes' => $data['notes'] ?? null,
        ]);
        foreach ($data['items'] as $i) {
            $pr->items()->create($i);
        }

        return response()->json(['data' => $pr->fresh('items')], 201);
    }

    public function prSubmit(PurchaseRequest $pr): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($pr->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }

        return response()->json(['data' => $this->procurement->submitPr($pr)]);
    }

    public function prApprove(Request $request, PurchaseRequest $pr): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($pr->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }

        return response()->json(['data' => $this->procurement->approvePr($pr, $request->user())]);
    }

    public function prReject(Request $request, PurchaseRequest $pr): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($pr->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return response()->json(['data' => $this->procurement->rejectPr($pr, $data['reason'], $request->user())]);
    }

    public function prConvertToPo(Request $request, PurchaseRequest $pr): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($pr->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'expected_date' => ['nullable', 'date'],
            'vat_percent' => ['nullable', 'numeric', 'min:0', 'max:30'],
        ]);
        $supplier = Supplier::query()->where('id', $data['supplier_id'])->where('branch_id', $branchId)->firstOrFail();

        $po = $this->procurement->convertToPo($pr, $supplier, $data['expected_date'] ?? null, $data['vat_percent'] ?? 7.0);

        return response()->json(['data' => $po], 201);
    }

    // ───── Purchase Orders ─────

    public function poIndex(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $q = PurchaseOrder::query()
            ->where('branch_id', $branchId)
            ->with(['supplier:id,name', 'approver:id,name', 'items.product:id,name,sku'])
            ->orderByDesc('id');
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }

        return response()->json(['data' => $q->limit(100)->get()]);
    }

    public function poShow(PurchaseOrder $po): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($po->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $po->load(['supplier:id,name', 'approver:id,name', 'items.product:id,name,sku', 'purchaseRequest:id,pr_number']);

        return response()->json(['data' => $po]);
    }

    public function poSend(Request $request, PurchaseOrder $po): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($po->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }

        return response()->json(['data' => $this->procurement->sendPo($po, $request->user())]);
    }

    public function poReceive(Request $request, PurchaseOrder $po): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($po->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.po_item_id' => ['required', 'integer'],
            'rows.*.qty' => ['required', 'integer', 'min:1'],
            'rows.*.lot_no' => ['nullable', 'string', 'max:50'],
            'rows.*.expiry_date' => ['nullable', 'date'],
        ]);

        $gr = $this->procurement->receivePo($po, $data['rows'], (int) $data['warehouse_id'], $request->user());

        return response()->json(['data' => $gr], 201);
    }

    public function poCancel(Request $request, PurchaseOrder $po): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($po->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return response()->json(['data' => $this->procurement->cancelPo($po, $data['reason'])]);
    }

    // ───── Disbursements ─────

    public function disbursementIndex(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $q = Disbursement::query()
            ->where('branch_id', $branchId)
            ->with(['requester:id,name', 'approver:id,name'])
            ->orderByDesc('id');
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }
        if ($t = $request->query('type')) {
            $q->where('type', $t);
        }

        return response()->json(['data' => $q->limit(100)->get()]);
    }

    public function disbursementStore(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'disbursement_date' => ['required', 'date'],
            'type' => ['required', Rule::in(Disbursement::TYPES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', Rule::in(Disbursement::PAYMENT_METHODS)],
            'vendor' => ['nullable', 'string', 'max:150'],
            'reference' => ['nullable', 'string', 'max:100'],
            'related_po_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'description' => ['nullable', 'string'],
        ]);

        $d = Disbursement::create($data + [
            'branch_id' => $branchId,
            'disbursement_no' => $this->disbursements->nextNumber($branchId),
            'payment_method' => $data['payment_method'] ?? 'transfer',
            'requested_by' => $request->user()->id,
            'status' => 'draft',
        ]);

        return response()->json(['data' => $d], 201);
    }

    public function disbursementApprove(Request $request, Disbursement $disbursement): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($disbursement->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }

        return response()->json(['data' => $this->disbursements->approve($disbursement, $request->user())]);
    }

    public function disbursementPay(Request $request, Disbursement $disbursement): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($disbursement->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:100']]);

        return response()->json(['data' => $this->disbursements->pay($disbursement, $data['reference'] ?? null, $request->user())]);
    }

    // ───── Tax Invoices ─────

    public function taxIndex(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $rows = TaxInvoice::query()
            ->where('branch_id', $branchId)
            ->with(['invoice:id,invoice_number,total_amount', 'issuer:id,name'])
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function taxIssue(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'customer_name' => ['required', 'string', 'max:200'],
            'customer_tax_id' => ['nullable', 'string', 'max:30'],
            'customer_address' => ['nullable', 'string'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:30'],
        ]);
        $invoice = Invoice::query()->where('id', $data['invoice_id'])->where('branch_id', $branchId)->firstOrFail();

        $tax = $this->taxInvoices->issue(
            $invoice,
            $data['customer_name'],
            $data['customer_tax_id'] ?? null,
            $data['customer_address'] ?? null,
            $data['vat_rate'] ?? 7.0,
            $request->user(),
        );

        return response()->json(['data' => $tax], 201);
    }

    public function taxVoid(Request $request, TaxInvoice $taxInvoice): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($taxInvoice->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return response()->json(['data' => $this->taxInvoices->void($taxInvoice, $data['reason'], $request->user())]);
    }

    // ───── Reports ─────

    public function ledger(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
        ]);

        return response()->json([
            'data' => $this->reports->generalLedger($branchId, (int) $data['account_id'], $data['from'], $data['to']),
        ]);
    }

    public function trialBalance(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $asOf = $request->query('as_of', now()->toDateString());

        return response()->json([
            'data' => $this->reports->trialBalance($branchId, $asOf),
        ]);
    }

    public function cashFlow(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->endOfMonth()->toDateString());

        return response()->json([
            'data' => $this->reports->cashFlow($branchId, $from, $to),
        ]);
    }

    public function taxSummary(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $month = $request->query('month', now()->format('Y-m'));

        return response()->json([
            'data' => $this->reports->taxSummary($branchId, $month),
        ]);
    }
}
