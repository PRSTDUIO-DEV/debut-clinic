<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompensationRule;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $q = User::query()
            ->whereHas('branches', fn ($q) => $q->where('branches.id', $branchId))
            ->with(['roles:id,name,display_name', 'employeeProfile']);
        if ($request->filled('role')) {
            $q->whereHas('roles', fn ($qq) => $qq->where('roles.name', $request->role));
        }
        if ($request->filled('search')) {
            $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->search.'%')
                ->orWhere('email', 'like', '%'.$request->search.'%')
                ->orWhere('employee_code', 'like', '%'.$request->search.'%'));
        }
        if ($request->filled('active')) {
            $q->where('is_active', $request->boolean('active'));
        }

        return response()->json(['data' => $q->orderBy('name')->paginate(50)]);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['roles:id,name,display_name', 'branches:id,name', 'employeeProfile', 'compensationRules']);

        return response()->json(['data' => $user]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'employee_code' => ['nullable', 'string', 'max:32', 'unique:users,employee_code'],
            'password' => ['required', 'string', 'min:8'],
            'pin' => ['nullable', 'string', 'min:4', 'max:6'],
            'phone' => ['nullable', 'string', 'max:32'],
            'position' => ['nullable', 'string'],
            'is_doctor' => ['nullable', 'boolean'],
            'license_no' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
        ]);
        $branchId = (int) app('branch.id');

        $user = User::create([
            'branch_id' => $branchId,
            'name' => $data['name'],
            'email' => $data['email'],
            'employee_code' => $data['employee_code'] ?? null,
            'password' => Hash::make($data['password']),
            'pin_hash' => ! empty($data['pin']) ? Hash::make($data['pin']) : null,
            'phone' => $data['phone'] ?? null,
            'position' => $data['position'] ?? null,
            'is_doctor' => $data['is_doctor'] ?? false,
            'license_no' => $data['license_no'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $branchIds = $data['branch_ids'] ?? [$branchId];
        $attach = [];
        foreach ($branchIds as $i => $bid) {
            $attach[$bid] = ['is_primary' => $i === 0];
        }
        $user->branches()->sync($attach);

        if (! empty($data['role_ids'])) {
            $user->roles()->sync($data['role_ids']);
        }

        return response()->json(['data' => $user->load(['roles', 'branches', 'employeeProfile'])], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'employee_code' => ['nullable', 'string', 'max:32', Rule::unique('users', 'employee_code')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'pin' => ['nullable', 'string', 'min:4', 'max:6'],
            'phone' => ['nullable', 'string', 'max:32'],
            'position' => ['nullable', 'string'],
            'is_doctor' => ['nullable', 'boolean'],
            'license_no' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        if (! empty($data['pin'])) {
            $data['pin_hash'] = Hash::make($data['pin']);
        }
        unset($data['pin']);
        $user->fill($data)->save();

        return response()->json(['data' => $user->fresh()]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->is_active = false;
        $user->save();

        return response()->json(null, 204);
    }

    public function updateProfile(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'employee_no' => ['nullable', 'string', 'max:32', Rule::unique('employee_profiles', 'employee_no')->ignore(optional($user->employeeProfile)->id)],
            'position' => ['nullable', 'string'],
            'department' => ['nullable', 'string'],
            'hire_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date'],
            'bank_name' => ['nullable', 'string'],
            'bank_account' => ['nullable', 'string'],
            'emergency_contact' => ['nullable', 'string'],
            'emergency_phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'national_id' => ['nullable', 'string'],
        ]);

        $profile = EmployeeProfile::updateOrCreate(
            ['user_id' => $user->id],
            $data,
        );

        return response()->json(['data' => $profile]);
    }

    public function assignRoles(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role_ids' => ['required', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);
        $user->roles()->sync($data['role_ids']);

        return response()->json(['data' => $user->load('roles')]);
    }

    public function assignBranches(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'primary' => ['nullable', 'integer'],
        ]);
        $primary = $data['primary'] ?? $data['branch_ids'][0];
        $attach = [];
        foreach ($data['branch_ids'] as $bid) {
            $attach[$bid] = ['is_primary' => $bid === $primary];
        }
        $user->branches()->sync($attach);

        return response()->json(['data' => $user->load('branches')]);
    }

    public function storeCompensationRule(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:monthly,hourly,daily,per_procedure,commission'],
            'base_amount' => ['required', 'numeric', 'min:0'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'applicable_procedure_id' => ['nullable', 'integer'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $branchId = (int) app('branch.id');

        $rule = CompensationRule::create(array_merge($data, [
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'is_active' => $data['is_active'] ?? true,
        ]));

        return response()->json(['data' => $rule], 201);
    }

    public function destroyCompensationRule(User $user, CompensationRule $rule): JsonResponse
    {
        if ($rule->user_id !== $user->id) {
            return response()->json(['message' => 'not found'], 404);
        }
        $rule->delete();

        return response()->json(null, 204);
    }
}
