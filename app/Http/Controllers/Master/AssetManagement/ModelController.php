<?php

namespace App\Http\Controllers\Master\AssetManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\AssetManagement\ModelRequest;
use App\Models\Master\AssetManagement\Brand;
use App\Models\Master\AssetManagement\Model;
use App\Models\Master\AssetManagement\Type;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModelController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $models = Model::query()
            ->with([
                'brand:id,code,name',
                'type:id,category_id,code,name',
                'type.category:id,code,name',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhereHas('brand', function ($query) use ($search) {
                            $query
                                ->where('code', 'ilike', "%{$search}%")
                                ->orWhere('name', 'ilike', "%{$search}%");
                        })
                        ->orWhereHas('type', function ($query) use ($search) {
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
            'Master/AssetManagement/Models/Index',
            [
                'models' => $models,
                'filters' => [
                    'search' => $search,
                ],
            ],
        );
    }

    public function create(): Response
    {
        $brands = Brand::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
            ]);

        $types = Type::query()
            ->with('category:id,code,name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'category_id',
                'code',
                'name',
            ]);

        return Inertia::render(
            'Master/AssetManagement/Models/Create',
            [
                'brands' => $brands,
                'types' => $types,
            ],
        );
    }

    public function store(ModelRequest $request): RedirectResponse
    {
        Model::create($request->validated());

        return to_route('master.asset-management.models.index')
            ->with('success', 'Asset Model berhasil dibuat.');
    }

    public function edit(Model $model): Response
    {
        $brands = Brand::query()
            ->where(function ($query) use ($model) {
                $query
                    ->where('is_active', true)
                    ->orWhere('id', $model->brand_id);
            })
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
            ]);

        $types = Type::query()
            ->with('category:id,code,name')
            ->where(function ($query) use ($model) {
                $query
                    ->where('is_active', true)
                    ->orWhere('id', $model->type_id);
            })
            ->orderBy('name')
            ->get([
                'id',
                'category_id',
                'code',
                'name',
            ]);

        $model->load([
            'brand:id,code,name',
            'type:id,category_id,code,name',
            'type.category:id,code,name',
        ]);

        return Inertia::render(
            'Master/AssetManagement/Models/Edit',
            [
                'model' => $model,
                'brands' => $brands,
                'types' => $types,
            ],
        );
    }

    public function update(
        ModelRequest $request,
        Model $model,
    ): RedirectResponse {
        $model->update($request->validated());

        return to_route('master.asset-management.models.index')
            ->with('success', 'Asset Model berhasil diperbarui.');
    }

    public function destroy(Model $model): RedirectResponse
    {
        $model->delete();

        return to_route('master.asset-management.models.index')
            ->with('success', 'Asset Model berhasil dihapus.');
    }
}
