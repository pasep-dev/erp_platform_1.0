<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('asset.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'ulid', Rule::exists('assets', 'id')->where('status', Asset::STATUS_ACTIVE)],
            'maintenance_type' => ['required', Rule::in([AssetMaintenanceRecord::TYPE_PREVENTIVE, AssetMaintenanceRecord::TYPE_CORRECTIVE])],
            'execution_mode' => ['required', Rule::in([AssetMaintenanceRecord::EXECUTION_INTERNAL, AssetMaintenanceRecord::EXECUTION_VENDOR])],
            'priority' => ['required', Rule::in(array_keys(AssetMaintenanceRecord::PRIORITY_OPTIONS))],
            'requested_at' => ['required', 'date_format:Y-m-d'],
            'issue_description' => ['required', 'string', 'max:5000'],
            'reported_condition' => ['required', Rule::in(array_keys(Asset::CONDITION_OPTIONS))],
            'inspection_result' => ['nullable', 'string', 'max:5000'],
            'vendor_id' => ['nullable', 'required_if:execution_mode,VENDOR', 'ulid', Rule::exists('mst_vendors', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'estimated_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'scheduled_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:requested_at'],
            'components' => ['nullable', 'array', 'max:50'],
            'components.*' => ['array:component_name,quantity,estimated_unit_cost,needed_at'],
            'components.*.component_name' => ['required', 'string', 'max:150'],
            'components.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999'],
            'components.*.estimated_unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'components.*.needed_at' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
