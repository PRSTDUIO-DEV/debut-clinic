<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FollowUpRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FollowUpRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $rows = FollowUpRule::query()
            ->where('branch_id', $branchId)
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'priority' => ['required', Rule::in(FollowUpRule::PRIORITIES)],
            'condition_type' => ['required', Rule::in(FollowUpRule::CONDITIONS)],
            'condition_value' => ['required', 'array'],
            'notify_doctor' => ['nullable', 'boolean'],
            'notify_branch_admin' => ['nullable', 'boolean'],
            'preferred_channel' => ['nullable', Rule::in(['in_app', 'line', 'email'])],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['branch_id'] = $branchId;
        $data['notify_doctor'] ??= false;
        $data['notify_branch_admin'] ??= true;
        $data['preferred_channel'] ??= 'in_app';
        $data['is_active'] ??= true;

        return response()->json(['data' => FollowUpRule::create($data)], 201);
    }

    public function update(Request $request, FollowUpRule $rule): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($rule->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'priority' => ['sometimes', Rule::in(FollowUpRule::PRIORITIES)],
            'condition_type' => ['sometimes', Rule::in(FollowUpRule::CONDITIONS)],
            'condition_value' => ['sometimes', 'array'],
            'notify_doctor' => ['nullable', 'boolean'],
            'notify_branch_admin' => ['nullable', 'boolean'],
            'preferred_channel' => ['nullable', Rule::in(['in_app', 'line', 'email'])],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $rule->fill($data)->save();

        return response()->json(['data' => $rule]);
    }

    public function destroy(FollowUpRule $rule): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($rule->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $rule->delete();

        return response()->json(null, 204);
    }
}
