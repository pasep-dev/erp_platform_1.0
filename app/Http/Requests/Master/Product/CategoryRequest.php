<?php

namespace App\Http\Requests\Master\Product;

use App\Http\Requests\Master\AuthorizesMasterData;
use App\Services\DepartmentAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    use AuthorizesMasterData;

    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = is_object($category) ? $category->id : $category;

        return [
            'department_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('mst_org_departments', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
                Rule::in(app(DepartmentAccessService::class)->departmentIds($this->user())),
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
            'department_id' => 'department',
            'code' => 'category code',
            'name' => 'category name',
            'description' => 'description',
            'is_active' => 'active status',
        ];
    }
}
