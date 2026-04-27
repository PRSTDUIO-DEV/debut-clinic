<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\Procedure;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class LookupController extends Controller
{
    public function doctors(): JsonResponse
    {
        $doctors = User::query()
            ->where('is_doctor', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'license_no']);

        return response()->json(['data' => $doctors]);
    }

    public function rooms(): JsonResponse
    {
        $rooms = Room::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']);

        return response()->json(['data' => $rooms]);
    }

    public function procedures(): JsonResponse
    {
        $procedures = Procedure::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'duration_minutes', 'price', 'follow_up_days']);

        return response()->json(['data' => $procedures]);
    }

    public function customerGroups(): JsonResponse
    {
        $groups = CustomerGroup::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'discount_rate']);

        return response()->json(['data' => $groups]);
    }
}
