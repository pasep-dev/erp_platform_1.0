<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveAssetIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('asset.update') ?? false;
    }

    public function rules(): array
    {
        $incident = $this->route('incident');
        $resolutionOptions = match ($incident?->incident_type) {
            AssetIncident::TYPE_DAMAGED => [AssetIncident::RESOLUTION_REPAIRED, AssetIncident::RESOLUTION_MONITOR, AssetIncident::RESOLUTION_NO_ISSUE],
            AssetIncident::TYPE_LOST, AssetIncident::TYPE_STOLEN => [AssetIncident::RESOLUTION_MISSING_CONFIRMED, AssetIncident::RESOLUTION_RECOVERED, AssetIncident::RESOLUTION_NO_ISSUE],
            default => [AssetIncident::RESOLUTION_MONITOR, AssetIncident::RESOLUTION_NO_ISSUE],
        };

        return [
            'resolution_type' => ['required', Rule::in($resolutionOptions)],
            'resolution_notes' => ['required', 'string', 'min:5', 'max:10000'],
            'condition_after' => [
                Rule::requiredIf($incident?->incident_type === AssetIncident::TYPE_DAMAGED),
                'nullable',
                Rule::in(array_keys(Asset::CONDITION_OPTIONS)),
            ],
        ];
    }
}
