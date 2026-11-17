<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use Illuminate\Foundation\Http\FormRequest;

class ApproveAssetDisposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('asset.dispose') ?? false;
    }

    public function rules(): array
    {
        return [
            'decision_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
