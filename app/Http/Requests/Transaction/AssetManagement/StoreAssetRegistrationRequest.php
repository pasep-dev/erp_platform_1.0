<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use App\Models\Master\Organization\Location;
use App\Models\User;
use App\Services\DepartmentAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAssetRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('registration.create') === true;
    }

    public function rules(): array
    {
        $isProcurementRegistration = $this->filled('goods_receipt_item_id');

        return [
            'goods_receipt_item_id' => ['nullable', 'string', 'exists:goods_receipt_items,id'],
            'product_id' => [$isProcurementRegistration ? 'prohibited' : 'required', 'string', Rule::exists('mst_products', 'id')->where('group', 'ASSET')->where('is_active', true)->whereNull('deleted_at'), Rule::exists('product_department', 'product_id')->where('department_id', $this->input('department_id'))],
            'company_id' => [$isProcurementRegistration ? 'prohibited' : 'required', 'string', Rule::exists('mst_org_companies', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'department_id' => [$isProcurementRegistration ? 'prohibited' : 'required', 'string', Rule::exists('mst_org_departments', 'id')->where('company_id', $this->input('company_id'))->where('is_active', true)->whereNull('deleted_at'), Rule::in(app(DepartmentAccessService::class)->departmentIds($this->user()))],
            'location_id' => ['required', 'string', 'exists:mst_org_locations,id'],
            'custodian_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'units' => ['required', 'array', 'min:1', 'max:500'],
            'units.*.serial_number' => ['nullable', 'string', 'max:150', 'distinct:ignore_case', Rule::unique('assets', 'serial_number')],
            'units.*.specifications' => ['nullable', 'array', 'max:50'],
            'units.*.specifications.*.name' => ['nullable', 'string', 'max:100'],
            'units.*.specifications.*.value' => ['nullable', 'string', 'max:500'],
            'units.*.warranty_years' => ['nullable', 'numeric', 'min:0.5', 'max:100', 'decimal:0,2'],
            'acquisition_date' => [$isProcurementRegistration ? 'prohibited' : 'required', 'date'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $units = $this->input('units', []);
            if (is_array($units)) {
                foreach ($units as $unitIndex => $unit) {
                    if (! is_array($unit)) {
                        continue;
                    }

                    foreach ($unit['specifications'] ?? [] as $specificationIndex => $specification) {
                        if (! is_array($specification)) {
                            continue;
                        }

                        $hasName = filled($specification['name'] ?? null);
                        $hasValue = filled($specification['value'] ?? null);

                        if ($hasName !== $hasValue) {
                            $validator->errors()->add(
                                "units.{$unitIndex}.specifications.{$specificationIndex}.value",
                                'Isi nama dan nilai spesifikasi, atau hapus baris spesifikasi tersebut.'
                            );
                        }
                    }

                    $warrantyYears = $unit['warranty_years'] ?? null;
                    if ($warrantyYears !== null && abs(((float) $warrantyYears * 12) - round((float) $warrantyYears * 12)) > 0.00001) {
                        $validator->errors()->add(
                            "units.{$unitIndex}.warranty_years",
                            'Masa garansi harus dapat dikonversi ke jumlah bulan penuh, misalnya 0,5 atau 1 tahun.'
                        );
                    }
                }
            }

            if ($this->filled('goods_receipt_item_id')) {
                return;
            }

            if (! $this->filled('company_id') || ! $this->filled('location_id')) {
                return;
            }

            $locationBelongsToCompany = Location::query()
                ->whereKey($this->input('location_id'))
                ->where('company_id', $this->input('company_id'))
                ->where('is_active', true)
                ->exists();

            if (! $locationBelongsToCompany) {
                $validator->errors()->add('location_id', 'Pilih lokasi aktif yang berada di perusahaan yang dipilih.');
            }

            if ($this->filled('custodian_id') && $this->filled('department_id')) {
                $custodian = User::query()->find($this->input('custodian_id'));
                $custodianBelongsToDepartment = $custodian !== null && (
                    $custodian->hasGlobalDepartmentAccess()
                    || $custodian->department_id === $this->input('department_id')
                    || $custodian->additionalDepartments()->whereKey($this->input('department_id'))->exists()
                );

                if (! $custodianBelongsToDepartment) {
                    $validator->errors()->add('custodian_id', 'Penanggung jawab harus memiliki akses ke department aset.');
                }
            }

            if ($this->filled('custodian_id')) {
                $custodianBelongsToCompany = User::query()
                    ->whereKey($this->input('custodian_id'))
                    ->where(function ($query): void {
                        $query->whereNull('company_id')
                            ->orWhere('company_id', $this->input('company_id'));
                    })
                    ->exists();

                if (! $custodianBelongsToCompany) {
                    $validator->errors()->add('custodian_id', 'Penanggung jawab harus berasal dari perusahaan yang dipilih.');
                }
            }
        }];
    }
}
