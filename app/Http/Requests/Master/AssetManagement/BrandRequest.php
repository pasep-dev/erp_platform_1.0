<?php

namespace App\Http\Requests\Master\AssetManagement;

use App\Models\Master\AssetManagement\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

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
            'description' => 'description',
            'is_active' => 'active status',
        ];
    }
}
