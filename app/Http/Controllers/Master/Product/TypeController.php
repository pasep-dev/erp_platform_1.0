<?php

namespace App\Http\Controllers\Master\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Product\TypeRequest;
use App\Models\Master\Product\Category;
use App\Models\Master\Product\Type;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TypeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $types = Type::query()
            ->with('category')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhereHas('category', function ($query) use ($search) {
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
            'Master/Product/Types/Index',
            [
                'types' => $types,
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
            'Master/Product/Types/Create',
            [
                'categories' => $categories,
            ],
        );
    }

    public function store(TypeRequest $request): RedirectResponse
    {
        Type::create($request->validated());

        return to_route('master.product.types.index')
            ->with('success', 'Asset Type was created successfully.');
    }

    public function edit(Type $type): Response
    {
        $categories = Category::query()
            ->where(function ($query) use ($type) {
                $query
                    ->where('is_active', true)
                    ->orWhere('id', $type->category_id);
            })
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'parent_id',
            ]);

        return Inertia::render(
            'Master/Product/Types/Edit',
            [
                'type' => $type,
                'categories' => $categories,
            ],
        );
    }

    public function update(
        TypeRequest $request,
        Type $type,
    ): RedirectResponse {
        $type->update($request->validated());

        return to_route('master.product.types.index')
            ->with('success', 'Asset Type was updated successfully.');
    }

    public function destroy(Type $type): RedirectResponse
    {
        $type->delete();

        return to_route('master.product.types.index')
            ->with('success', 'Asset Type was deleted successfully.');
    }
}
