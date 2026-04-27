<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Patient\StorePatientRequest;
use App\Http\Requests\Api\Patient\UpdatePatientRequest;
use App\Http\Resources\Api\PatientResource;
use App\Models\Branch;
use App\Models\Patient;
use App\Services\HnGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    public function __construct(private HnGenerator $hnGenerator) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) min(100, max(1, $request->integer('per_page', 20)));
        $search = trim((string) $request->query('search', ''));

        $query = Patient::query()->with('customerGroup');

        if ($search !== '') {
            // Use Scout-driven search for full-text + relevance
            $ids = Patient::search($search)
                ->where('branch_id', (int) app('branch.id'))
                ->take(200)
                ->keys();
            $query->whereIn('id', $ids);
        }

        if ($gender = $request->query('filter.gender')) {
            $query->where('gender', $gender);
        }

        if ($groupId = $request->query('filter.customer_group_id')) {
            $query->where('customer_group_id', $groupId);
        }

        $sort = (string) $request->query('sort', '-last_visit_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $sortField = ltrim($sort, '-');
        $allowedSorts = ['last_visit_at', 'name', 'total_spent', 'created_at'];
        if (! in_array($sortField, $allowedSorts, true)) {
            $sortField = 'last_visit_at';
        }
        if ($sortField === 'name') {
            $query->orderBy('first_name', $direction)->orderBy('last_name', $direction);
        } else {
            $query->orderBy($sortField, $direction);
        }

        return PatientResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StorePatientRequest $request): JsonResponse
    {
        /** @var Branch $branch */
        $branch = Branch::query()->findOrFail(app('branch.id'));

        $patient = DB::transaction(function () use ($branch, $request) {
            $hn = $this->hnGenerator->nextFor($branch);
            $data = $request->validated();
            $data['branch_id'] = $branch->id;
            $data['hn'] = $hn;

            return Patient::create($data);
        });

        $patient->load('customerGroup');

        return response()->json(['data' => new PatientResource($patient)], 201);
    }

    public function show(Patient $patient): JsonResponse
    {
        $patient->load('customerGroup');

        return response()->json(['data' => new PatientResource($patient)]);
    }

    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        $patient->update($request->validated());
        $patient->load('customerGroup');

        return response()->json(['data' => new PatientResource($patient)]);
    }

    public function destroy(Patient $patient): JsonResponse
    {
        $patient->delete();

        return response()->json(null, 204);
    }
}
