<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\ReportExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __construct(private ReportExporter $exporter) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) min(200, max(1, $request->integer('per_page', 50)));
        $query = $this->buildQuery($request);
        $page = $query->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $log = AuditLog::with('user:id,name,email')
            ->where('branch_id', $branchId)
            ->findOrFail($id);

        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];

        $diff = [];
        $keys = array_unique(array_merge(array_keys((array) $old), array_keys((array) $new)));
        foreach ($keys as $k) {
            $a = $old[$k] ?? null;
            $b = $new[$k] ?? null;
            if ($a !== $b) {
                $diff[$k] = ['before' => $a, 'after' => $b];
            }
        }

        return response()->json(['data' => [
            'log' => $log,
            'diff' => $diff,
            'changed_fields' => array_keys($diff),
        ]]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->buildQuery($request)->limit(10000)->get()->map(fn ($l) => [
            'id' => $l->id,
            'created_at' => (string) $l->created_at,
            'user' => $l->user?->name ?? '',
            'action' => $l->action,
            'auditable_type' => $l->auditable_type,
            'auditable_id' => $l->auditable_id,
            'ip_address' => $l->ip_address,
            'changed_fields' => implode(',', array_keys(array_diff_assoc((array) ($l->new_values ?? []), (array) ($l->old_values ?? [])))),
        ])->all();

        return $this->exporter->csv($rows, [
            'id', 'created_at', 'user', 'action', 'auditable_type', 'auditable_id', 'ip_address', 'changed_fields',
        ], 'audit-logs-'.now()->format('Ymd-His').'.csv');
    }

    private function buildQuery(Request $request)
    {
        $branchId = (int) app('branch.id');
        $q = AuditLog::query()
            ->where('branch_id', $branchId)
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        if ($v = $request->query('user_id')) {
            $q->where('user_id', (int) $v);
        }
        if ($v = $request->query('action') ?? $request->query('filter.action')) {
            $q->where('action', $v);
        }
        if ($v = $request->query('auditable_type') ?? $request->query('filter.auditable_type')) {
            $q->where('auditable_type', $v);
        }
        if ($v = $request->query('auditable_id')) {
            $q->where('auditable_id', (int) $v);
        }
        if ($v = $request->query('date_from')) {
            $q->whereDate('created_at', '>=', $v);
        }
        if ($v = $request->query('date_to')) {
            $q->whereDate('created_at', '<=', $v);
        }
        if ($v = $request->query('search')) {
            $q->where(fn ($w) => $w->where('action', 'like', '%'.$v.'%')->orWhere('auditable_type', 'like', '%'.$v.'%'));
        }

        return $q;
    }
}
