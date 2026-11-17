<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use Illuminate\Foundation\Http\FormRequest;

class MarkAssetMaintenanceRejectedRequest extends FormRequest
{
    public function authorize(): bool
    {
        $maintenance = $this->route('maintenance');

        return $this->user()?->can('asset.update')
            && $maintenance instanceof AssetMaintenanceRecord
            && $maintenance->status === AssetMaintenanceRecord::STATUS_WAITING_APPROVAL;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
