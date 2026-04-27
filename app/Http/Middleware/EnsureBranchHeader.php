<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $branchId = $request->header('X-Branch-Id');

        if (! is_numeric($branchId) || (int) $branchId <= 0) {
            return response()->json([
                'message' => 'X-Branch-Id header required',
                'code' => 'branch_header_missing',
            ], 400);
        }

        $branchId = (int) $branchId;

        $user = $request->user();
        if ($user) {
            $allowed = $user->branches()->where('branches.id', $branchId)->exists();
            if (! $allowed) {
                return response()->json([
                    'message' => 'Not authorized for this branch',
                    'code' => 'branch_not_authorized',
                ], 403);
            }
        }

        app()->instance('branch.id', $branchId);

        return $next($request);
    }
}
