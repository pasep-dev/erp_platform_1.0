<?php

namespace App\Http\Requests\Master\Organization;

use App\Models\Master\Organization\CostCenter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $costCenter = $this->route('cost_center');

        $costCenterId = $costCenter instanceof CostCenter
            ? $costCenter->id
            : $costCenter;

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
                Rule::unique('mst_org_cost_centers', 'code')
                    ->ignore($costCenterId)
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
            'code' => 'cost center code',
            'name' => 'cost center name',
            'is_active' => 'active status',
        ];
    }
}
