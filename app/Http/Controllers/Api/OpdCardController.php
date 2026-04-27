<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabOrder;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpdCardController extends Controller
{
    public function visits(Request $request, Patient $patient): JsonResponse
    {
        $perPage = (int) min(100, max(1, $request->integer('per_page', 20)));
        $page = $patient->visits()
            ->with(['doctor:id,uuid,name', 'invoice:id,visit_id,invoice_number,total_amount,status'])
            ->orderByDesc('visit_date')
            ->orderByDesc('check_in_at')
            ->paginate($perPage);

        $data = collect($page->items())->map(fn ($v) => [
            'id' => $v->uuid,
            'visit_number' => $v->visit_number,
            'visit_date' => optional($v->visit_date)->toDateString(),
            'check_in_at' => optional($v->check_in_at)->toIso8601String(),
            'check_out_at' => optional($v->check_out_at)->toIso8601String(),
            'status' => $v->status,
            'doctor' => $v->doctor ? ['id' => $v->doctor->uuid, 'name' => $v->doctor->name] : null,
            'total_amount' => (float) $v->total_amount,
            'invoice_number' => $v->invoice?->invoice_number,
            'invoice_status' => $v->invoice?->status,
        ]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function photos(Patient $patient): JsonResponse
    {
        $rows = $patient->photos()
            ->orderByDesc('taken_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($p) => [
                'id' => $p->id,
                'visit_id' => $p->visit_id,
                'type' => $p->type,
                'file_path' => $p->file_path,
                'thumbnail_path' => $p->thumbnail_path,
                'url' => $p->url(),
                'thumbnail_url' => $p->thumbnailUrl(),
                'width' => $p->width,
                'height' => $p->height,
                'taken_at' => optional($p->taken_at)->toIso8601String(),
                'notes' => $p->notes,
            ]),
        ]);
    }

    public function consents(Patient $patient): JsonResponse
    {
        $rows = $patient->consents()->with('template:id,code,title')->orderByDesc('id')->get();

        return response()->json([
            'data' => $rows->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'template' => $c->template ? ['id' => $c->template->id, 'code' => $c->template->code, 'title' => $c->template->title] : null,
                'file_path' => $c->file_path,
                'file_url' => $c->fileUrl(),
                'signature_url' => $c->signatureUrl(),
                'signed_by_name' => $c->signed_by_name,
                'signed_at' => optional($c->signed_at)->toIso8601String(),
                'expires_at' => optional($c->expires_at)->toDateString(),
                'status' => $c->status,
                'notes' => $c->notes,
            ]),
        ]);
    }

    public function courses(Patient $patient): JsonResponse
    {
        $rows = $patient->courses()->orderByDesc('id')->get(['id', 'name', 'total_sessions', 'used_sessions', 'remaining_sessions', 'expires_at', 'status']);

        return response()->json(['data' => $rows]);
    }

    public function financial(Patient $patient): JsonResponse
    {
        $invoices = $patient->invoices()
            ->orderByDesc('invoice_date')
            ->limit(50)
            ->get(['id', 'uuid', 'invoice_number', 'invoice_date', 'subtotal', 'discount_amount', 'total_amount', 'status']);

        return response()->json([
            'data' => [
                'total_spent' => (float) $patient->total_spent,
                'visit_count' => (int) $patient->visit_count,
                'last_visit_at' => optional($patient->last_visit_at)->toIso8601String(),
                'member_account' => $patient->memberAccount ? [
                    'package_name' => $patient->memberAccount->package_name,
                    'total_deposit' => (float) $patient->memberAccount->total_deposit,
                    'total_used' => (float) $patient->memberAccount->total_used,
                    'balance' => (float) $patient->memberAccount->balance,
                    'expires_at' => optional($patient->memberAccount->expires_at)->toDateString(),
                    'status' => $patient->memberAccount->status,
                ] : null,
                'invoices' => $invoices->map(fn ($i) => [
                    'id' => $i->uuid,
                    'invoice_number' => $i->invoice_number,
                    'invoice_date' => optional($i->invoice_date)->toDateString(),
                    'subtotal' => (float) $i->subtotal,
                    'discount_amount' => (float) $i->discount_amount,
                    'total_amount' => (float) $i->total_amount,
                    'status' => $i->status,
                ]),
            ],
        ]);
    }

    public function labResults(Patient $patient): JsonResponse
    {
        $rows = $patient->relationLoaded('labOrders')
            ? $patient->labOrders
            : LabOrder::query()
                ->where('patient_id', $patient->id)
                ->with(['items.test', 'results.test', 'orderer:id,name'])
                ->orderByDesc('ordered_at')
                ->limit(20)
                ->get();

        return response()->json([
            'data' => $rows->map(function ($o) {
                $byTest = $o->results->keyBy('lab_test_id');

                return [
                    'id' => $o->id,
                    'order_no' => $o->order_no,
                    'status' => $o->status,
                    'ordered_at' => optional($o->ordered_at)->toIso8601String(),
                    'result_date' => optional($o->result_date)->toDateString(),
                    'ordered_by' => $o->orderer?->name,
                    'report_url' => $o->reportUrl(),
                    'rows' => $o->items->map(function ($it) use ($byTest) {
                        $r = $byTest->get($it->lab_test_id);

                        return [
                            'lab_test_id' => $it->lab_test_id,
                            'code' => $it->test?->code,
                            'name' => $it->test?->name,
                            'unit' => $it->test?->unit,
                            'ref_min' => $it->test?->ref_min !== null ? (float) $it->test->ref_min : null,
                            'ref_max' => $it->test?->ref_max !== null ? (float) $it->test->ref_max : null,
                            'ref_text' => $it->test?->ref_text,
                            'value_numeric' => $r?->value_numeric !== null ? (float) $r->value_numeric : null,
                            'value_text' => $r?->value_text,
                            'abnormal_flag' => $r?->abnormal_flag,
                        ];
                    })->values(),
                ];
            }),
        ]);
    }
}
