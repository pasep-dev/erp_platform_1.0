<?php

namespace App\Http\Requests\Master\Product;

use App\Http\Requests\Master\AuthorizesMasterData;
use App\Models\Master\Product\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CategoryRequest extends FormRequest
{
    use AuthorizesMasterData;

    public function rules(): array
    {
        $category = $this->route('category');

        $categoryId = $category instanceof Category
            ? $category->id
            : $category;

        return [
            'parent_id' => [
                'nullable',
                'string',
                'size:26',
                Rule::exists('mst_product_categories', 'id')
                    ->whereNull('parent_id')
                    ->whereNull('deleted_at'),
                Rule::notIn([$categoryId]),
            ],

            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('mst_product_categories', 'code')
                    ->ignore($categoryId)
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
            'parent_id' => 'parent category',
            'code' => 'category code',
            'name' => 'category name',
            'description' => 'description',
            'is_active' => 'active status',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $category = $this->route('category');

            if (! $category instanceof Category) {
                return;
            }

            $parentId = $this->input('parent_id');

            if (! $parentId) {
                return;
            }

            $parent = Category::query()
                ->find($parentId);

            if (! $parent) {
                return;
            }

            $visited = [];

            while ($parent) {
                if (isset($visited[$parent->id])) {
                    break;
                }

                $visited[$parent->id] = true;

                if ($parent->id === $category->id) {
                    $validator
                        ->errors()
                        ->add(
                            'parent_id',
                            'The selected parent category would create a circular hierarchy.'
                        );

                    break;
                }

                if (! $parent->parent_id) {
                    break;
                }

                $parent = Category::query()
                    ->find($parent->parent_id);
            }
        });
    }
}
