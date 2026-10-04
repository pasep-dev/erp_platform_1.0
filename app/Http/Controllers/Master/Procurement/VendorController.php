<?php

namespace App\Http\Controllers\Master\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Procurement\VendorRequest;
use App\Models\Master\Procurement\Vendor;
use App\Models\Master\Procurement\VendorType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        $vendors = Vendor::query()
            ->with([
                'vendorType',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'ILIKE', "%{$search}%")
                        ->orWhere('name', 'ILIKE', "%{$search}%")
                        ->orWhere('tax_number', 'ILIKE', "%{$search}%")
                        ->orWhere('email', 'ILIKE', "%{$search}%")
                        ->orWhere('phone', 'ILIKE', "%{$search}%")
                        ->orWhere('address', 'ILIKE', "%{$search}%")
                        ->orWhere('description', 'ILIKE', "%{$search}%")
                        ->orWhereHas('vendorType', function ($query) use ($search) {
                            $query->where('code', 'ILIKE', "%{$search}%")
                                ->orWhere('name', 'ILIKE', "%{$search}%");
                        })
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Master/Procurement/Vendors/Index', [
            'vendors' => $vendors,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(): Response
    {
        $vendorTypes = VendorType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
            ]);

        return Inertia::render('Master/Procurement/Vendors/Create', [
            'vendorTypes' => $vendorTypes,
        ]);
    }

    public function store(VendorRequest $request): RedirectResponse
    {
        Vendor::create($request->validated());

        return redirect()
            ->route('master.procurement.vendors.index')
            ->with('success', 'Vendor berhasil ditambahkan.');
    }

    public function edit(Vendor $vendor): Response
    {
        $vendorTypes = VendorType::query()
            ->where(function ($query) use ($vendor) {
                $query->where('is_active', true);

                if ($vendor->vendor_type_id !== null) {
                    $query->orWhere('id', $vendor->vendor_type_id);
                }
            })
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'is_active',
            ]);

        return Inertia::render('Master/Procurement/Vendors/Edit', [
            'vendor' => $vendor->load([
                'vendorType',
            ]),
            'vendorTypes' => $vendorTypes,
        ]);
    }

    public function update(
        VendorRequest $request,
        Vendor $vendor
    ): RedirectResponse {
        $vendor->update($request->validated());

        return redirect()
            ->route('master.procurement.vendors.index')
            ->with('success', 'Vendor berhasil diperbarui.');
    }

    public function destroy(Vendor $vendor): RedirectResponse
    {
        $vendor->delete();

        return redirect()
            ->route('master.procurement.vendors.index')
            ->with('success', 'Vendor berhasil dihapus.');
    }
}
