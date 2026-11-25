<?php

namespace App\Http\Requests\Operation\Procurement;

use App\Models\Operation\Procurement\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $purchaseRequest = $this->route('purchase_request');

        $purchaseRequestId = $purchaseRequest instanceof PurchaseRequest
            ? $purchaseRequest->id
            : $purchaseRequest;

        return [
            'request_date' => [
                'required',
                'date',
            ],

            'company_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('mst_companies', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],

            'branch_id' => [
                'nullable',
                'string',
                'size:26',
                Rule::exists('mst_branches', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],

            'department_id' => [
                'nullable',
                'string',
                'size:26',
                Rule::exists('mst_departments', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],

            'cost_center_id' => [
                'nullable',
                'string',
                'size:26',
                Rule::exists('mst_cost_centers', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],

            'required_date' => [
                'nullable',
                'date',
                'after_or_equal:request_date',
            ],

            'purpose' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.item_description' => [
                'required',
                'string',
                'max:1000',
            ],

            'items.*.management_class' => [
                'required',
                Rule::in([
                    'ASSET',
                    'CONSUMABLE',
                    'LICENSE',
                ]),
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'min:0.0001',
            ],

            'items.*.uom_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('mst_prc_uom', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],

            'items.*.estimated_unit_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'items.*.specification' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'items.*.notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'request_date' => 'request date',
            'company_id' => 'company',
            'branch_id' => 'branch',
            'department_id' => 'department',
            'cost_center_id' => 'cost center',
            'required_date' => 'required date',
            'purpose' => 'purpose',
            'description' => 'description',

            'items' => 'request items',
            'items.*.item_description' => 'item description',
            'items.*.management_class' => 'management class',
            'items.*.quantity' => 'quantity',
            'items.*.uom_id' => 'unit of measure',
            'items.*.estimated_unit_price' => 'estimated unit price',
            'items.*.specification' => 'specification',
            'items.*.notes' => 'notes',
        ];
    }
}
