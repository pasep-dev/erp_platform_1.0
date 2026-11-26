<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use App\Models\Transaction\AssetManagement\Asset;
use App\Services\BranchAccessService;
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
        $asset = Asset::query()->find($this->input('asset_id'));

        return [
            'asset_id' => ['required', 'ulid', 'exists:trx_assets,id'],
            'branch_id' => [
                'required',
                'ulid',
                Rule::exists('mst_org_branches', 'id')
                    ->where('company_id', $asset?->company_id)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
                Rule::in(app(BranchAccessService::class)->branchIds($this->user())),
            ],
            'effective_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'min:5', 'max:5000'],
        ];
    }
}
