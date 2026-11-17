<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use App\Models\Transaction\AssetManagement\Asset;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('asset.transfer') ?? false;
    }

    public function rules(): array
    {
        $asset = $this->route('asset');
        abort_unless($asset instanceof Asset, 404);

        return [
            'condition' => ['required', Rule::in(array_keys(Asset::CONDITION_OPTIONS))],
            'assignee_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('is_active', true)
                    ->where(static function (QueryBuilder $query) use ($asset): void {
                        $query->whereNull('company_id')
                            ->orWhere('company_id', $asset->company_id);
                    }),
            ],
            'effective_at' => ['required', 'date_format:Y-m-d'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
