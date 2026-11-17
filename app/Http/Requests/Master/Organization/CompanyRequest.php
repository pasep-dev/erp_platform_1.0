<?php

namespace App\Http\Requests\Master\Organization;

use App\Http\Requests\Master\AuthorizesMasterData;
use App\Models\Master\Organization\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    use AuthorizesMasterData;

    public function rules(): array
    {
        $company = $this->route('company');

        $companyId = $company instanceof Company
            ? $company->id
            : $company;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('mst_org_companies', 'code')
                    ->ignore($companyId)
                    ->whereNull('deleted_at'),
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'short_name' => [
                'nullable',
                'string',
                'max:100',
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
            'code' => 'company code',
            'name' => 'company name',
            'short_name' => 'short name',
            'is_active' => 'active status',
        ];
    }
}
