<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;

class DevController extends Controller
{
    /**
     * List quick-login accounts (development helper).
     * Disabled outside local environment.
     */
    public function quickAccounts(): JsonResponse
    {
        if (! App::environment('local')) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $users = User::query()
            ->with(['roles:id,name,display_name', 'branches:id,name,code'])
            ->whereIn('email', [
                'super@debut-clinic.local',
                'branch@debut-clinic.local',
                'doctor@debut-clinic.local',
                'nurse@debut-clinic.local',
                'reception@debut-clinic.local',
                'pharmacist@debut-clinic.local',
                'accountant@debut-clinic.local',
                'marketing@debut-clinic.local',
            ])
            ->get(['id', 'name', 'email']);

        $data = $users->map(fn (User $u) => [
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->roles->first()?->display_name,
            'role_key' => $u->roles->first()?->name,
            'branch' => $u->branches->first()?->name,
            'password' => 'password', // dev only
        ])->values();

        return response()->json(['data' => $data]);
    }
}
