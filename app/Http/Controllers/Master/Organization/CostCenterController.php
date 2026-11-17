<?php

namespace App\Http\Controllers\Master\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Organization\CostCenterRequest;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\CostCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CostCenterController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $costCenters = CostCenter::query()
            ->with('company')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhereHas('company', function ($query) use ($search) {
                            $query
                                ->where('code', 'ilike', "%{$search}%")
                                ->orWhere('name', 'ilike', "%{$search}%");
                        });
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render(
            'Master/Organization/CostCenters/Index',
            [
                'costCenters' => $costCenters,
                'filters' => [
                    'search' => $search,
                ],
            ],
        );
    }

    public function create(): Response
    {
        $companies = Company::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render(
            'Master/Organization/CostCenters/Create',
            [
                'companies' => $companies,
            ],
        );
    }

    public function store(CostCenterRequest $request): RedirectResponse
    {
        CostCenter::create($request->validated());

        return to_route('master.organization.cost-centers.index')
            ->with('success', 'Cost Center was created successfully.');
    }

    public function edit(CostCenter $costCenter): Response
    {
        $companies = Company::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render(
            'Master/Organization/CostCenters/Edit',
            [
                'costCenter' => $costCenter,
                'companies' => $companies,
            ],
        );
    }

    public function update(
        CostCenterRequest $request,
        CostCenter $costCenter,
    ): RedirectResponse {
        $costCenter->update($request->validated());

        return to_route('master.organization.cost-centers.index')
            ->with('success', 'Cost Center was updated successfully.');
    }

    public function destroy(
        CostCenter $costCenter,
    ): RedirectResponse {
        $costCenter->delete();

        return to_route('master.organization.cost-centers.index')
            ->with('success', 'Cost Center was deleted successfully.');
    }
}
