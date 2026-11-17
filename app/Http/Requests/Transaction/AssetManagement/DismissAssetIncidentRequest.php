<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use Illuminate\Foundation\Http\FormRequest;

class DismissAssetIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('asset.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'dismissal_reason' => ['required', 'string', 'min:5', 'max:10000'],
        ];
    }
}
