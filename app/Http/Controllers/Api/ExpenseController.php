<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\Accounting\AccountingPoster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function categories(): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $rows = ExpenseCategory::query()
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories')->where(fn ($q) => $q->where('branch_id', $branchId))],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['branch_id'] = $branchId;
        $data['is_active'] ??= true;

        return response()->json(['data' => ExpenseCategory::create($data)], 201);
    }

    public function updateCategory(Request $request, ExpenseCategory $category): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($category->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $category->fill($data)->save();

        return response()->json(['data' => $category]);
    }

    public function destroyCategory(ExpenseCategory $category): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($category->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $category->delete();

        return response()->json(null, 204);
    }

    public function index(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $perPage = (int) min(200, max(1, $request->integer('per_page', 50)));

        $q = Expense::query()
            ->where('branch_id', $branchId)
            ->with(['category:id,name', 'payer:id,name'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        if ($from = $request->query('date_from')) {
            $q->whereDate('expense_date', '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $q->whereDate('expense_date', '<=', $to);
        }
        if ($cat = $request->query('category_id')) {
            $q->where('category_id', $cat);
        }

        $page = $q->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (Expense $e) => [
                'id' => $e->id,
                'expense_date' => $e->expense_date?->toDateString(),
                'category' => $e->category?->name,
                'amount' => (float) $e->amount,
                'payment_method' => $e->payment_method,
                'vendor' => $e->vendor,
                'description' => $e->description,
                'paid_by' => $e->payer?->name,
            ]),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'sum' => (float) $q->getQuery()->cloneWithout([])->sum('amount'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', Rule::in(Expense::PAYMENT_METHODS)],
            'vendor' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $data['branch_id'] = $branchId;
        $data['payment_method'] ??= 'cash';
        $data['paid_by'] = $request->user()->id;

        $expense = Expense::create($data);

        try {
            app(AccountingPoster::class)->postExpense($expense);
        } catch (\Throwable $e) {
            Log::warning('Accounting post failed for expense '.$expense->id.': '.$e->getMessage());
        }

        return response()->json(['data' => $expense], 201);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($expense->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'expense_date' => ['sometimes', 'date'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', Rule::in(Expense::PAYMENT_METHODS)],
            'vendor' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $expense->fill($data)->save();

        return response()->json(['data' => $expense]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($expense->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $expense->delete();

        return response()->json(null, 204);
    }
}
