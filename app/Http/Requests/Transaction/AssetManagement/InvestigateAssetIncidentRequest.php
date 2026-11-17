<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use Illuminate\Foundation\Http\FormRequest;

class InvestigateAssetIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('asset.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'investigation_findings' => ['required', 'string', 'min:5', 'max:10000'],
        ];
    }
}
