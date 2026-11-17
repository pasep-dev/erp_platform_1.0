<?php

namespace App\Http\Controllers\Master\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Product\CategoryRequest;
use App\Models\Master\Product\Category;
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
            ->with('parent')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
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
            'Master/Product/Categories/Index',
            [
                'categories' => $categories,
                'filters' => [
                    'search' => $search,
                ],
            ],
        );
    }

    public function create(): Response
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'parent_id',
            ]);

        return Inertia::render(
            'Master/Product/Categories/Create',
            [
                'categories' => $categories,
            ],
        );
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return to_route('master.product.categories.index')
            ->with('success', 'Asset Category was created successfully.');
    }

    public function edit(Category $category): Response
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->where('id', '!=', $category->id)
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'parent_id',
            ]);

        return Inertia::render(
            'Master/Product/Categories/Edit',
            [
                'category' => $category,
                'categories' => $categories,
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
