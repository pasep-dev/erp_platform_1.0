<?php

namespace App\Http\Controllers\Master\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Product\CategoryRequest;
use App\Models\Master\Organization\Department;
use App\Models\Master\Product\Category;
use App\Services\DepartmentAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $categories = Category::query()
            ->with('department:id,code,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhereHas('department', function ($query) use ($search) {
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
            'Master/Product/Categories/Index',
            [
                'categories' => $categories,
                'filters' => [
                    'search' => $search,
                ],
            ],
        );
    }

    public function create(Request $request, DepartmentAccessService $departmentAccess): Response
    {
        $departments = Department::query()
            ->where('is_active', true)
            ->whereIn('id', $departmentAccess->departmentIds($request->user()))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render(
            'Master/Product/Categories/Create',
            [
                'departments' => $departments,
            ],
        );
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return to_route('master.product.categories.index')
            ->with('success', 'Asset Category was created successfully.');
    }

    public function edit(Request $request, Category $category, DepartmentAccessService $departmentAccess): Response
    {
        $departments = Department::query()
            ->where('is_active', true)
            ->whereIn('id', $departmentAccess->departmentIds($request->user()))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render(
            'Master/Product/Categories/Edit',
            [
                'category' => $category,
                'departments' => $departments,
            ],
        );
    }

    public function update(
        CategoryRequest $request,
        Category $category,
    ): RedirectResponse {
        $category->update($request->validated());

        return to_route('master.product.categories.index')
            ->with('success', 'Asset Category was updated successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return to_route('master.product.categories.index')
            ->with('success', 'Asset Category was deleted successfully.');
    }
}
