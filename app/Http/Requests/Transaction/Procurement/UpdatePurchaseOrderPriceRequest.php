<?php

namespace App\Http\Requests\Transaction\Procurement;

use App\Models\Transaction\Procurement\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseOrderPriceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('purchase-order.update') === true
            && $this->route('purchaseOrder') instanceof PurchaseOrder
            && $this->route('purchaseOrder')->status === 'DRAFT';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $purchaseOrder = $this->route('purchaseOrder');

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => [
                'required',
                'string',
                'distinct',
                Rule::exists('trx_purchase_order_items', 'id')
                    ->where('purchase_order_id', $purchaseOrder?->id),
            ],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'items.*.reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $purchaseOrder = $this->route('purchaseOrder');
            if (! $purchaseOrder instanceof PurchaseOrder) {
                return;
            }

            foreach ($this->input('items', []) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $orderItem = $purchaseOrder->items()->find($item['id'] ?? null);
                if ($orderItem === null || round((float) $orderItem->unit_price, 2) === round((float) ($item['unit_price'] ?? 0), 2)) {
                    continue;
                }

                if (blank($item['reason'] ?? null)) {
                    $validator->errors()->add(
                        "items.{$index}.reason",
                        'Alasan perubahan harga wajib diisi.',
                    );
                }
            }
        }];
    }
}
