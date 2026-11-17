<?php

namespace App\Http\Requests\Master\Product;

use App\Http\Requests\Master\AuthorizesMasterData;
use App\Models\Master\Product\Category;
use App\Models\Master\Product\Type;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TypeRequest extends FormRequest
{
    use AuthorizesMasterData;

    public function rules(): array
    {
        $type = $this->route('type');

        $typeId = $type instanceof Type
            ? $type->id
            : $type;

        return [
            'category_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('mst_asset_categories', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],

            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('mst_asset_types', 'code')
                    ->ignore($typeId)
                    ->where(
                        'category_id',
                        $this->input('category_id'),
                    )
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
            'code' => 'type code',
            'name' => 'type name',
            'description' => 'description',
            'is_active' => 'active status',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $categoryId = $this->input('category_id');

            if (! $categoryId) {
                return;
            }

            $category = Category::query()->find($categoryId);

            if (! $category) {
                return;
            }

            if (! $category->is_active) {
                $validator
                    ->errors()
                    ->add(
                        'category_id',
                        'The selected category is inactive.',
                    );
            }
        });
    }
}
