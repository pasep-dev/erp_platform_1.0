<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use App\Models\Transaction\AssetManagement\AssetAuditItem;
use App\Services\BranchAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetAuditItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('asset.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'result' => ['required', Rule::in([
                AssetAuditItem::RESULT_FOUND,
                AssetAuditItem::RESULT_MOVED,
                AssetAuditItem::RESULT_DAMAGED,
                AssetAuditItem::RESULT_MISSING,
            ])],
            'observed_location_id' => ['nullable', 'required_if:result,MOVED', 'ulid', Rule::exists('mst_org_locations', 'id')->where('is_active', true)->whereNull('deleted_at')->whereIn('branch_id', app(BranchAccessService::class)->branchIds($this->user()))],
            'observed_condition' => ['nullable', 'required_if:result,DAMAGED', Rule::in(['GOOD', 'FAIR', 'NEEDS_REPAIR', 'DAMAGED'])],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
