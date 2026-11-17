<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use App\Models\Transaction\AssetManagement\AssetDisposal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetDisposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('asset.dispose') ?? false;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'ulid', 'exists:assets,id'],
            'disposal_type' => ['required', Rule::in(AssetDisposal::TYPES)],
            'disposal_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'reason' => ['required', 'string', 'min:5', 'max:10000'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }
}
