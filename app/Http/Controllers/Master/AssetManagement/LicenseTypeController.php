<?php

namespace App\Http\Controllers\Master\AssetManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\AssetManagement\LicenseTypeRequest;
use App\Models\Master\AssetManagement\LicenseType;
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
            'Master/AssetManagement/LicenseTypes/Index',
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
            'Master/AssetManagement/LicenseTypes/Create',
        );
    }

    public function store(
        LicenseTypeRequest $request,
    ): RedirectResponse {
        LicenseType::create($request->validated());

        return to_route('master.asset-management.license-types.index')
            ->with('success', 'License Type berhasil dibuat.');
    }

    public function edit(
        LicenseType $licenseType,
    ): Response {
        return Inertia::render(
            'Master/AssetManagement/LicenseTypes/Edit',
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

        return to_route('master.asset-management.license-types.index')
            ->with('success', 'License Type berhasil diperbarui.');
    }

    public function destroy(
        LicenseType $licenseType,
    ): RedirectResponse {
        $licenseType->delete();

        return to_route('master.asset-management.license-types.index')
            ->with('success', 'License Type berhasil dihapus.');
    }
}
