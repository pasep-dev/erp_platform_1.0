<?php

namespace App\Http\Requests\Master\Product;

use App\Http\Requests\Master\AuthorizesMasterData;
use App\Models\Master\Product\LicenseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LicenseTypeRequest extends FormRequest
{
    use AuthorizesMasterData;

    public function rules(): array
    {
        $licenseType = $this->route('license_type');

        $licenseTypeId = $licenseType instanceof LicenseType
            ? $licenseType->id
            : $licenseType;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('mst_asset_license_types', 'code')
                    ->ignore($licenseTypeId)
                    ->whereNull('deleted_at'),
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'license type code',
            'name' => 'license type name',
            'description' => 'description',
            'is_active' => 'active status',
        ];
    }
}
