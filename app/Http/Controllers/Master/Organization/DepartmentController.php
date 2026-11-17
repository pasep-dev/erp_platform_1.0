<?php

namespace App\Http\Controllers\Master\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Organization\DepartmentRequest;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $departments = Department::query()
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
            'Master/Organization/Departments/Index',
            [
                'departments' => $departments,
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
            'Master/Organization/Departments/Create',
            [
                'companies' => $companies,
            ],
        );
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        Department::create($request->validated());

        return to_route('master.organization.departments.index')
            ->with('success', 'Department was created successfully.');
    }

    public function edit(Department $department): Response
    {
        $companies = Company::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render(
            'Master/Organization/Departments/Edit',
            [
                'department' => $department,
                'companies' => $companies,
            ],
        );
    }

    public function update(
        DepartmentRequest $request,
        Department $department,
    ): RedirectResponse {
        $department->update($request->validated());

        return to_route('master.organization.departments.index')
            ->with('success', 'Department was updated successfully.');
    }

    public function destroy(
        Department $department,
    ): RedirectResponse {
        $department->delete();

        return to_route('master.organization.departments.index')
            ->with('success', 'Department was deleted successfully.');
    }
}
