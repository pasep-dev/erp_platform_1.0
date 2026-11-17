<?php

namespace App\Http\Controllers\Master\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Procurement\TaxRequest;
use App\Models\Master\Procurement\Tax;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $taxes = Tax::query()
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
            'Master/Procurement/Taxes/Index',
            [
                'taxes' => $taxes,
                'filters' => [
                    'search' => $search,
                ],
            ],
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'Master/Procurement/Taxes/Create',
        );
    }

    public function store(
        TaxRequest $request,
    ): RedirectResponse {
        Tax::create($request->validated());

        return to_route('master.procurement.taxes.index')
            ->with('success', 'Tax was created successfully.');
    }

    public function edit(
        Tax $tax,
    ): Response {
        return Inertia::render(
            'Master/Procurement/Taxes/Edit',
            [
                'tax' => $tax,
            ],
        );
    }

    public function update(
        TaxRequest $request,
        Tax $tax,
    ): RedirectResponse {
        $tax->update($request->validated());

        return to_route('master.procurement.taxes.index')
            ->with('success', 'Tax was updated successfully.');
    }

    public function destroy(
        Tax $tax,
    ): RedirectResponse {
        $tax->delete();

        return to_route('master.procurement.taxes.index')
            ->with('success', 'Tax was deleted successfully.');
    }
}
