<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use Illuminate\Foundation\Http\FormRequest;

class CancelAssetMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $maintenance = $this->route('maintenance');
        $user = $this->user();

        return $user !== null
            && $maintenance instanceof AssetMaintenanceRecord
            && $user->can('asset.update')
            && $maintenance->status === AssetMaintenanceRecord::STATUS_REQUESTED;
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
