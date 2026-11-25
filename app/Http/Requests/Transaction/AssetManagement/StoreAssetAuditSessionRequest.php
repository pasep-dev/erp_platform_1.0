<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use App\Services\BranchAccessService;
use App\Services\DepartmentAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetAuditSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('asset.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'ulid', Rule::exists('mst_org_companies', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'department_id' => ['required', 'ulid', Rule::exists('mst_org_departments', 'id')->where('is_active', true)->whereNull('deleted_at'), Rule::in(app(DepartmentAccessService::class)->departmentIds($this->user()))],
            'location_id' => [Rule::requiredIf(! $this->user()?->hasGlobalBranchAccess()), 'nullable', 'ulid', Rule::exists('mst_org_locations', 'id')->where('is_active', true)->whereNull('deleted_at')->whereIn('branch_id', app(BranchAccessService::class)->branchIds($this->user()))],
            'category_id' => ['nullable', 'ulid', Rule::exists('mst_product_categories', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
