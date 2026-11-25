<?php

namespace App\Http\Requests\Master\Procurement;

use App\Http\Requests\Master\AuthorizesMasterData;
use App\Models\Master\Procurement\Tax;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxRequest extends FormRequest
{
    use AuthorizesMasterData;

    public function rules(): array
    {
        $tax = $this->route('tax');

        $taxId = $tax instanceof Tax
            ? $tax->id
            : $tax;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('mst_prc_taxes', 'code')
                    ->ignore($taxId)
                    ->whereNull('deleted_at'),
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'rate' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'category' => [
                'required',
                Rule::in(['ADDITIVE', 'WITHHOLDING']),
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
            'code' => 'tax code',
            'name' => 'tax name',
            'rate' => 'tax rate',
            'category' => 'tax category',
            'description' => 'description',
            'is_active' => 'active status',
        ];
    }
}
