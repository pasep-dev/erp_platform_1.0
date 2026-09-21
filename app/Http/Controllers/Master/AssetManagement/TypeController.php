<?php

namespace App\Http\Controllers\Master\AssetManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\AssetManagement\TypeRequest;
use App\Models\Master\AssetManagement\Category;
use App\Models\Master\AssetManagement\Type;
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
            'Master/AssetManagement/Types/Index',
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
            'Master/AssetManagement/Types/Create',
            [
                'categories' => $categories,
            ],
        );
    }

    public function store(TypeRequest $request): RedirectResponse
    {
        Type::create($request->validated());

        return to_route('master.asset-management.types.index')
            ->with('success', 'Asset Type berhasil dibuat.');
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
            'Master/AssetManagement/Types/Edit',
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

        return to_route('master.asset-management.types.index')
            ->with('success', 'Asset Type berhasil diperbarui.');
    }

    public function destroy(Type $type): RedirectResponse
    {
        $type->delete();

        return to_route('master.asset-management.types.index')
            ->with('success', 'Asset Type berhasil dihapus.');
    }
}
