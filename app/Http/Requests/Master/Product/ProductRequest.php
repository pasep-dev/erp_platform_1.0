<?php

namespace App\Http\Requests\Master\Product;

use App\Http\Requests\Master\AuthorizesMasterData;
use App\Models\Master\Product\Category;
use App\Models\Master\Product\Product;
use App\Services\DepartmentAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    use AuthorizesMasterData;

    protected function prepareForValidation(): void
    {
        $this->normalizeMasterDataCode();

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
        $categoryId = $this->input('category_id');
        $rootCategoryId = Category::query()
            ->whereKey($categoryId)
            ->value('parent_id') ?: $categoryId;

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
            'category_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('mst_product_categories', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],

            'type_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('mst_product_types', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->where('category_id', $this->input('category_id')),
            ],

            'brand_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('mst_product_brands', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
                Rule::exists('mst_product_brand_categories', 'brand_id')
                    ->whereIn('category_id', array_filter([$rootCategoryId, $categoryId])),
            ],

            'model_id' => [
                'nullable',
                'string',
                'size:26',
                Rule::exists('mst_product_models', 'id')
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
                Rule::exists('mst_prc_uom', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],

            'code' => $this->isMethod('POST')
                ? ['prohibited']
                : [
                    'required',
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

            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
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
            'image' => 'product image',
            'is_active' => 'active status',
        ];
    }
}
