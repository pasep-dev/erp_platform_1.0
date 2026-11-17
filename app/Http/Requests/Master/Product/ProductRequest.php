<?php

namespace App\Http\Requests\Master\Product;

use App\Http\Requests\Master\AuthorizesMasterData;
use App\Models\Master\Product\Product;
use App\Services\BranchAccessService;
use App\Services\DepartmentAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    use AuthorizesMasterData;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'inventory_tracking_mode' => $this->input('group') === 'CONSUMABLE'
                ? $this->input('inventory_tracking_mode', 'QUANTITY')
                : 'QUANTITY',
        ]);
    }

    public function rules(): array
    {
        $product = $this->route('product');

        $productId = $product instanceof Product
            ? $product->id
            : $product;

        return [
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => [
                'required',
                'string',
                'size:26',
                'distinct',
                Rule::exists('mst_org_departments', 'id')->where('is_active', true)->whereNull('deleted_at'),
                Rule::in(app(DepartmentAccessService::class)->departmentIds($this->user())),
            ],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => [
                'required', 'string', 'size:26', 'distinct',
                Rule::exists('mst_org_branches', 'id')->where('is_active', true)->whereNull('deleted_at'),
                Rule::in(app(BranchAccessService::class)->branchIds($this->user())),
            ],
            'category_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('mst_asset_categories', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],

            'type_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('mst_asset_types', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->where('category_id', $this->input('category_id')),
            ],

            'brand_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('mst_asset_brands', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
                Rule::exists('mst_asset_brand_categories', 'brand_id')
                    ->where('category_id', $this->input('category_id')),
            ],

            'model_id' => [
                'nullable',
                'string',
                'size:26',
                Rule::exists('mst_asset_models', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->where('brand_id', $this->input('brand_id'))
                    ->where('type_id', $this->input('type_id')),
            ],

            'group' => [
                'required',
                Rule::in([
                    'ASSET',
                    'CONSUMABLE',
                    'LICENSE',
                ]),
            ],

            'inventory_tracking_mode' => [
                'required',
                Rule::in(['QUANTITY', 'SERIAL']),
            ],

            'base_uom_id' => [
                Rule::requiredIf($this->input('group') === 'CONSUMABLE'),
                'nullable',
                'string',
                'size:26',
                Rule::exists('mst_units_of_measure', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],

            'code' => [
                $this->isMethod('POST') ? 'nullable' : 'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('mst_products', 'code')
                    ->ignore($productId)
                    ->whereNull('deleted_at'),
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'category_id' => 'category',
            'type_id' => 'type',
            'brand_id' => 'brand',
            'model_id' => 'model',
            'group' => 'group',
            'inventory_tracking_mode' => 'inventory tracking method',
            'code' => 'product code',
            'name' => 'product name',
            'description' => 'description',
            'is_active' => 'active status',
        ];
    }
}
