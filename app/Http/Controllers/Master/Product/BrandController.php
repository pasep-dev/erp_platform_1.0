<?php

namespace App\Http\Controllers\Master\Product;

use App\Enums\NumberingModuleCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Product\BrandRequest;
use App\Models\Master\Product\Brand;
use App\Models\Master\Product\Category;
use App\Services\NumberingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $brands = Brand::query()
            ->with('categories:id,code,name,parent_id')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render(
            'Master/Product/Brands/Index',
            [
                'brands' => $brands,
                'filters' => [
                    'search' => $search,
                ],
            ],
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'Master/Product/Brands/Create',
            [
                'categories' => Category::query()
                    ->where('is_active', true)
                    ->whereNull('parent_id')
                    ->withCount(['children' => fn ($query) => $query->where('is_active', true)])
                    ->orderBy('name')
                    ->get(['id', 'code', 'name', 'parent_id']),
            ],
        );
    }

    public function store(BrandRequest $request, NumberingService $numberingService): RedirectResponse
    {
        $data = $request->validated();
        $categoryIds = $data['category_ids'];
        unset($data['category_ids']);

        if (! $numberingService->hasActiveDocument(NumberingModuleCode::ProductBrand)) {
            throw ValidationException::withMessages([
                'code' => 'Configure an active PRODUCT_BRAND numbering rule before creating a brand.',
            ]);
        }

        $data['code'] = mb_strtoupper(
            $numberingService->generate(NumberingModuleCode::ProductBrand),
            'UTF-8',
        );

        if (Brand::withTrashed()->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Generated brand code already exists. Update the numbering sequence start.',
            ]);
        }

        DB::transaction(function () use ($data, $categoryIds): void {
            $brand = Brand::create($data);
            $brand->categories()->sync($categoryIds);
        });

        return to_route('master.product.brands.index')
            ->with('success', 'Asset Brand was created successfully.');
    }

    public function edit(Brand $brand): Response
    {
        $brand->load('categories.parent:id,code,name');
        $brand->setRelation(
            'categories',
            $brand->categories
                ->map(fn (Category $category): Category => $category->parent ?? $category)
                ->unique('id')
                ->values(),
        );

        return Inertia::render(
            'Master/Product/Brands/Edit',
            [
                'brand' => $brand,
                'categories' => Category::query()
                    ->where('is_active', true)
                    ->whereNull('parent_id')
                    ->withCount(['children' => fn ($query) => $query->where('is_active', true)])
                    ->orderBy('name')
                    ->get(['id', 'code', 'name', 'parent_id']),
            ],
        );
    }

    public function update(
        BrandRequest $request,
        Brand $brand,
    ): RedirectResponse {
        $data = $request->validated();
        $categoryIds = $data['category_ids'];
        unset($data['category_ids']);

        DB::transaction(function () use ($brand, $data, $categoryIds): void {
            $brand->update($data);
            $brand->categories()->sync($categoryIds);
        });

        return to_route('master.product.brands.index')
            ->with('success', 'Asset Brand was updated successfully.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $brand->delete();

        return to_route('master.product.brands.index')
            ->with('success', 'Asset Brand was deleted successfully.');
    }
}
