<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use Illuminate\Foundation\Http\FormRequest;

class ResolveAssetAuditItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('asset.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'resolution_remarks' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
