<?php

namespace App\Http\Requests\Master\Procurement;

use App\Models\Master\Procurement\ProcurementChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcurementChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $procurementChannel = $this->route('procurement_channel');

        $procurementChannelId = $procurementChannel instanceof ProcurementChannel
            ? $procurementChannel->id
            : $procurementChannel;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('mst_procurement_channels', 'code')
                    ->ignore($procurementChannelId)
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
            'code' => 'procurement channel code',
            'name' => 'procurement channel name',
            'description' => 'description',
            'is_active' => 'active status',
        ];
    }
}
