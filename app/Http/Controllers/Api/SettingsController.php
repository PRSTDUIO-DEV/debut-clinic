<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\CustomerGroup;
use App\Models\ExpenseCategory;
use App\Models\Procedure;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Room;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Centralised CRUD endpoints for admin settings entities.
 * All branch-scoped models inherit BranchScope, so list/show/store auto-filter.
 * Branches itself is super_admin only and not branch-scoped.
 */
class SettingsController extends Controller
{
    // ───── Branches (super_admin) ─────

    public function branchesIndex(): JsonResponse
    {
        return response()->json(['data' => Branch::orderBy('name')->paginate(50)]);
    }

    public function branchesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'unique:branches,code'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return response()->json(['data' => Branch::create($data + ['is_active' => $data['is_active'] ?? true])], 201);
    }

    public function branchesUpdate(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:32', Rule::unique('branches', 'code')->ignore($branch->id)],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $branch->fill($data)->save();

        return response()->json(['data' => $branch->fresh()]);
    }

    public function branchesDestroy(Branch $branch): JsonResponse
    {
        $branch->is_active = false;
        $branch->save();

        return response()->json(null, 204);
    }

    // ───── Rooms ─────

    public function roomsIndex(): JsonResponse
    {
        return response()->json(['data' => Room::orderBy('position')->orderBy('name')->paginate(100)]);
    }

    public function roomsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:64'],
            'floor' => ['nullable', 'string', 'max:32'],
            'is_active' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer'],
        ]);

        return response()->json(['data' => Room::create($data + ['is_active' => $data['is_active'] ?? true])], 201);
    }

    public function roomsUpdate(Request $request, Room $room): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:64'],
            'floor' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer'],
        ]);
        $room->fill($data)->save();

        return response()->json(['data' => $room->fresh()]);
    }

    public function roomsDestroy(Room $room): JsonResponse
    {
        return $this->softDelete($room);
    }

    // ───── Banks ─────

    public function banksIndex(): JsonResponse
    {
        return response()->json(['data' => Bank::orderBy('position')->orderBy('name')->paginate(100)]);
    }

    public function banksStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_no' => ['nullable', 'string', 'max:64'],
            'mdr_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer'],
        ]);

        return response()->json(['data' => Bank::create($data + ['is_active' => $data['is_active'] ?? true])], 201);
    }

    public function banksUpdate(Request $request, Bank $bank): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'account_no' => ['nullable', 'string', 'max:64'],
            'mdr_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer'],
        ]);
        $bank->fill($data)->save();

        return response()->json(['data' => $bank->fresh()]);
    }

    public function banksDestroy(Bank $bank): JsonResponse
    {
        return $this->softDelete($bank);
    }

    // ───── Customer Groups ─────

    public function customerGroupsIndex(): JsonResponse
    {
        return response()->json(['data' => CustomerGroup::orderBy('position')->orderBy('name')->paginate(100)]);
    }

    public function customerGroupsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer'],
        ]);

        return response()->json(['data' => CustomerGroup::create($data + ['is_active' => $data['is_active'] ?? true])], 201);
    }

    public function customerGroupsUpdate(Request $request, CustomerGroup $customerGroup): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer'],
        ]);
        $customerGroup->fill($data)->save();

        return response()->json(['data' => $customerGroup->fresh()]);
    }

    public function customerGroupsDestroy(CustomerGroup $customerGroup): JsonResponse
    {
        return $this->softDelete($customerGroup);
    }

    // ───── Suppliers ─────

    public function suppliersIndex(): JsonResponse
    {
        return response()->json(['data' => Supplier::orderBy('position')->orderBy('name')->paginate(100)]);
    }

    public function suppliersStore(Request $request): JsonResponse
    {
        $data = $this->validateSupplier($request);

        return response()->json(['data' => Supplier::create($data + ['is_active' => $data['is_active'] ?? true])], 201);
    }

    public function suppliersUpdate(Request $request, Supplier $supplier): JsonResponse
    {
        $data = $this->validateSupplier($request, true);
        $supplier->fill($data)->save();

        return response()->json(['data' => $supplier->fresh()]);
    }

    public function suppliersDestroy(Supplier $supplier): JsonResponse
    {
        return $this->softDelete($supplier);
    }

    private function validateSupplier(Request $request, bool $update = false): array
    {
        $rule = $update ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$rule, 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'payment_terms' => ['nullable', 'string', 'max:64'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer'],
        ]);
    }

    // ───── Procedures ─────

    public function proceduresIndex(): JsonResponse
    {
        return response()->json(['data' => Procedure::orderBy('position')->orderBy('name')->paginate(100)]);
    }

    public function proceduresStore(Request $request): JsonResponse
    {
        $data = $this->validateProcedure($request);

        return response()->json(['data' => Procedure::create($data + ['is_active' => $data['is_active'] ?? true])], 201);
    }

    public function proceduresUpdate(Request $request, Procedure $procedure): JsonResponse
    {
        $data = $this->validateProcedure($request, true, $procedure);
        $procedure->fill($data)->save();

        return response()->json(['data' => $procedure->fresh()]);
    }

    public function proceduresDestroy(Procedure $procedure): JsonResponse
    {
        return $this->softDelete($procedure);
    }

    private function validateProcedure(Request $request, bool $update = false, ?Procedure $existing = null): array
    {
        $rule = $update ? 'sometimes' : 'required';
        $codeUnique = Rule::unique('procedures', 'code');
        if ($existing) {
            $codeUnique = $codeUnique->ignore($existing->id);
        }

        return $request->validate([
            'code' => [$rule, 'string', 'max:32', $codeUnique],
            'name' => [$rule, 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:128'],
            'price' => [$rule, 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'doctor_fee_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'staff_commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'follow_up_days' => ['nullable', 'integer', 'min:0'],
            'is_package' => ['nullable', 'boolean'],
            'package_sessions' => ['nullable', 'integer', 'min:0'],
            'package_validity_days' => ['nullable', 'integer', 'min:0'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer'],
        ]);
    }

    // ───── Products ─────

    public function productsIndex(Request $request): JsonResponse
    {
        $q = Product::with('category:id,name');
        if ($request->filled('search')) {
            $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->search.'%')->orWhere('sku', 'like', $request->search.'%'));
        }
        if ($request->filled('category_id')) {
            $q->where('category_id', (int) $request->category_id);
        }

        return response()->json(['data' => $q->orderBy('name')->paginate(100)]);
    }

    public function productsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'unit' => ['nullable', 'string', 'max:32'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'max_stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json(['data' => Product::create($data + ['is_active' => $data['is_active'] ?? true])], 201);
    }

    public function productsUpdate(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'sku' => ['sometimes', 'string', 'max:32'],
            'name' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'unit' => ['nullable', 'string', 'max:32'],
            'selling_price' => ['sometimes', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'max_stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $product->fill($data)->save();

        return response()->json(['data' => $product->fresh()]);
    }

    public function productsDestroy(Product $product): JsonResponse
    {
        return $this->softDelete($product);
    }

    // ───── Product Categories ─────

    public function productCategoriesIndex(): JsonResponse
    {
        return response()->json(['data' => ProductCategory::with('parent:id,name')->orderBy('position')->orderBy('name')->paginate(100)]);
    }

    public function productCategoriesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer'],
        ]);

        return response()->json(['data' => ProductCategory::create($data + ['is_active' => $data['is_active'] ?? true])], 201);
    }

    public function productCategoriesUpdate(Request $request, ProductCategory $productCategory): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer'],
        ]);
        $productCategory->fill($data)->save();

        return response()->json(['data' => $productCategory->fresh()]);
    }

    public function productCategoriesDestroy(ProductCategory $productCategory): JsonResponse
    {
        return $this->softDelete($productCategory);
    }

    // ───── Expense Categories ─────

    public function expenseCategoriesIndex(): JsonResponse
    {
        return response()->json(['data' => ExpenseCategory::orderBy('position')->orderBy('name')->paginate(100)]);
    }

    public function expenseCategoriesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer'],
        ]);

        return response()->json(['data' => ExpenseCategory::create($data + ['is_active' => $data['is_active'] ?? true])], 201);
    }

    public function expenseCategoriesUpdate(Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer'],
        ]);
        $expenseCategory->fill($data)->save();

        return response()->json(['data' => $expenseCategory->fresh()]);
    }

    public function expenseCategoriesDestroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        return $this->softDelete($expenseCategory);
    }

    private function softDelete(Model $model): JsonResponse
    {
        $model->delete();

        return response()->json(null, 204);
    }

    // ───── Reorder helper (drag-and-drop) ─────

    public function reorder(Request $request, string $entity): JsonResponse
    {
        $map = [
            'rooms' => Room::class,
            'banks' => Bank::class,
            'customer-groups' => CustomerGroup::class,
            'suppliers' => Supplier::class,
            'procedures' => Procedure::class,
            'product-categories' => ProductCategory::class,
            'expense-categories' => ExpenseCategory::class,
        ];
        if (! isset($map[$entity])) {
            return response()->json(['message' => 'unknown entity'], 404);
        }
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ]);
        $modelClass = $map[$entity];
        foreach ($data['order'] as $position => $id) {
            $modelClass::where('id', (int) $id)->update(['position' => $position]);
        }

        return response()->json(['data' => ['updated' => count($data['order'])]]);
    }
}
