<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use App\Services\DepartmentAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('asset.transfer') ?? false;
    }

    public function rules(): array
    {
        $companyId = $this->input('company_id');

        return [
            'asset_id' => ['required', 'ulid', 'exists:assets,id'],
            'company_id' => ['required', 'ulid', Rule::exists('mst_org_companies', 'id')->where('is_active', true)],
            'department_id' => ['required', 'ulid', Rule::exists('mst_org_departments', 'id')->where('company_id', $companyId)->where('is_active', true), Rule::in(app(DepartmentAccessService::class)->departmentIds($this->user()))],
            'location_id' => ['required', 'ulid', Rule::exists('mst_org_locations', 'id')->where('company_id', $companyId)->where('is_active', true)],
            'effective_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'min:5', 'max:5000'],
        ];
    }
}
