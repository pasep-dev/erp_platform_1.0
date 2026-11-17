<?php

namespace App\Http\Controllers\Master\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Product\LicenseTypeRequest;
use App\Models\Master\Product\LicenseType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LicenseTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $licenseTypes = LicenseType::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere(
                            'description',
                            'ilike',
                            "%{$search}%",
                        );
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render(
            'Master/Product/LicenseTypes/Index',
            [
                'licenseTypes' => $licenseTypes,
                'filters' => [
                    'search' => $search,
                ],
            ],
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'Master/Product/LicenseTypes/Create',
        );
    }

    public function store(
        LicenseTypeRequest $request,
    ): RedirectResponse {
        LicenseType::create($request->validated());

        return to_route('master.product.license-types.index')
            ->with('success', 'License Type was created successfully.');
    }

    public function edit(
        LicenseType $licenseType,
    ): Response {
        return Inertia::render(
            'Master/Product/LicenseTypes/Edit',
            [
                'licenseType' => $licenseType,
            ],
        );
    }

    public function update(
        LicenseTypeRequest $request,
        LicenseType $licenseType,
    ): RedirectResponse {
        $licenseType->update($request->validated());

        return to_route('master.product.license-types.index')
            ->with('success', 'License Type was updated successfully.');
    }

    public function destroy(
        LicenseType $licenseType,
    ): RedirectResponse {
        $licenseType->delete();

        return to_route('master.product.license-types.index')
            ->with('success', 'License Type was deleted successfully.');
    }
}
