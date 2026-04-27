<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\SwitchBranchRequest;
use App\Http\Resources\Api\BranchSummaryResource;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var User|null $user */
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('Invalid credentials'),
            ])->status(401);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Account deactivated',
                'code' => 'forbidden',
            ], 403);
        }

        $deviceName = $data['device_name'] ?? $request->userAgent() ?? 'unknown-device';
        $token = $user->createToken($deviceName)->plainTextToken;

        $user->load(['branches', 'roles.permissions:id,name']);

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
                'branches' => BranchSummaryResource::collection($user->branches),
                'permissions' => $user->permissionNames(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['branches', 'roles.permissions:id,name']);

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'branches' => BranchSummaryResource::collection($user->branches),
                'permissions' => $user->permissionNames(),
            ],
        ]);
    }

    public function switchBranch(SwitchBranchRequest $request): JsonResponse
    {
        $user = $request->user();
        $branchId = (int) $request->validated('branch_id');

        $allowed = $user->branches()->where('branches.id', $branchId)->exists();
        if (! $allowed) {
            return response()->json([
                'message' => 'Not authorized for this branch',
                'code' => 'branch_not_authorized',
            ], 403);
        }

        return response()->json([
            'data' => [
                'active_branch_id' => $branchId,
            ],
        ]);
    }
}
