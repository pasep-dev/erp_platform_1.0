<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use Illuminate\Foundation\Http\FormRequest;

class ScanAssetAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('asset.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'asset_number' => ['required', 'string', 'max:100'],
        ];
    }
}
