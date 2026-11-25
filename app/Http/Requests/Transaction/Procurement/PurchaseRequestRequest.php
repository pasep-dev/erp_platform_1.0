<?php

namespace App\Http\Requests\Transaction\Procurement;

use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use App\Services\BranchAccessService;
use App\Services\DepartmentAccessService;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    public function rules(): array
    {
        return [
            'company_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('mst_org_companies', 'id')->where(
                    fn (Builder $query) => $query
                        ->where('is_active', true)
                        ->whereNull('deleted_at'),
                ),
            ],

            'department_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('mst_org_departments', 'id')->where(
                    fn (Builder $query) => $query
                        ->where('company_id', $this->input('company_id'))
                        ->where('is_active', true)
                        ->whereNull('deleted_at'),
                ),
                Rule::in(app(DepartmentAccessService::class)->departmentIds($this->user())),
            ],

            'request_date' => [
                'required',
                'date',
            ],

            'purpose' => [
                'required',
                'string',
                'max:255',
            ],

            'remarks' => [
                'nullable',
                'string',
            ],

            'asset_maintenance_record_id' => [
                'nullable',
                'ulid',
                Rule::exists('trx_asset_maintenance_records', 'id')->where(
                    fn (Builder $query) => $query->where('status', AssetMaintenanceRecord::STATUS_WAITING_PARTS),
                ),
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.item_type' => [
                'required',
                Rule::in(['PRODUCT', 'SERVICE']),
            ],

            'items.*.product_id' => [
                'nullable',
                'required_if:items.*.item_type,PRODUCT',
                'string',
                'size:26',
                'exists:mst_products,id',
                Rule::exists('mst_product_departments', 'product_id')->where('department_id', $this->input('department_id')),
            ],

            'items.*.service_type_id' => [
                'nullable',
                'required_if:items.*.item_type,SERVICE',
                'string',
                'size:26',
                Rule::exists('mst_service_types', 'id')->where(
                    fn (Builder $query) => $query
                        ->where('is_active', true)
                        ->whereNull('deleted_at'),
                ),
            ],

            'items.*.asset_id' => [
                'nullable',
                'string',
                'size:26',
            ],

            'items.*.description' => [
                'nullable',
                'string',
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'items.*.uom_id' => [
                'required',
                'string',
                'size:26',
                'exists:mst_prc_uom,id',
            ],

            'items.*.estimated_unit_price' => [
                'nullable',
                'numeric',
                'gte:0',
            ],

            'items.*.vendor_id' => [
                'nullable',
                'string',
                'size:26',
                'exists:mst_prc_vendors,id',
            ],

            'items.*.vendor_pic' => [
                'nullable',
                'string',
                'max:150',
            ],

            'items.*.vendor_contact' => [
                'nullable',
                'string',
                'max:100',
            ],

            'items.*.vendor_url' => [
                'nullable',
                'string',
                'max:500',
            ],

            'items.*.requires_po' => [
                'boolean',
            ],

            'items.*.tax_included' => [
                'boolean',
            ],

            'items.*.tax_id' => [
                'nullable',
                'string',
                'size:26',
                Rule::exists('mst_prc_taxes', 'id')->where(
                    fn (Builder $query) => $query
                        ->where('is_active', true)
                        ->whereNull('deleted_at'),
                ),
            ],

            'items.*.required_date' => [
                'nullable',
                'date',
            ],

            'items.*.remarks' => [
                'nullable',
                'string',
            ],

            'items.*.delivery_branch_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('mst_org_branches', 'id')->where(
                    fn (Builder $query) => $query
                        ->where('company_id', $this->input('company_id'))
                        ->where('is_active', true)
                        ->whereNull('deleted_at'),
                ),
                Rule::in(app(BranchAccessService::class)->branchIds($this->user())),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'request_date' => 'tanggal request',
            'company_id' => 'perusahaan',
            'department_id' => 'departemen',
            'purpose' => 'keperluan',
            'remarks' => 'catatan',
            'items' => 'item PR',
            'items.*.product_id' => 'produk',
            'items.*.service_type_id' => 'jenis jasa',
            'items.*.description' => 'deskripsi',
            'items.*.quantity' => 'jumlah',
            'items.*.uom_id' => 'satuan',
            'items.*.estimated_unit_price' => 'estimasi harga satuan',
            'items.*.vendor_id' => 'vendor',
            'items.*.tax_id' => 'tax',
            'items.*.required_date' => 'tanggal kebutuhan',
        ];
    }
}
