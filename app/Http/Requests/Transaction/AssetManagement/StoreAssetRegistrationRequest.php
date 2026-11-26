<?php

namespace App\Http\Requests\Transaction\AssetManagement;

use App\Models\Master\Organization\Branch;
use App\Models\Transaction\Procurement\GoodsReceiptItem;
use App\Services\BranchAccessService;
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
            'goods_receipt_item_id' => ['nullable', 'string', 'exists:trx_goods_receipt_items,id'],
            'product_id' => [$isProcurementRegistration ? 'prohibited' : 'required', 'string', Rule::exists('mst_products', 'id')->where('group', 'ASSET')->where('is_active', true)->whereNull('deleted_at'), Rule::exists('mst_product_departments', 'product_id')->where('department_id', $this->input('department_id'))],
            'company_id' => [$isProcurementRegistration ? 'prohibited' : 'required', 'string', Rule::exists('mst_org_companies', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'department_id' => [$isProcurementRegistration ? 'prohibited' : 'required', 'string', Rule::exists('mst_org_departments', 'id')->where('company_id', $this->input('company_id'))->where('is_active', true)->whereNull('deleted_at'), Rule::in(app(DepartmentAccessService::class)->departmentIds($this->user()))],
            'branch_id' => ['required', 'string', 'size:26', Rule::exists('mst_org_branches', 'id')->where('is_active', true)->whereNull('deleted_at'), Rule::in(app(BranchAccessService::class)->branchIds($this->user()))],
            'location_id' => ['prohibited'],
            'custodian_id' => ['prohibited'],
            'units' => ['required', 'array', 'min:1', 'max:500'],
            'units.*.serial_number' => ['nullable', 'string', 'max:150', 'distinct:ignore_case', Rule::unique('trx_assets', 'serial_number')],
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

            if (! $this->filled('branch_id')) {
                return;
            }

            $companyId = $this->input('company_id');

            if ($this->filled('goods_receipt_item_id')) {
                $companyId = GoodsReceiptItem::query()
                    ->with('goodsReceipt:id,company_id')
                    ->find($this->input('goods_receipt_item_id'))
                    ?->goodsReceipt?->company_id;
            }

            if ($companyId === null) {
                return;
            }

            $branchBelongsToCompany = Branch::query()
                ->withoutGlobalScope('branch_access')
                ->whereKey($this->input('branch_id'))
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->exists();

            if (! $branchBelongsToCompany) {
                $validator->errors()->add('branch_id', 'Pilih branch aktif yang berada di perusahaan aset.');
            }
        }];
    }
}
