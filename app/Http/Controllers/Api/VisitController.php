<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\VisitResource;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Product;
use App\Models\Visit;
use App\Services\CheckoutService;
use App\Services\InvoiceNumberGenerator;
use App\Services\VisitNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VisitController extends Controller
{
    public function __construct(
        private VisitNumberGenerator $visitNumbers,
        private InvoiceNumberGenerator $invoiceNumbers,
        private CheckoutService $checkout,
    ) {}

    public function todayActive(): JsonResponse
    {
        $rows = Visit::query()
            ->with(['patient:id,uuid,hn,first_name,last_name,phone', 'doctor:id,uuid,name', 'room:id,name'])
            ->whereDate('visit_date', now()->toDateString())
            ->whereNotIn('status', ['cancelled'])
            ->orderByDesc('check_in_at')
            ->get();

        return response()->json(['data' => VisitResource::collection($rows)]);
    }

    public function open(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_uuid' => ['required', Rule::exists('patients', 'uuid')->where('branch_id', app('branch.id'))],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')->where('branch_id', app('branch.id'))],
            'appointment_id' => ['nullable', 'integer', Rule::exists('appointments', 'id')->where('branch_id', app('branch.id'))],
            'chief_complaint' => ['nullable', 'string'],
            'source' => ['nullable', Rule::in(['walk_in', 'appointment'])],
        ]);

        $patient = Patient::query()->where('uuid', $data['patient_uuid'])->firstOrFail();

        $visit = Visit::create([
            'branch_id' => app('branch.id'),
            'patient_id' => $patient->id,
            'doctor_id' => $data['doctor_id'] ?? null,
            'room_id' => $data['room_id'] ?? null,
            'appointment_id' => $data['appointment_id'] ?? null,
            'visit_number' => $this->visitNumbers->next(),
            'visit_date' => now()->toDateString(),
            'check_in_at' => now(),
            'status' => 'in_progress',
            'source' => $data['source'] ?? 'walk_in',
            'chief_complaint' => $data['chief_complaint'] ?? null,
        ]);

        // Create empty draft invoice
        Invoice::create([
            'branch_id' => $visit->branch_id,
            'visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'invoice_number' => $this->invoiceNumbers->next(),
            'invoice_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        return response()->json(['data' => new VisitResource($visit->fresh(['patient', 'doctor', 'room', 'invoice.items']))], 201);
    }

    public function show(Visit $visit): JsonResponse
    {
        $visit->load(['patient', 'doctor', 'room', 'invoice.items', 'invoice.payments']);

        return response()->json(['data' => new VisitResource($visit)]);
    }

    public function updateVitalSigns(Request $request, Visit $visit): JsonResponse
    {
        $data = $request->validate([
            'vital_signs' => ['required', 'array'],
            'vital_signs.bp' => ['nullable', 'string'],
            'vital_signs.pulse' => ['nullable', 'numeric'],
            'vital_signs.weight' => ['nullable', 'numeric'],
            'vital_signs.height' => ['nullable', 'numeric'],
            'vital_signs.temp' => ['nullable', 'numeric'],
        ]);

        // Auto compute BMI if weight + height
        $vs = $data['vital_signs'];
        if (! empty($vs['weight']) && ! empty($vs['height'])) {
            $h = (float) $vs['height'] / 100;
            if ($h > 0) {
                $vs['bmi'] = round(((float) $vs['weight']) / ($h * $h), 2);
            }
        }

        $visit->vital_signs = $vs;
        $visit->save();

        return response()->json(['data' => new VisitResource($visit->fresh())]);
    }

    public function addItem(Request $request, Visit $visit): JsonResponse
    {
        $data = $request->validate([
            'item_type' => ['required', Rule::in(InvoiceItem::TYPES)],
            'item_id' => ['required', 'integer'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $invoice = $visit->invoice;
        if (! $invoice) {
            return response()->json(['message' => 'Visit has no draft invoice'], 409);
        }
        if ($invoice->status !== 'draft') {
            return response()->json(['message' => 'Cannot add items to non-draft invoice'], 409);
        }

        $itemName = '';
        $unitPrice = 0;
        $costPrice = 0;
        if ($data['item_type'] === 'procedure') {
            $proc = Procedure::query()->where('id', $data['item_id'])->where('branch_id', $visit->branch_id)->firstOrFail();
            $itemName = $proc->name;
            $unitPrice = (float) $proc->price;
            $costPrice = (float) $proc->cost;
        } elseif ($data['item_type'] === 'product') {
            $product = Product::query()
                ->where('id', $data['item_id'])
                ->where('branch_id', $visit->branch_id)
                ->firstOrFail();
            $itemName = $product->name;
            $unitPrice = (float) $request->input('unit_price', $product->selling_price);
            $costPrice = (float) $product->cost_price;
        } elseif ($data['item_type'] === 'course') {
            $courseId = $data['course_id'] ?? $data['item_id'];
            $course = Course::query()
                ->where('id', $courseId)
                ->where('branch_id', $visit->branch_id)
                ->where('patient_id', $visit->patient_id)
                ->firstOrFail();
            $itemName = 'Course: '.$course->name.' (session)';
            $unitPrice = 0.0;
            $costPrice = 0.0;
            $data['course_id'] = $course->id;
            $data['item_id'] = $course->id;
        } else {
            $itemName = $request->input('item_name', 'Item #'.$data['item_id']);
            $unitPrice = (float) $request->input('unit_price', 0);
        }

        $discount = (float) ($data['discount'] ?? 0);
        $total = ($unitPrice * (int) $data['quantity']) - $discount;
        if ($total < 0) {
            $total = 0;
        }

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_type' => $data['item_type'],
            'item_id' => $data['item_id'],
            'course_id' => $data['course_id'] ?? null,
            'item_name' => $itemName,
            'quantity' => $data['quantity'],
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'total' => $total,
            'cost_price' => $costPrice,
            'doctor_id' => $data['doctor_id'] ?? null,
            'staff_id' => $data['staff_id'] ?? null,
        ]);

        // Update invoice subtotal/total preview
        $invoice->subtotal = $invoice->items()->sum('total');
        $invoice->total_amount = (float) $invoice->subtotal - (float) $invoice->discount_amount + (float) $invoice->vat_amount;
        $invoice->save();

        return response()->json(['data' => $item], 201);
    }

    public function removeItem(Request $request, Visit $visit, int $itemId): JsonResponse
    {
        $invoice = $visit->invoice;
        if (! $invoice || $invoice->status !== 'draft') {
            return response()->json(['message' => 'Invoice not editable'], 409);
        }

        $item = InvoiceItem::query()->where('invoice_id', $invoice->id)->where('id', $itemId)->firstOrFail();
        $item->delete();

        $invoice->subtotal = $invoice->items()->sum('total');
        $invoice->total_amount = (float) $invoice->subtotal - (float) $invoice->discount_amount + (float) $invoice->vat_amount;
        $invoice->save();

        return response()->json(null, 204);
    }

    public function checkoutAction(Request $request, Visit $visit): JsonResponse
    {
        $data = $request->validate([
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', Rule::in(['cash', 'credit_card', 'transfer', 'member_credit', 'coupon'])],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'payments.*.reference_no' => ['nullable', 'string', 'max:50'],
            'coupon_code' => ['nullable', 'string', 'max:32'],
            'promotion_id' => ['nullable', 'integer', 'exists:promotions,id'],
        ]);

        $marketing = [
            'coupon_code' => $data['coupon_code'] ?? null,
            'promotion_id' => $data['promotion_id'] ?? null,
        ];
        $invoice = $this->checkout->checkout($visit, $data['payments'], $request->user(), $marketing);

        return response()->json([
            'data' => [
                'invoice_id' => $invoice->uuid,
                'invoice_number' => $invoice->invoice_number,
                'total_amount' => (float) $invoice->total_amount,
                'status' => $invoice->status,
            ],
        ]);
    }
}
