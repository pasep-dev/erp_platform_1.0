<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('asset.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'ulid', 'exists:assets,id'],
            'incident_type' => ['required', Rule::in(AssetIncident::TYPES)],
            'severity' => ['required', Rule::in(['LOW', 'NORMAL', 'HIGH', 'URGENT'])],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'reported_condition' => ['nullable', Rule::in(array_keys(Asset::CONDITION_OPTIONS))],
            'description' => ['required', 'string', 'min:5', 'max:10000'],
            'involved_parties' => ['nullable', 'string', 'max:5000'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }
}
