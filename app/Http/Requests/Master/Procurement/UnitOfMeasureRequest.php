<?php

namespace App\Http\Requests\Master\Procurement;

use App\Http\Requests\Master\AuthorizesMasterData;
use App\Models\Master\Procurement\UnitOfMeasure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitOfMeasureRequest extends FormRequest
{
    use AuthorizesMasterData;

    public function rules(): array
    {
        $unitOfMeasure = $this->route('unit_of_measure');

        $unitOfMeasureId = $unitOfMeasure instanceof UnitOfMeasure
            ? $unitOfMeasure->id
            : $unitOfMeasure;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('mst_prc_uom', 'code')
                    ->ignore($unitOfMeasureId)
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
            'code' => 'unit of measure code',
            'name' => 'unit of measure name',
            'description' => 'description',
            'is_active' => 'active status',
        ];
    }
}
