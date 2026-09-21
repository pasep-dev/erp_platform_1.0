<?php

namespace App\Http\Controllers\Master\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Procurement\VendorTypeRequest;
use App\Models\Master\Procurement\VendorType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorTypeController extends Controller
{
    /**
     * Display a listing of vendor types.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        $vendorTypes = VendorType::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'ILIKE', "%{$search}%")
                        ->orWhere('name', 'ILIKE', "%{$search}%")
                        ->orWhere('description', 'ILIKE', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Master/Procurement/VendorTypes/Index', [
            'vendorTypes' => $vendorTypes,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show the form for creating a new vendor type.
     */
    public function create(): Response
    {
        return Inertia::render('Master/Procurement/VendorTypes/Create');
    }

    /**
     * Store a newly created vendor type.
     */
    public function store(VendorTypeRequest $request): RedirectResponse
    {
        VendorType::create($request->validated());

        return redirect()
            ->route('master.procurement.vendor-types.index')
            ->with('success', 'Vendor type berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified vendor type.
     */
    public function edit(VendorType $vendorType): Response
    {
        return Inertia::render('Master/Procurement/VendorTypes/Edit', [
            'vendorType' => $vendorType,
        ]);
    }

    /**
     * Update the specified vendor type.
     */
    public function update(
        VendorTypeRequest $request,
        VendorType $vendorType
    ): RedirectResponse {
        $vendorType->update($request->validated());

        return redirect()
            ->route('master.procurement.vendor-types.index')
            ->with('success', 'Vendor type berhasil diperbarui.');
    }

    /**
     * Remove the specified vendor type.
     */
    public function destroy(VendorType $vendorType): RedirectResponse
    {
        $vendorType->delete();

        return redirect()
            ->route('master.procurement.vendor-types.index')
            ->with('success', 'Vendor type berhasil dihapus.');
    }
}
