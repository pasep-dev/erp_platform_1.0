<?php

namespace App\Http\Controllers\Master\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Procurement\UnitOfMeasureRequest;
use App\Models\Master\Procurement\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitOfMeasureController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $unitsOfMeasure = UnitOfMeasure::query()
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
            'Master/Procurement/UnitsOfMeasure/Index',
            [
                'unitsOfMeasure' => $unitsOfMeasure,
                'filters' => [
                    'search' => $search,
                ],
            ],
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'Master/Procurement/UnitsOfMeasure/Create',
        );
    }

    public function store(
        UnitOfMeasureRequest $request,
    ): RedirectResponse {
        UnitOfMeasure::create($request->validated());

        return to_route('master.procurement.units-of-measure.index')
            ->with('success', 'Unit of Measure berhasil dibuat.');
    }

    public function edit(
        UnitOfMeasure $unitsOfMeasure,
    ): Response {
        return Inertia::render(
            'Master/Procurement/UnitsOfMeasure/Edit',
            [
                'unitOfMeasure' => $unitsOfMeasure,
            ],
        );
    }

    public function update(
        UnitOfMeasureRequest $request,
        UnitOfMeasure $unitsOfMeasure,
    ): RedirectResponse {
        $unitsOfMeasure->update($request->validated());

        return to_route('master.procurement.units-of-measure.index')
            ->with('success', 'Unit of Measure berhasil diperbarui.');
    }

    public function destroy(
        UnitOfMeasure $unitsOfMeasure,
    ): RedirectResponse {
        $unitsOfMeasure->delete();

        return to_route('master.procurement.units-of-measure.index')
            ->with('success', 'Unit of Measure berhasil dihapus.');
    }
}
