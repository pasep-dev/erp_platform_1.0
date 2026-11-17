<?php

namespace App\Http\Requests\Transaction\Procurement;

use App\Models\Transaction\Procurement\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;

class MarkPurchaseRequestApprovedRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $purchaseRequest = $this->route('purchaseRequest');

        return $user !== null
            && $purchaseRequest instanceof PurchaseRequest
            && ($purchaseRequest->requester_id === $user->id
                || $user->hasGlobalDepartmentAccess());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'remarks' => [
                'required',
                'string',
                'max:2000',
            ],
            'signed_document' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:20480',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'remarks' => 'komentar approval',
            'signed_document' => 'dokumen PR yang sudah ditandatangani',
        ];
    }
}
