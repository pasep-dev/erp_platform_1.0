<?php

namespace App\Http\Requests\Master\Organization;

use App\Models\Master\Organization\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $department = $this->route('department');

        $departmentId = $department instanceof Department
            ? $department->id
            : $department;

        return [
            'company_id' => [
                'required',
                'exists:mst_org_companies,id',
            ],

            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('mst_org_departments', 'code')
                    ->ignore($departmentId)
                    ->where('company_id', $this->input('company_id'))
                    ->whereNull('deleted_at'),
            ],

            'name' => [
                'required',
                'string',
                'max:150',
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
            'company_id' => 'company',
            'code' => 'department code',
            'name' => 'department name',
            'is_active' => 'active status',
        ];
    }
}
