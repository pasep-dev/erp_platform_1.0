<?php

namespace App\Http\Controllers\Master\Product;

use App\Enums\NumberingModuleCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Product\TypeRequest;
use App\Models\Master\Product\Category;
use App\Models\Master\Product\Type;
use App\Services\DepartmentAccessService;
use App\Services\NumberingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TypeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $types = Type::query()
            ->with('category.department:id,code,name')
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

    public function create(Request $request, DepartmentAccessService $departmentAccess): Response
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->whereIn('department_id', $departmentAccess->departmentIds($request->user()))
            ->with('department:id,code,name')
            ->get([
                'id',
                'code',
                'name',
                'department_id',
            ]);

        return Inertia::render(
            'Master/Product/Types/Create',
            [
                'categories' => $categories,
            ],
        );
    }

    public function store(TypeRequest $request, NumberingService $numberingService): RedirectResponse
    {
        $data = $request->validated();

        if (! $numberingService->hasActiveDocument(NumberingModuleCode::ProductType)) {
            throw ValidationException::withMessages([
                'code' => 'Configure an active PRODUCT_TYPE numbering rule before creating a type.',
            ]);
        }

        $data['code'] = mb_strtoupper(
            $numberingService->generate(NumberingModuleCode::ProductType),
            'UTF-8',
        );

        if (Type::query()->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Generated type code already exists. Update the numbering sequence start.',
            ]);
        }

        Type::create($data);

        return to_route('master.product.types.index')
            ->with('success', 'Asset Type was created successfully.');
    }

    public function edit(Request $request, Type $type, DepartmentAccessService $departmentAccess): Response
    {
        $categories = Category::query()
            ->with('department:id,code,name')
            ->where(function ($query) use ($type) {
                $query->where('is_active', true)
                    ->orWhere('id', $type->category_id);
            })
            ->whereIn('department_id', $departmentAccess->departmentIds($request->user()))
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'department_id',
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
