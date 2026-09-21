<?php

namespace App\Http\Requests\Master\AssetManagement;

use App\Models\Master\AssetManagement\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $model = $this->route('model');

        $modelId = $model instanceof Model
            ? $model->id
            : $model;

        return [
            'brand_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('mst_asset_brands', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],

            'type_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('mst_asset_types', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],

            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('mst_asset_models', 'code')
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
