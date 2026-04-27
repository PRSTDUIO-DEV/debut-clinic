<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LineRichMenu;
use App\Services\Marketing\RichMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RichMenuController extends Controller
{
    public function __construct(private RichMenuService $service) {}

    public function index(): JsonResponse
    {
        $branchId = (int) app('branch.id');

        return response()->json(['data' => LineRichMenu::where('branch_id', $branchId)->orderByDesc('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'layout' => ['required', 'in:compact_4,compact_6,full_4,full_6,full_12'],
            'buttons' => ['required', 'array'],
            'provider_id' => ['nullable', 'integer'],
        ]);
        $this->service->validateButtons($data['layout'], $data['buttons']);
        $branchId = (int) app('branch.id');
        $menu = LineRichMenu::create(array_merge($data, ['branch_id' => $branchId, 'is_active' => false]));

        return response()->json(['data' => $menu], 201);
    }

    public function sync(LineRichMenu $richMenu): JsonResponse
    {
        $r = $this->service->syncToLine($richMenu);
        $this->service->activate($richMenu->fresh());

        return response()->json(['data' => array_merge(['menu' => $richMenu->fresh()], $r)]);
    }

    public function destroy(LineRichMenu $richMenu): JsonResponse
    {
        $richMenu->delete();

        return response()->json(null, 204);
    }
}
