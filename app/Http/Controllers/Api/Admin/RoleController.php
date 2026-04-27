<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions:id,name,module,display_name')->get();
        $perms = Permission::orderBy('module')->orderBy('name')->get(['id', 'name', 'display_name', 'module']);

        return response()->json([
            'data' => [
                'roles' => $roles,
                'permissions' => $perms,
            ],
        ]);
    }

    public function syncPermissions(Request $request, int $role): JsonResponse
    {
        $data = $request->validate([
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        /** @var Role $r */
        $r = Role::findOrFail($role);
        $r->permissions()->sync($data['permission_ids'] ?? []);

        return response()->json([
            'data' => [
                'role_id' => $r->id,
                'permission_ids' => $r->permissions()->pluck('permissions.id'),
            ],
        ]);
    }
}
