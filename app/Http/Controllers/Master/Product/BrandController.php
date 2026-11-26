<?php

namespace App\Http\Controllers\Master\Product;

use App\Enums\NumberingModuleCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Product\BrandRequest;
use App\Http\Requests\Master\Product\ImportBrandsRequest;
use App\Models\Master\Product\Brand;
use App\Models\Master\Product\Category;
use App\Services\DepartmentAccessService;
use App\Services\Master\Product\BrandSpreadsheetService;
use App\Services\NumberingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandController extends Controller
{
    public function template(Request $request, DepartmentAccessService $departmentAccess, BrandSpreadsheetService $spreadsheet): BinaryFileResponse
    {
        $categories = Category::query()
            ->with('department:id,code,name')
            ->where('is_active', true)
            ->whereIn('department_id', $departmentAccess->departmentIds($request->user()))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'department_id']);

        $filePath = $spreadsheet->createTemplate($categories);

        return response()
            ->download($filePath, 'brand-import-template.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function import(
        ImportBrandsRequest $request,
        DepartmentAccessService $departmentAccess,
        BrandSpreadsheetService $spreadsheet,
        NumberingService $numberingService,
    ): RedirectResponse {
        if (! $numberingService->hasActiveDocument(NumberingModuleCode::ProductBrand)) {
            throw ValidationException::withMessages([
                'file' => 'Configure an active PRODUCT_BRAND numbering rule before importing brands.',
            ]);
        }

        try {
            $rows = $spreadsheet->readBrands($request->file('file')->getPathname());
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'The Excel sheet is empty.']);
        }

        $headers = array_map('strtolower', array_values($rows[0]['cells']));
        $headerIndexes = array_flip($headers);
        $requiredHeaders = ['name', 'description', 'category_codes', 'is_active'];
        if (array_diff($requiredHeaders, $headers) !== []) {
            throw ValidationException::withMessages([
                'file' => 'The Brands sheet must contain these columns: name, description, category_codes, is_active.',
            ]);
        }

        $dataRows = array_slice($rows, 1);
        if (count($dataRows) > 500) {
            throw ValidationException::withMessages(['file' => 'A maximum of 500 brands can be imported at once.']);
        }

        $accessibleDepartmentIds = $departmentAccess->departmentIds($request->user());
        $categoriesByCode = Category::query()
            ->where('is_active', true)
            ->whereIn('department_id', $accessibleDepartmentIds)
            ->get(['id', 'code'])
            ->keyBy(fn (Category $category): string => mb_strtoupper($category->code, 'UTF-8'));

        $validatedRows = [];
        $errors = [];
        foreach ($dataRows as $row) {
            $cells = $row['cells'];
            $value = static fn (string $header): string => trim((string) ($cells[$headerIndexes[$header]] ?? ''));
            $name = $value('name');
            $description = $value('description');
            $categoryCodes = array_values(array_unique(array_filter(array_map(
                static fn (string $code): string => trim($code),
                explode('|', $value('category_codes')),
            ))));
            $activeValue = mb_strtolower($value('is_active'), 'UTF-8');
            $rowErrors = [];

            if ($name === '' || mb_strlen($name) > 150) {
                $rowErrors[] = 'name is required and must not exceed 150 characters';
            }
            if (mb_strlen($description) > 1000) {
                $rowErrors[] = 'description must not exceed 1000 characters';
            }
            if ($categoryCodes === []) {
                $rowErrors[] = 'category_codes must contain at least one category code, separated with |';
            }
            if (! in_array($activeValue, ['true', 'false', '1', '0'], true)) {
                $rowErrors[] = 'is_active must be TRUE or FALSE';
            }

            $categoryIds = [];
            foreach ($categoryCodes as $categoryCode) {
                $category = $categoriesByCode->get(mb_strtoupper($categoryCode, 'UTF-8'));
                if ($category === null) {
                    $rowErrors[] = "category code [{$categoryCode}] is inactive, unavailable, or does not exist";

                    continue;
                }
                $categoryIds[] = (string) $category->id;
            }

            if ($rowErrors !== []) {
                $errors["row_{$row['row_number']}"] = 'Row '.$row['row_number'].': '.implode('; ', $rowErrors).'.';

                continue;
            }

            $validatedRows[] = [
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'category_ids' => $categoryIds,
                'is_active' => in_array($activeValue, ['true', '1'], true),
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        if ($validatedRows === []) {
            throw ValidationException::withMessages(['file' => 'No brand data rows were found in the Brands sheet.']);
        }

        DB::transaction(function () use ($validatedRows, $numberingService): void {
            foreach ($validatedRows as $row) {
                $code = mb_strtoupper($numberingService->generate(NumberingModuleCode::ProductBrand), 'UTF-8');
                if (Brand::withTrashed()->where('code', $code)->exists()) {
                    throw ValidationException::withMessages([
                        'file' => 'Generated brand code already exists. Update the numbering sequence start.',
                    ]);
                }

                $categoryIds = $row['category_ids'];
                unset($row['category_ids']);
                $brand = Brand::query()->create(['code' => $code, ...$row]);
                $brand->categories()->sync($categoryIds);
            }
        });

        return to_route('master.product.brands.index')
            ->with('success', count($validatedRows).' brands were imported successfully.');
    }

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $brands = Brand::query()
            ->with('categories:id,code,name,department_id')
            ->with('categories.department:id,code,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render(
            'Master/Product/Brands/Index',
            [
                'brands' => $brands,
                'filters' => [
                    'search' => $search,
                ],
            ],
        );
    }

    public function create(Request $request, DepartmentAccessService $departmentAccess): Response
    {
        return Inertia::render(
            'Master/Product/Brands/Create',
            [
                'categories' => Category::query()
                    ->where('is_active', true)
                    ->whereIn('department_id', $departmentAccess->departmentIds($request->user()))
                    ->with('department:id,code,name')
                    ->orderBy('name')
                    ->get(['id', 'code', 'name', 'department_id']),
            ],
        );
    }

    public function store(BrandRequest $request, NumberingService $numberingService): RedirectResponse
    {
        $data = $request->validated();
        $categoryIds = $data['category_ids'];
        unset($data['category_ids']);

        if (! $numberingService->hasActiveDocument(NumberingModuleCode::ProductBrand)) {
            throw ValidationException::withMessages([
                'code' => 'Configure an active PRODUCT_BRAND numbering rule before creating a brand.',
            ]);
        }

        $data['code'] = mb_strtoupper(
            $numberingService->generate(NumberingModuleCode::ProductBrand),
            'UTF-8',
        );

        if (Brand::withTrashed()->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Generated brand code already exists. Update the numbering sequence start.',
            ]);
        }

        DB::transaction(function () use ($data, $categoryIds): void {
            $brand = Brand::create($data);
            $brand->categories()->sync($categoryIds);
        });

        return to_route('master.product.brands.index')
            ->with('success', 'Asset Brand was created successfully.');
    }

    public function edit(Request $request, Brand $brand, DepartmentAccessService $departmentAccess): Response
    {
        $brand->load('categories:id,code,name,department_id', 'categories.department:id,code,name');

        return Inertia::render(
            'Master/Product/Brands/Edit',
            [
                'brand' => $brand,
                'categories' => Category::query()
                    ->where('is_active', true)
                    ->whereIn('department_id', $departmentAccess->departmentIds($request->user()))
                    ->with('department:id,code,name')
                    ->orderBy('name')
                    ->get(['id', 'code', 'name', 'department_id']),
            ],
        );
    }

    public function update(
        BrandRequest $request,
        Brand $brand,
    ): RedirectResponse {
        $data = $request->validated();
        $categoryIds = $data['category_ids'];
        unset($data['category_ids']);

        DB::transaction(function () use ($brand, $data, $categoryIds): void {
            $brand->update($data);
            $brand->categories()->sync($categoryIds);
        });

        return to_route('master.product.brands.index')
            ->with('success', 'Asset Brand was updated successfully.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $brand->delete();

        return to_route('master.product.brands.index')
            ->with('success', 'Asset Brand was deleted successfully.');
    }
}
