<?php

namespace App\Http\Requests\Master\Organization;

use App\Http\Requests\Master\AuthorizesMasterData;
use App\Models\Master\Organization\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    use AuthorizesMasterData;

    public function rules(): array
    {
        $location = $this->route('location');

        $locationId = $location instanceof Location
            ? $location->id
            : $location;

        return [
            'company_id' => [
                'required',
                'exists:mst_org_companies,id',
            ],

            'branch_id' => [
                'nullable',
                Rule::exists('mst_org_branches', 'id')
                    ->where(function ($query) {
                        $query->where(
                            'company_id',
                            $this->input('company_id')
                        );
                    })
                    ->whereNull('deleted_at'),
            ],

            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('mst_org_locations', 'code')
                    ->ignore($locationId)
                    ->where('company_id', $this->input('company_id'))
                    ->whereNull('deleted_at'),
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'parent_id' => [
                'nullable',
                'string',
                'size:26',
                Rule::exists('mst_org_locations', 'id')
                    ->where(function ($query) {
                        $query->where(
                            'company_id',
                            $this->input('company_id')
                        )->where(function ($branchQuery) {
                            $branchId = $this->input('branch_id');

                            if ($branchId) {
                                $branchQuery->whereNull('branch_id')
                                    ->orWhere('branch_id', $branchId);
                            } else {
                                $branchQuery->whereNull('branch_id');
                            }
                        });
                    })
                    ->whereNull('deleted_at')
                    ->where(function ($query) use ($locationId) {
                        if ($locationId) {
                            $query->where('id', '!=', $locationId);
                        }
                    }),
            ],

            'location_type' => [
                'nullable',
                Rule::in(array_keys(Location::TYPE_OPTIONS)),
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
            'company_id' => 'company',
            'branch_id' => 'branch',
            'code' => 'location code',
            'name' => 'location name',
            'parent_id' => 'parent location',
            'location_type' => 'location type',
            'is_active' => 'active status',
        ];
    }
}
