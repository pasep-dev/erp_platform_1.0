<?php

namespace App\Services\Transaction\Procurement;

use App\Models\NumberingDocument;
use App\Models\Transaction\Procurement\PurchaseOrder;
use App\Models\Transaction\Procurement\PurchaseRequest;
use App\Models\Transaction\Procurement\PurchaseRequestItem;
use App\Services\NumberingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseOrderGenerationService
{
    public function __construct(
        private readonly NumberingService $numberingService,
    ) {}

    public function generateForApprovedPurchaseRequest(PurchaseRequest $purchaseRequest): void
    {
        DB::transaction(function () use ($purchaseRequest): void {
            $lockedPurchaseRequest = PurchaseRequest::query()
                ->with([
                    'company:id,code',
                    'department:id,code',
                    'items.vendor:id,name',
                ])
                ->lockForUpdate()
                ->findOrFail($purchaseRequest->id);

            if ($lockedPurchaseRequest->status !== 'APPROVED') {
                return;
            }

            /** @var Collection<int, PurchaseRequestItem> $poItems */
            $poItems = $lockedPurchaseRequest->items
                ->where('requires_po', true)
                ->values();

            if ($poItems->isEmpty()) {
                return;
            }

            if ($poItems->contains(fn (PurchaseRequestItem $item): bool => $item->vendor_id === null)) {
                throw new RuntimeException(
                    "Purchase Request [{$lockedPurchaseRequest->number}] has PO-required items without a vendor.",
                );
            }

            $poItems->groupBy('vendor_id')->each(function (Collection $vendorItems) use (
                $lockedPurchaseRequest,
            ): void {
                $vendorId = (string) $vendorItems->first()->vendor_id;
                $existingOrder = PurchaseOrder::query()
                    ->where('purchase_request_id', $lockedPurchaseRequest->id)
                    ->where('vendor_id', $vendorId)
                    ->first();

                if ($existingOrder !== null) {
                    return;
                }

                $vendor = $vendorItems->first()->vendor;
                if ($vendor === null) {
                    throw new RuntimeException(
                        "Purchase Request [{$lockedPurchaseRequest->number}] references a missing vendor.",
                    );
                }

                $purchaseOrder = PurchaseOrder::query()->create([
                    'number' => $this->numberingService->generate(
                        NumberingDocument::CODE_PURCHASE_ORDER,
                        [
                            'company' => $lockedPurchaseRequest->company->code,
                            'document_type' => 'PO',
                            'department' => $lockedPurchaseRequest->department->code,
                        ],
                    ),
                    'purchase_request_id' => $lockedPurchaseRequest->id,
                    'company_id' => $lockedPurchaseRequest->company_id,
                    'department_id' => $lockedPurchaseRequest->department_id,
                    'vendor_id' => $vendorId,
                    'status' => 'DRAFT',
                ]);

                foreach ($vendorItems as $item) {
                    $purchaseOrder->items()->create([
                        'purchase_request_item_id' => $item->id,
                        'branch_id' => $item->branch_id,
                        'item_type' => $item->item_type,
                        'product_id' => $item->product_id,
                        'service_type_id' => $item->service_type_id,
                        'asset_id' => $item->asset_id,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'uom_id' => $item->uom_id,
                        'pr_estimated_unit_price' => $item->estimated_unit_price,
                        'unit_price' => $item->estimated_unit_price,
                        'tax_included' => $item->tax_included,
                        'required_date' => $item->required_date,
                        'delivery_address' => $item->delivery_address,
                        'vendor_pic' => $item->vendor_pic,
                        'vendor_contact' => $item->vendor_contact,
                        'vendor_url' => $item->vendor_url,
                        'remarks' => $item->remarks,
                    ]);
                }
            });
        });
    }
}
