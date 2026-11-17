<?php

namespace App\Http\Controllers\Master\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Organization\BranchRequest;
use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $branches = Branch::query()
            ->with('company')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('address', 'ilike', "%{$search}%")
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

        return Inertia::render('Master/Organization/Branches/Index', [
            'branches' => $branches,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(): Response
    {
        $companies = Company::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Master/Organization/Branches/Create', [
            'companies' => $companies,
        ]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        Branch::create($request->validated());

        return to_route('master.organization.branches.index')
            ->with('success', 'Branch was created successfully.');
    }

    public function edit(Branch $branch): Response
    {
        $companies = Company::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Master/Organization/Branches/Edit', [
            'branch' => $branch,
            'companies' => $companies,
        ]);
    }

    public function update(
        BranchRequest $request,
        Branch $branch,
    ): RedirectResponse {
        $branch->update($request->validated());

        return to_route('master.organization.branches.index')
            ->with('success', 'Branch was updated successfully.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $branch->delete();

        return to_route('master.organization.branches.index')
            ->with('success', 'Branch was deleted successfully.');
    }
}
