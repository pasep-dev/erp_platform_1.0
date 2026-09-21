<?php

namespace App\Http\Controllers\Master\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Organization\LocationRequest;
use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $locations = Location::query()
            ->with([
                'company',
                'branch',
                'parent',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('location_type', 'ilike', "%{$search}%")
                        ->orWhereHas('company', function ($query) use ($search) {
                            $query
                                ->where('code', 'ilike', "%{$search}%")
                                ->orWhere('name', 'ilike', "%{$search}%");
                        })
                        ->orWhereHas('branch', function ($query) use ($search) {
                            $query
                                ->where('code', 'ilike', "%{$search}%")
                                ->orWhere('name', 'ilike', "%{$search}%");
                        })
                        ->orWhereHas('parent', function ($query) use ($search) {
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
            'Master/Organization/Locations/Index',
            [
                'locations' => $locations,
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

        $branches = Branch::query()
            ->orderBy('name')
            ->get(['id', 'company_id', 'code', 'name']);

        $parents = Location::query()
            ->orderBy('name')
            ->get(['id', 'company_id', 'code', 'name']);

        return Inertia::render(
            'Master/Organization/Locations/Create',
            [
                'companies' => $companies,
                'branches' => $branches,
                'parents' => $parents,
            ],
        );
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        Location::create($request->validated());

        return to_route('master.organization.locations.index')
            ->with('success', 'Location berhasil dibuat.');
    }

    public function edit(Location $location): Response
    {
        $companies = Company::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $branches = Branch::query()
            ->orderBy('name')
            ->get(['id', 'company_id', 'code', 'name']);

        $parents = Location::query()
            ->where('id', '!=', $location->id)
            ->orderBy('name')
            ->get(['id', 'company_id', 'code', 'name']);

        return Inertia::render(
            'Master/Organization/Locations/Edit',
            [
                'location' => $location,
                'companies' => $companies,
                'branches' => $branches,
                'parents' => $parents,
            ],
        );
    }

    public function update(
        LocationRequest $request,
        Location $location,
    ): RedirectResponse {
        $location->update($request->validated());

        return to_route('master.organization.locations.index')
            ->with('success', 'Location berhasil diperbarui.');
    }

    public function destroy(
        Location $location,
    ): RedirectResponse {
        $location->delete();

        return to_route('master.organization.locations.index')
            ->with('success', 'Location berhasil dihapus.');
    }
}
