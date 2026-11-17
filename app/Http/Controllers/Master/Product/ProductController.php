<?php

namespace App\Http\Controllers\Master\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Product\ProductRequest;
use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Department;
use App\Models\Master\Procurement\UnitOfMeasure;
use App\Models\Master\Product\Brand;
use App\Models\Master\Product\Category;
use App\Models\Master\Product\Model as AssetModel;
use App\Models\Master\Product\Product;
use App\Models\Master\Product\Type;
use App\Models\NumberingDocument;
use App\Models\Transaction\Inventory\InventoryReceiptIntegration;
use App\Services\BranchAccessService;
use App\Services\DepartmentAccessService;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request, DepartmentAccessService $departmentAccess): Response
    {
        $search = $request->string('search')->trim()->toString();
        $group = $request->string('group')->trim()->toString();
        $status = $request->string('status', 'ALL')->toString();
        $requestedPerPage = $request->integer('per_page', 15);
        $perPage = in_array($requestedPerPage, [5, 15, 25, 50, 100], true) ? $requestedPerPage : 15;

        if (! in_array($status, ['ALL', 'ACTIVE', 'INACTIVE'], true)) {
            $status = 'ALL';
        }

        $products = $this->accessibleProducts(Product::query(), $request, $departmentAccess)
            ->with(['category', 'type', 'brand', 'model', 'departments:id,name', 'branches:id,name,code'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('group', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            })
            ->when($group !== '', function ($query) use ($group) {
                $query->where('group', $group);
            })
            ->when($status === 'ACTIVE', fn ($query) => $query->where('is_active', true))
            ->when($status === 'INACTIVE', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'Master/Product/Products/Index',
            [
                'products' => $products,
                'filters' => [
                    'search' => $search,
                    'group' => $group,
                    'status' => $status,
                    'per_page' => $perPage,
                ],
            ],
        );
    }

    public function show(Request $request, Product $product, DepartmentAccessService $departmentAccess): Response
    {
        $this->authorizeProductAccess($product, $request, $departmentAccess);
        $product->load(['category', 'type', 'brand', 'model', 'departments:id,name', 'branches:id,name,code']);

        return Inertia::render('Master/Product/Products/Show', [
            'product' => $product,
        ]);
    }

    public function create(Request $request, DepartmentAccessService $departmentAccess, BranchAccessService $branchAccess): Response
    {
        return Inertia::render(
            'Master/Product/Products/Create',
            [
                'categories' => Category::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'code', 'name']),
                'unitsOfMeasure' => UnitOfMeasure::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
                'departments' => $this->accessibleDepartments($request, $departmentAccess),
                'branches' => $branchAccess->scopeBranches(Branch::query(), $request->user())->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
                'types' => Type::query()
                    ->with('category')
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'category_id', 'code', 'name']),
                'brands' => Brand::query()
                    ->where('is_active', true)
                    ->with('categories:id,code,name')
                    ->orderBy('name')
                    ->get(['id', 'code', 'name']),
                'models' => AssetModel::query()
                    ->with(['brand', 'type'])
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'brand_id', 'type_id', 'code', 'name']),
            ],
        );
    }

    public function store(
        ProductRequest $request,
        NumberingService $numberingService,
    ): RedirectResponse {
        $data = $request->validated();

        if (blank($data['code'] ?? null)) {
            if (! $numberingService->hasActiveDocument(NumberingDocument::CODE_PRODUCT)) {
                throw ValidationException::withMessages([
                    'code' => 'Enter a product code or configure an active PRODUCT numbering rule.',
                ]);
            }

            $data['code'] = $numberingService->generate(NumberingDocument::CODE_PRODUCT);

            if (Product::withTrashed()->where('code', $data['code'])->exists()) {
                throw ValidationException::withMessages([
                    'code' => 'Generated product code already exists. Update the numbering sequence start.',
                ]);
            }
        }

        $departmentIds = $data['department_ids'];
        $branchIds = $data['branch_ids'];
        unset($data['department_ids'], $data['branch_ids']);

        $product = Product::query()->create($data);
        $product->departments()->sync($departmentIds);
        $product->branches()->sync($branchIds);

        return to_route('master.product.products.index')
            ->with('success', 'Product master was created successfully.');
    }

    public function edit(Request $request, Product $product, DepartmentAccessService $departmentAccess, BranchAccessService $branchAccess): Response
    {
        $this->authorizeProductAccess($product, $request, $departmentAccess);
        $product->load(['category', 'type', 'brand', 'model', 'departments:id,name', 'branches:id,name,code']);

        return Inertia::render(
            'Master/Product/Products/Edit',
            [
                'product' => $product,
                'departments' => $this->accessibleDepartments($request, $departmentAccess),
                'branches' => $branchAccess->scopeBranches(Branch::query(), $request->user())->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
                'unitsOfMeasure' => UnitOfMeasure::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
                'categories' => Category::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'code', 'name']),
                'types' => Type::query()
                    ->with('category')
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'category_id', 'code', 'name']),
                'brands' => Brand::query()
                    ->where('is_active', true)
                    ->with('categories:id,code,name')
                    ->orderBy('name')
                    ->get(['id', 'code', 'name']),
                'models' => AssetModel::query()
                    ->with(['brand', 'type'])
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'brand_id', 'type_id', 'code', 'name']),
            ],
        );
    }

    public function update(
        ProductRequest $request,
        Product $product,
        DepartmentAccessService $departmentAccess,
        BranchAccessService $branchAccess,
    ): RedirectResponse {
        $this->authorizeProductAccess($product, $request, $departmentAccess);
        $data = $request->validated();
        $departmentIds = $data['department_ids'];
        $accessibleDepartmentIds = $departmentAccess->departmentIds($request->user());
        $hiddenDepartmentIds = DB::table('product_department')
            ->where('product_id', $product->id)
            ->whereNotIn('department_id', $accessibleDepartmentIds)
            ->pluck('department_id')
            ->all();
        $departmentIds = array_values(array_unique([...$departmentIds, ...$hiddenDepartmentIds]));
        $branchIds = $data['branch_ids'];
        $accessibleBranchIds = $branchAccess->branchIds($request->user());
        $hiddenBranchIds = DB::table('product_branch')
            ->where('product_id', $product->id)
            ->whereNotIn('branch_id', $accessibleBranchIds)
            ->pluck('branch_id')
            ->all();
        $branchIds = array_values(array_unique([...$branchIds, ...$hiddenBranchIds]));
        unset($data['department_ids'], $data['branch_ids']);
        $hasInventoryHistory = Schema::hasTable('inventory_stock_movements')
            && ($product->inventoryMovements()->exists()
                || InventoryReceiptIntegration::query()->where('product_id', $product->id)->exists());

        if (
            $hasInventoryHistory
            && ($data['group'] !== 'CONSUMABLE' || $data['inventory_tracking_mode'] !== $product->inventory_tracking_mode)
        ) {
            throw ValidationException::withMessages([
                'inventory_tracking_mode' => 'Group dan metode pelacakan tidak dapat diubah setelah produk memiliki riwayat Inventory.',
            ]);
        }

        $product->update($data);
        $product->departments()->sync($departmentIds);
        $product->branches()->sync($branchIds);

        return to_route('master.product.products.index')
            ->with('success', 'Product master was updated successfully.');
    }

    public function destroy(Request $request, Product $product, DepartmentAccessService $departmentAccess): RedirectResponse
    {
        $this->authorizeProductAccess($product, $request, $departmentAccess);
        $product->delete();

        return to_route('master.product.products.index')
            ->with('success', 'Product master was deleted successfully.');
    }

    private function accessibleProducts(Builder $query, Request $request, DepartmentAccessService $departmentAccess): Builder
    {
        $user = $request->user();
        abort_if($user === null, 401);

        if (! $departmentAccess->hasGlobalAccess($user)) {
            $query->whereHas('departments', fn ($departments) => $departments->whereIn('mst_org_departments.id', $departmentAccess->departmentIds($user)));
        }

        return $query;
    }

    private function authorizeProductAccess(Product $product, Request $request, DepartmentAccessService $departmentAccess): void
    {
        $user = $request->user();
        abort_if($user === null, 401);
        abort_unless(
            $departmentAccess->hasGlobalAccess($user)
                || $product->departments()->whereIn('mst_org_departments.id', $departmentAccess->departmentIds($user))->exists(),
            404,
        );
    }

    private function accessibleDepartments(Request $request, DepartmentAccessService $departmentAccess): Collection
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return $departmentAccess->scopeDepartments(Department::query(), $user)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'company_id']);
    }
}
