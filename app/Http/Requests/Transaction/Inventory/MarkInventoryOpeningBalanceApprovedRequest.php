<?php

namespace App\Http\Requests\Transaction\Inventory;

use App\Models\Transaction\Inventory\InventoryDocument;
use Illuminate\Foundation\Http\FormRequest;

class MarkInventoryOpeningBalanceApprovedRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $document = $this->route('inventoryDocument');

        return $user !== null
            && $document instanceof InventoryDocument
            && $document->document_type === InventoryDocument::TYPE_OPENING_BALANCE
            && $document->status === InventoryDocument::STATUS_PENDING_APPROVAL
            && $document->approval_mode === 'MANUAL'
            && ($document->performed_by === $user->id || $user->hasGlobalDepartmentAccess());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'remarks' => ['required', 'string', 'max:2000'],
            'signed_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
        ];
    }
}
