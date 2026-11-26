<?php

namespace App\Http\Requests\Master\Product;

use App\Http\Requests\Master\AuthorizesMasterData;
use App\Models\Master\Product\Model as ProductModel;
use App\Models\Master\Product\Type as ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModelRequest extends FormRequest
{
    use AuthorizesMasterData;

    public function rules(): array
    {
        $model = $this->route('model');

        $modelId = $model instanceof ProductModel
            ? $model->id
            : $model;
        $categoryId = ProductType::query()
            ->whereKey($this->input('type_id'))
            ->value('category_id');

        return [
            'brand_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('mst_product_brands', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
                Rule::exists('mst_product_brand_categories', 'brand_id')
                    ->where('category_id', $categoryId),
            ],

            'type_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('mst_product_types', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],

            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('mst_product_models', 'code')
                    ->ignore($modelId)
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
            'brand_id' => 'brand',
            'type_id' => 'type',
            'code' => 'model code',
            'name' => 'model name',
            'description' => 'description',
            'is_active' => 'active status',
        ];
    }
}
