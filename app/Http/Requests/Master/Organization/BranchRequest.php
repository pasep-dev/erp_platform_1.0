<?php

namespace App\Http\Requests\Master\Organization;

use App\Http\Requests\Master\AuthorizesMasterData;
use App\Models\Master\Organization\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    use AuthorizesMasterData;

    public function rules(): array
    {
        $branch = $this->route('branch');

        $branchId = $branch instanceof Branch
            ? $branch->id
            : $branch;

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
                Rule::unique('mst_org_branches', 'code')
                    ->ignore($branchId)
                    ->where('company_id', $this->input('company_id'))
                    ->whereNull('deleted_at'),
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'address' => [
                'nullable',
                'string',
                'max:255',
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
            'code' => 'branch code',
            'name' => 'branch name',
            'address' => 'address',
            'is_active' => 'active status',
        ];
    }
}
