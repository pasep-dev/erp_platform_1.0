<?php

namespace App\Http\Controllers\Master\Product;

use App\Enums\NumberingModuleCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Product\ProductRequest;
use App\Models\Master\Organization\Department;
use App\Models\Master\Procurement\UnitOfMeasure;
use App\Models\Master\Product\Brand;
use App\Models\Master\Product\Category;
use App\Models\Master\Product\Model as AssetModel;
use App\Models\Master\Product\Product;
use App\Models\Master\Product\Type;
use App\Models\Transaction\Inventory\InventoryReceiptIntegration;
use App\Services\DepartmentAccessService;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request, DepartmentAccessService $departmentAccess): Response
    {
        $filters = $this->productFilters($request);
        $search = $filters['search'];
        $group = $filters['group'];
        $status = $filters['status'];
        $requestedPerPage = $request->integer('per_page', 15);
        $perPage = in_array($requestedPerPage, [5, 15, 25, 50, 100], true) ? $requestedPerPage : 15;
        $products = $this->filteredProducts($request, $departmentAccess, $filters)
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

    public function export(Request $request, DepartmentAccessService $departmentAccess): StreamedResponse
    {
        $filters = $this->productFilters($request);
        $products = $this->filteredProducts($request, $departmentAccess, $filters)->lazy(500);

        return response()->streamDownload(function () use ($products): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Product Code', 'Product Name', 'Group', 'Description',
                'Category Code', 'Category', 'Type Code', 'Type',
                'Brand Code', 'Brand', 'Model Code', 'Model',
                'Unit of Measure Code', 'Base Unit of Measure Code',
                'Departments', 'Inventory Tracking Mode', 'Status', 'Created At',
            ]);

            foreach ($products as $product) {
                fputcsv($output, array_map($this->escapeCsvCell(...), [
                    $product->code,
                    $product->name,
                    $product->group,
                    $product->description,
                    $product->category?->code,
                    $product->category?->name,
                    $product->type?->code,
                    $product->type?->name,
                    $product->brand?->code,
                    $product->brand?->name,
                    $product->model?->code,
                    $product->model?->name,
                    $product->unitOfMeasure?->code,
                    $product->baseUnitOfMeasure?->code,
                    $product->departments
                        ->map(fn (Department $department): string => $department->code.' - '.$department->name)
                        ->implode('; '),
                    $product->inventory_tracking_mode,
                    $product->is_active ? 'Active' : 'Inactive',
                    $product->created_at?->toDateTimeString(),
                ]));
            }

            fclose($output);
        }, 'master-products-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function show(Request $request, Product $product, DepartmentAccessService $departmentAccess): Response
    {
        $this->authorizeProductAccess($product, $request, $departmentAccess);
        $product->load(['category', 'type', 'brand', 'model', 'departments:id,name']);

        return Inertia::render('Master/Product/Products/Show', [
            'product' => $product,
        ]);
    }

    public function create(Request $request, DepartmentAccessService $departmentAccess): Response
    {
        return Inertia::render(
            'Master/Product/Products/Create',
            [
                'categories' => Category::query()
                    ->where('is_active', true)
                    ->whereIn('department_id', $departmentAccess->departmentIds($request->user()))
                    ->orderBy('name')
                    ->with('department:id,code,name')
                    ->get(['id', 'code', 'name', 'department_id']),
                'unitsOfMeasure' => UnitOfMeasure::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
                'departments' => $this->accessibleDepartments($request, $departmentAccess),
                'types' => Type::query()
                    ->with('category:id,code,name,department_id')
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'category_id', 'code', 'name']),
                'brands' => Brand::query()
                    ->where('is_active', true)
                    ->with('categories:id,code,name,department_id')
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

        if (! $numberingService->hasActiveDocument(NumberingModuleCode::Product)) {
            throw ValidationException::withMessages([
                'code' => 'Configure an active PRODUCT numbering rule before creating a product.',
            ]);
        }

        $data['code'] = mb_strtoupper(
            $numberingService->generate(NumberingModuleCode::Product),
            'UTF-8',
        );

        if (Product::withTrashed()->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Generated product code already exists. Update the numbering sequence start.',
            ]);
        }

        $departmentIds = $data['department_ids'];
        unset($data['department_ids']);
        $image = $request->file('image');
        unset($data['image']);

        if ($image !== null) {
            $imagePath = $image->storePublicly('products', 'public');

            if (! is_string($imagePath)) {
                throw new RuntimeException('Unable to store the product image.');
            }

            $data['image_path'] = $imagePath;
        }

        try {
            $product = Product::query()->create($data);
            $product->departments()->sync($departmentIds);
        } catch (Throwable $exception) {
            if (isset($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }

        return to_route('master.product.products.index')
            ->with('success', 'Product master was created successfully.');
    }

    public function edit(Request $request, Product $product, DepartmentAccessService $departmentAccess): Response
    {
        $this->authorizeProductAccess($product, $request, $departmentAccess);
        $product->load(['category', 'type', 'brand', 'model', 'departments:id,name']);

        return Inertia::render(
            'Master/Product/Products/Edit',
            [
                'product' => $product,
                'departments' => $this->accessibleDepartments($request, $departmentAccess),
                'unitsOfMeasure' => UnitOfMeasure::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
                'categories' => Category::query()
                    ->where('is_active', true)
                    ->whereIn('department_id', $departmentAccess->departmentIds($request->user()))
                    ->orderBy('name')
                    ->with('department:id,code,name')
                    ->get(['id', 'code', 'name', 'department_id']),
                'types' => Type::query()
                    ->with('category')
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'category_id', 'code', 'name']),
                'brands' => Brand::query()
                    ->where('is_active', true)
                    ->with('categories:id,code,name,department_id')
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
    ): RedirectResponse {
        $this->authorizeProductAccess($product, $request, $departmentAccess);
        $data = $request->validated();
        $departmentIds = $data['department_ids'];
        $image = $request->file('image');
        $accessibleDepartmentIds = $departmentAccess->departmentIds($request->user());
        $hiddenDepartmentIds = DB::table('mst_product_departments')
            ->where('product_id', $product->id)
            ->whereNotIn('department_id', $accessibleDepartmentIds)
            ->pluck('department_id')
            ->all();
        $departmentIds = array_values(array_unique([...$departmentIds, ...$hiddenDepartmentIds]));
        unset($data['department_ids']);
        unset($data['image']);
        $hasInventoryHistory = Schema::hasTable('trx_inventory_stock_movements')
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

        $oldImagePath = $product->image_path;
        $newImagePath = null;

        if ($image !== null) {
            $newImagePath = $image->storePublicly('products', 'public');

            if (! is_string($newImagePath)) {
                throw new RuntimeException('Unable to store the product image.');
            }

            $data['image_path'] = $newImagePath;
        }

        try {
            $product->update($data);
            $product->departments()->sync($departmentIds);
        } catch (Throwable $exception) {
            if ($newImagePath !== null) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $exception;
        }

        if ($newImagePath !== null && $oldImagePath !== null) {
            Storage::disk('public')->delete($oldImagePath);
        }

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

    /**
     * @return array{search: string, group: string, status: string}
     */
    private function productFilters(Request $request): array
    {
        $group = $request->string('group')->trim()->toString();
        $status = $request->string('status', 'ALL')->toString();

        return [
            'search' => $request->string('search')->trim()->toString(),
            'group' => in_array($group, ['ASSET', 'CONSUMABLE', 'LICENSE'], true) ? $group : '',
            'status' => in_array($status, ['ALL', 'ACTIVE', 'INACTIVE'], true) ? $status : 'ALL',
        ];
    }

    /**
     * @param  array{search: string, group: string, status: string}  $filters
     */
    private function filteredProducts(
        Request $request,
        DepartmentAccessService $departmentAccess,
        array $filters,
    ): Builder {
        return $this->accessibleProducts(Product::query(), $request, $departmentAccess)
            ->with([
                'category:id,code,name',
                'type:id,code,name',
                'brand:id,code,name',
                'model:id,code,name',
                'unitOfMeasure:id,code',
                'baseUnitOfMeasure:id,code',
                'departments:id,code,name',
            ])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $search = $filters['search'];
                    $query->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('group', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            })
            ->when($filters['group'] !== '', fn (Builder $query): Builder => $query->where('group', $filters['group']))
            ->when($filters['status'] === 'ACTIVE', fn (Builder $query): Builder => $query->where('is_active', true))
            ->when($filters['status'] === 'INACTIVE', fn (Builder $query): Builder => $query->where('is_active', false))
            ->orderBy('name');
    }

    private function escapeCsvCell(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[\s]*[=+\-@]/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
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
