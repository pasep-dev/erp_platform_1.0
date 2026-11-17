<?php

namespace App\Http\Requests\Transaction;

use App\Models\Master\Procurement\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->map(function ($item) {
                $item['requires_po'] = filter_var(
                    $item['requires_po'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                );

                $item['tax_included'] = filter_var(
                    $item['tax_included'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                );

                return $item;
            })
            ->values()
            ->all();

        $this->merge([
            'items' => $items,
        ]);
    }

    public function rules(): array
    {
        return [
            'request_date' => [
                'required',
                'date',
            ],

            'purpose' => [
                'nullable',
                'string',
                'max:255',
            ],

            'remarks' => [
                'nullable',
                'string',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.item_type' => [
                'required',
                Rule::in([
                    'PRODUCT',
                    'SERVICE',
                ]),
            ],

            'items.*.product_id' => [
                'nullable',
                'string',
                'size:26',
                Rule::exists('mst_products', 'id')
                    ->where('is_active', true),
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $type = $this->input("items.{$index}.item_type");

                    if ($type === 'PRODUCT' && ! $value) {
                        $fail('Product wajib dipilih untuk item PRODUCT.');
                    }

                    if ($type === 'SERVICE' && $value) {
                        $fail('Product tidak boleh diisi untuk item SERVICE.');
                    }
                },
            ],

            'items.*.service_type_id' => [
                'nullable',
                'string',
                'size:26',
                Rule::exists('mst_service_types', 'id')
                    ->where('is_active', true),
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $type = $this->input("items.{$index}.item_type");

                    if ($type === 'SERVICE' && ! $value) {
                        $fail('Jenis service wajib dipilih untuk item SERVICE.');
                    }

                    if ($type === 'PRODUCT' && $value) {
                        $fail(
                            'Jenis service tidak boleh diisi untuk item PRODUCT.'
                        );
                    }
                },
            ],

            'items.*.asset_id' => [
                'nullable',
                'string',
                'size:26',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];

                    $type = $this->input(
                        "items.{$index}.item_type"
                    );

                    $serviceTypeId = $this->input(
                        "items.{$index}.service_type_id"
                    );

                    if ($type === 'PRODUCT' && $value) {
                        $fail(
                            'Asset tidak boleh diisi untuk item PRODUCT.'
                        );

                        return;
                    }

                    if (
                        $type !== 'SERVICE' ||
                        ! $serviceTypeId
                    ) {
                        return;
                    }

                    $serviceType = ServiceType::query()
                        ->whereKey($serviceTypeId)
                        ->where('is_active', true)
                        ->first();

                    if (
                        $serviceType?->requires_asset === true
                        && ! $value
                    ) {
                        $fail(
                            'Asset wajib dipilih untuk jenis service ini.'
                        );
                    }
                },
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
                Rule::exists('mst_units_of_measure', 'id')
                    ->where('is_active', true),
            ],

            'items.*.estimated_unit_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'items.*.vendor_id' => [
                'nullable',
                'string',
                'size:26',
                Rule::exists('mst_vendors', 'id')
                    ->where('is_active', true),
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
                'url',
            ],

            'items.*.requires_po' => [
                'boolean',
            ],

            'items.*.tax_included' => [
                'boolean',
            ],

            'items.*.required_date' => [
                'nullable',
                'date',
            ],

            'items.*.delivery_address' => [
                'nullable',
                'string',
                'max:500',
            ],

            'items.*.remarks' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'request_date.required' => 'Tanggal pengajuan wajib diisi.',

            'items.required' => 'Minimal satu item harus ditambahkan.',

            'items.min' => 'Minimal satu item harus ditambahkan.',

            'items.*.item_type.required' => 'Jenis item wajib dipilih.',

            'items.*.product_id.exists' => 'Product tidak ditemukan atau sudah tidak aktif.',

            'items.*.service_type_id.exists' => 'Jenis service tidak ditemukan atau sudah tidak aktif.',

            'items.*.quantity.required' => 'Quantity wajib diisi.',

            'items.*.quantity.gt' => 'Quantity harus lebih dari 0.',

            'items.*.uom_id.required' => 'UOM wajib dipilih.',

            'items.*.uom_id.exists' => 'UOM tidak ditemukan atau sudah tidak aktif.',

            'items.*.vendor_id.exists' => 'Vendor tidak ditemukan atau sudah tidak aktif.',

            'items.*.vendor_url.url' => 'URL vendor tidak valid.',
        ];
    }
}
