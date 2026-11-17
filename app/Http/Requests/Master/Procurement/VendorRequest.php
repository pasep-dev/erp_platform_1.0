<?php

namespace App\Http\Requests\Master\Procurement;

use App\Http\Requests\Master\AuthorizesMasterData;
use App\Models\Master\Procurement\Vendor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VendorRequest extends FormRequest
{
    use AuthorizesMasterData;

    public function rules(): array
    {
        $vendor = $this->route('vendor');

        $vendorId = $vendor instanceof Vendor
            ? $vendor->id
            : $vendor;

        $currentVendorTypeId = $vendor instanceof Vendor
            ? $vendor->vendor_type_id
            : null;

        return [
            'vendor_type_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('mst_vendor_types', 'id')
                    ->whereNull('deleted_at')
                    ->where(function ($query) use ($currentVendorTypeId) {
                        $query->where('is_active', true);

                        if ($currentVendorTypeId !== null) {
                            $query->orWhere('id', $currentVendorTypeId);
                        }
                    }),
            ],

            'code' => [
                $this->isMethod('POST') ? 'nullable' : 'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('mst_vendors', 'code')
                    ->ignore($vendorId)
                    ->whereNull('deleted_at'),
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'tax_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'email' => [
                'nullable',
                'email',
                'max:150',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'address' => [
                'nullable',
                'string',
                'max:2000',
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
            'vendor_type_id' => 'vendor type',
            'code' => 'vendor code',
            'name' => 'vendor name',
            'tax_number' => 'tax number',
            'email' => 'email',
            'phone' => 'phone',
            'address' => 'address',
            'description' => 'description',
            'is_active' => 'active status',
        ];
    }
}
