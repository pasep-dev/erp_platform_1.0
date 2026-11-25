<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use Illuminate\Foundation\Http\FormRequest;

class ImportAssetDirectoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('registration.create') ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'extensions:csv', 'max:10240'],
        ];
    }
}
