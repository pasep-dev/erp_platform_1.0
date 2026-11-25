<?php

namespace App\Http\Requests\Transaction\Procurement;

use App\Models\Transaction\Procurement\GoodsReceipt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('good-receipt.create') === true;
    }

    public function rules(): array
    {
        return [
            'source_type' => ['required', Rule::in([
                GoodsReceipt::SOURCE_PURCHASE_ORDER,
                GoodsReceipt::SOURCE_PURCHASE_REQUEST,
            ])],
            'source_id' => ['required', 'string'],
            'vendor_id' => ['nullable', 'string', 'exists:mst_prc_vendors,id'],
            'warehouse_location_id' => ['nullable', 'string', 'size:26', 'exists:mst_org_locations,id'],
            'storage_location_id' => ['nullable', 'string', 'size:26', 'exists:mst_org_locations,id'],
            'received_at' => ['required', 'date'],
            'delivery_note_number' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.source_item_id' => ['required', 'string', 'distinct'],
            'items.*.quantity_received' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'items.*.quantity_accepted' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            'items.*.quantity_rejected' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            'items.*.remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($validator->errors()->any()) {
                return;
            }

            foreach ($this->input('items', []) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $received = (float) ($item['quantity_received'] ?? 0);
                $accepted = (float) ($item['quantity_accepted'] ?? 0);
                $rejected = (float) ($item['quantity_rejected'] ?? 0);

                if (round($accepted + $rejected, 3) !== round($received, 3)) {
                    $validator->errors()->add(
                        "items.{$index}.quantity_accepted",
                        'Jumlah diterima baik dan ditolak harus sama dengan kuantitas diterima.',
                    );
                }

                if ($rejected > 0 && blank($item['remarks'] ?? null)) {
                    $validator->errors()->add(
                        "items.{$index}.remarks",
                        'Catatan wajib diisi jika terdapat kuantitas yang ditolak.',
                    );
                }
            }
        }];
    }
}
