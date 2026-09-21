<?php

namespace App\Http\Requests\Master\Procurement;

use App\Models\Master\Procurement\PaymentTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $paymentTerm = $this->route('payment_term');

        $paymentTermId = $paymentTerm instanceof PaymentTerm
            ? $paymentTerm->id
            : $paymentTerm;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('mst_payment_terms', 'code')
                    ->ignore($paymentTermId)
                    ->whereNull('deleted_at'),
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'due_days' => [
                'required',
                'integer',
                'min:0',
                'max:3650',
            ],

            'description' => [
                'nullable',
                'string',
                'max:1000',
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
            'code' => 'payment term code',
            'name' => 'payment term name',
            'due_days' => 'due days',
            'description' => 'description',
            'is_active' => 'active status',
        ];
    }
}
