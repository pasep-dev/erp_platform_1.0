<?php

namespace App\Http\Controllers\Master\Procurement;

use App\Enums\NumberingModuleCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Procurement\VendorTypeRequest;
use App\Models\Master\Procurement\VendorType;
use App\Services\NumberingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
    public function store(
        VendorTypeRequest $request,
        NumberingService $numberingService,
    ): RedirectResponse {
        $data = $request->validated();

        if (blank($data['code'] ?? null)) {
            if (! $numberingService->hasActiveDocument(NumberingModuleCode::VendorType)) {
                throw ValidationException::withMessages([
                    'code' => 'Enter a vendor type code or configure an active VENDOR_TYPE numbering rule.',
                ]);
            }

            $data['code'] = mb_strtoupper(
                $numberingService->generate(NumberingModuleCode::VendorType),
                'UTF-8',
            );

            if (VendorType::withTrashed()->where('code', $data['code'])->exists()) {
                throw ValidationException::withMessages([
                    'code' => 'Generated vendor type code already exists. Update the numbering sequence start.',
                ]);
            }
        }

        VendorType::create($data);

        return redirect()
            ->route('master.procurement.vendor-types.index')
            ->with('success', 'Vendor type was added successfully.');
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
            ->with('success', 'Vendor type was updated successfully.');
    }

    /**
     * Remove the specified vendor type.
     */
    public function destroy(VendorType $vendorType): RedirectResponse
    {
        $vendorType->delete();

        return redirect()
            ->route('master.procurement.vendor-types.index')
            ->with('success', 'Vendor type was deleted successfully.');
    }
}
