<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function __construct(private PermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'unauthenticated',
            ], 401);
        }

        if (! $this->permissions->userHas($user, $permission)) {
            return response()->json([
                'message' => 'Insufficient permissions',
                'code' => 'forbidden',
                'required' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
