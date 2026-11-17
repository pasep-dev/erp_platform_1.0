<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use App\Models\Transaction\AssetManagement\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('asset.transfer') ?? false;
    }

    public function rules(): array
    {
        return [
            'condition' => ['required', Rule::in(array_keys(Asset::CONDITION_OPTIONS))],
            'effective_at' => ['required', 'date_format:Y-m-d'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
