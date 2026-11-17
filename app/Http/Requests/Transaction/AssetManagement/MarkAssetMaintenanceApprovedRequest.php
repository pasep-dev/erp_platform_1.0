<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use Illuminate\Foundation\Http\FormRequest;

class MarkAssetMaintenanceApprovedRequest extends FormRequest
{
    public function authorize(): bool
    {
        $maintenance = $this->route('maintenance');
        $user = $this->user();

        return $user !== null
            && $maintenance instanceof AssetMaintenanceRecord
            && $maintenance->status === AssetMaintenanceRecord::STATUS_WAITING_APPROVAL
            && $user->can('asset.update');
    }

    public function rules(): array
    {
        return [
            'signed_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
