<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use Illuminate\Foundation\Http\FormRequest;

class RejectAssetDisposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('asset.dispose') ?? false;
    }

    public function rules(): array
    {
        return [
            'decision_notes' => ['required', 'string', 'min:5', 'max:5000'],
        ];
    }
}
