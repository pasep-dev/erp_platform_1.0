<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteAssetMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $maintenance = $this->route('maintenance');

        return $this->user()?->can('asset.update')
            && $maintenance instanceof AssetMaintenanceRecord
            && in_array($maintenance->status, [
                AssetMaintenanceRecord::STATUS_IN_PROGRESS,
                AssetMaintenanceRecord::STATUS_WAITING_PARTS,
                AssetMaintenanceRecord::STATUS_READY_TO_START,
            ], true);
    }

    public function rules(): array
    {
        return [
            'work_summary' => ['required', 'string', 'max:5000'],
            'completed_condition' => ['required', Rule::in(array_keys(Asset::CONDITION_OPTIONS))],
            'actual_cost' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'completed_at' => ['required', 'date_format:Y-m-d'],
            'next_service_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:completed_at'],
        ];
    }
}
