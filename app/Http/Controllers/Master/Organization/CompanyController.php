<?php

namespace App\Http\Controllers\Master\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Organization\CompanyRequest;
use App\Models\Master\Organization\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $companies = Company::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('short_name', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Master/Organization/Companies/Index', [
            'companies' => $companies,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Master/Organization/Companies/Create');
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        Company::create($request->validated());

        return to_route('master.organization.companies.index')
            ->with('success', 'Company berhasil dibuat.');
    }

    public function edit(Company $company): Response
    {
        return Inertia::render('Master/Organization/Companies/Edit', [
            'company' => $company,
        ]);
    }

    public function update(
        CompanyRequest $request,
        Company $company,
    ): RedirectResponse {
        $company->update($request->validated());

        return to_route('master.organization.companies.index')
            ->with('success', 'Company berhasil diperbarui.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $company->delete();

        return to_route('master.organization.companies.index')
            ->with('success', 'Company berhasil dihapus.');
    }
}
