<?php

namespace App\Http\Controllers\Master\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Product\BrandRequest;
use App\Models\Master\Product\Brand;
use App\Models\Master\Product\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $brands = Brand::query()
            ->with('categories:id,code,name')
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
                    ->orderBy('name')
                    ->get(['id', 'code', 'name']),
            ],
        );
    }

    public function store(BrandRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $categoryIds = $data['category_ids'];
        unset($data['category_ids']);

        DB::transaction(function () use ($data, $categoryIds): void {
            $brand = Brand::create($data);
            $brand->categories()->sync($categoryIds);
        });

        return to_route('master.product.brands.index')
            ->with('success', 'Asset Brand was created successfully.');
    }

    public function edit(Brand $brand): Response
    {
        $brand->load('categories:id,code,name');

        return Inertia::render(
            'Master/Product/Brands/Edit',
            [
                'brand' => $brand,
                'categories' => Category::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'code', 'name']),
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
