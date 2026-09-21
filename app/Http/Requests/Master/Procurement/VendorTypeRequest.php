<?php

namespace App\Http\Requests\Master\Procurement;

use App\Models\Master\Procurement\VendorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VendorTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $vendorType = $this->route('vendor_type');

        $vendorTypeId = $vendorType instanceof VendorType
            ? $vendorType->id
            : $vendorType;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('mst_vendor_types', 'code')
                    ->ignore($vendorTypeId)
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
            'code' => 'vendor type code',
            'name' => 'vendor type name',
            'description' => 'description',
            'is_active' => 'active status',
        ];
    }
}
