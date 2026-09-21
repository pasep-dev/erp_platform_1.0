<?php

namespace App\Http\Controllers\Master\AssetManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\AssetManagement\BrandRequest;
use App\Models\Master\AssetManagement\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $brands = Brand::query()
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
            'Master/AssetManagement/Brands/Index',
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
            'Master/AssetManagement/Brands/Create',
        );
    }

    public function store(BrandRequest $request): RedirectResponse
    {
        Brand::create($request->validated());

        return to_route('master.asset-management.brands.index')
            ->with('success', 'Asset Brand berhasil dibuat.');
    }

    public function edit(Brand $brand): Response
    {
        return Inertia::render(
            'Master/AssetManagement/Brands/Edit',
            [
                'brand' => $brand,
            ],
        );
    }

    public function update(
        BrandRequest $request,
        Brand $brand,
    ): RedirectResponse {
        $brand->update($request->validated());

        return to_route('master.asset-management.brands.index')
            ->with('success', 'Asset Brand berhasil diperbarui.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $brand->delete();

        return to_route('master.asset-management.brands.index')
            ->with('success', 'Asset Brand berhasil dihapus.');
    }
}
