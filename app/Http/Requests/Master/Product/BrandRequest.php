<?php

namespace App\Http\Requests\Master\Product;

use App\Http\Requests\Master\AuthorizesMasterData;
use App\Models\Master\Product\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    use AuthorizesMasterData;

    public function rules(): array
    {
        $brand = $this->route('brand');

        $brandId = $brand instanceof Brand
            ? $brand->id
            : $brand;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('mst_asset_brands', 'code')
                    ->ignore($brandId)
                    ->whereNull('deleted_at'),
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'category_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'category_ids.*' => [
                'required',
                'string',
                'size:26',
                'distinct',
                Rule::exists('mst_asset_categories', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
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
            'code' => 'brand code',
            'name' => 'brand name',
            'category_ids' => 'categories',
            'category_ids.*' => 'category',
            'description' => 'description',
            'is_active' => 'active status',
        ];
    }
}
