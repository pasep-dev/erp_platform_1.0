<?php

namespace App\Http\Requests\Transaction\Procurement;

use App\Models\Transaction\Procurement\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

class CancelPurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('purchase-order.cancel') === true
            && $this->route('purchaseOrder') instanceof PurchaseOrder
            && in_array($this->route('purchaseOrder')->status, ['DRAFT', 'SUBMITTED'], true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
