<?php

namespace App\Services\Transaction\Procurement;

use App\Enums\NumberingModuleCode;
use App\Models\Master\Organization\Location;
use App\Models\Master\Procurement\Vendor;
use App\Models\Transaction\Procurement\GoodsReceipt;
use App\Models\Transaction\Procurement\GoodsReceiptItem;
use App\Models\Transaction\Procurement\PurchaseOrder;
use App\Models\Transaction\Procurement\PurchaseOrderItem;
use App\Models\Transaction\Procurement\PurchaseRequest;
use App\Models\Transaction\Procurement\PurchaseRequestItem;
use App\Models\User;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GoodsReceiptManagementService
{
    public function __construct(
        private readonly NumberingService $numberingService,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availableSources(): array
    {
        $purchaseOrders = PurchaseOrder::query()
            ->with([
                'purchaseRequest:id,number,created_at,approved_at',
                'company:id,name',
                'department:id,name',
                'vendor:id,code,name',
                'items.goodsReceiptItems.goodsReceipt:id,number,status,received_at,posted_at',
            ])
            ->whereIn('status', ['SUBMITTED', 'PARTIALLY_RECEIVED', 'RECEIVED'])
            ->latest('submitted_at')
            ->get()
            ->map(function (PurchaseOrder $purchaseOrder): ?array {
                $openItems = $purchaseOrder->items
                    ->filter(fn (PurchaseOrderItem $item): bool => $this->remainingQuantity($item) > 0);

                $acceptedQuantity = $purchaseOrder->items->sum(
                    fn (PurchaseOrderItem $item): float => (float) $item->quantity - $this->remainingQuantity($item),
                );

                return [
                    'key' => 'PO:'.$purchaseOrder->id,
                    'source_type' => GoodsReceipt::SOURCE_PURCHASE_ORDER,
                    'source_id' => $purchaseOrder->id,
                    'source_number' => $purchaseOrder->number,
                    'purchase_request_number' => $purchaseOrder->purchaseRequest?->number,
                    'company_id' => $purchaseOrder->company_id,
                    'vendor_id' => $purchaseOrder->vendor_id,
                    'vendor' => $purchaseOrder->vendor?->name,
                    'company' => $purchaseOrder->company?->name,
                    'department' => $purchaseOrder->department?->name,
                    'queued_at' => $purchaseOrder->submitted_at?->toDateString(),
                    'timeline' => $this->receiptTimeline(
                        $purchaseOrder->purchaseRequest,
                        $purchaseOrder,
                        $openItems->isEmpty() ? 'RECEIVED' : ($acceptedQuantity > 0 ? 'PARTIALLY_RECEIVED' : 'WAITING_RECEIPT'),
                    ),
                    'queue_status' => $openItems->isEmpty()
                        ? 'RECEIVED'
                        : ($acceptedQuantity > 0 ? 'PARTIALLY_RECEIVED' : 'WAITING_RECEIPT'),
                    'items_count' => $purchaseOrder->items->count(),
                    'open_items_count' => $openItems->count(),
                    'received_quantity' => round($acceptedQuantity, 3),
                    'outstanding_quantity' => $openItems->sum(fn (PurchaseOrderItem $item): float => $this->remainingQuantity($item)),
                    'receipts' => $this->receiptHistory($purchaseOrder->items),
                ];
            })
            ->values()
            ->all();

        $purchaseRequests = PurchaseRequest::query()
            ->with([
                'company:id,name',
                'department:id,name',
                'items' => fn ($query) => $query->where('requires_po', false),
                'items.vendor:id,code,name',
                'items.product:id,code,name',
                'items.serviceType:id,code,name',
                'items.goodsReceiptItems.goodsReceipt:id,number,status,received_at,posted_at',
            ])
            ->where('status', 'APPROVED')
            ->latest('approved_at')
            ->get();

        $requestSources = [];
        foreach ($purchaseRequests as $purchaseRequest) {
            foreach ($purchaseRequest->items->groupBy(fn (PurchaseRequestItem $item): string => $item->vendor_id ?? 'none') as $vendorKey => $vendorItems) {
                $vendor = $vendorItems->first()->vendor;
                $openVendorItems = $vendorItems
                    ->filter(fn (PurchaseRequestItem $item): bool => $this->remainingQuantity($item) > 0);
                $acceptedQuantity = $vendorItems->sum(
                    fn (PurchaseRequestItem $item): float => (float) $item->quantity - $this->remainingQuantity($item),
                );
                $requestSources[] = [
                    'key' => 'PR:'.$purchaseRequest->id.':'.$vendorKey,
                    'source_type' => GoodsReceipt::SOURCE_PURCHASE_REQUEST,
                    'source_id' => $purchaseRequest->id,
                    'vendor_id' => $vendorKey === 'none' ? null : $vendorKey,
                    'source_number' => $purchaseRequest->number,
                    'purchase_request_number' => $purchaseRequest->number,
                    'company_id' => $purchaseRequest->company_id,
                    'vendor' => $vendor?->name,
                    'company' => $purchaseRequest->company?->name,
                    'department' => $purchaseRequest->department?->name,
                    'queued_at' => $purchaseRequest->approved_at?->toDateString(),
                    'timeline' => $this->receiptTimeline(
                        $purchaseRequest,
                        null,
                        $openVendorItems->isEmpty() ? 'RECEIVED' : ($acceptedQuantity > 0 ? 'PARTIALLY_RECEIVED' : 'WAITING_RECEIPT'),
                    ),
                    'queue_status' => $openVendorItems->isEmpty()
                        ? 'RECEIVED'
                        : ($acceptedQuantity > 0 ? 'PARTIALLY_RECEIVED' : 'WAITING_RECEIPT'),
                    'items_count' => $vendorItems->count(),
                    'open_items_count' => $openVendorItems->count(),
                    'received_quantity' => round($acceptedQuantity, 3),
                    'outstanding_quantity' => $openVendorItems->sum(fn (PurchaseRequestItem $item): float => $this->remainingQuantity($item)),
                    'receipts' => $this->receiptHistory($vendorItems),
                ];
            }
        }

        return [...$purchaseOrders, ...$requestSources];
    }

    /** @return array<int, array{label: string, at: ?string, status: string}> */
    private function receiptTimeline(PurchaseRequest $purchaseRequest, ?PurchaseOrder $purchaseOrder, string $receiptStatus): array
    {
        $timeline = [
            ['label' => 'PR dibuat', 'at' => $purchaseRequest->created_at?->toIso8601String(), 'status' => $purchaseRequest->created_at === null ? 'waiting' : 'done'],
            ['label' => 'PR disetujui', 'at' => $purchaseRequest->approved_at?->toIso8601String(), 'status' => $purchaseRequest->approved_at === null ? 'waiting' : 'done'],
        ];

        if ($purchaseOrder !== null) {
            $timeline[] = ['label' => 'PO dibuat', 'at' => $purchaseOrder->created_at?->toIso8601String(), 'status' => $purchaseOrder->created_at === null ? 'waiting' : 'done'];
            $timeline[] = ['label' => 'PO dikirim', 'at' => $purchaseOrder->submitted_at?->toIso8601String(), 'status' => $purchaseOrder->submitted_at === null ? 'waiting' : 'done'];
        }

        $timeline[] = [
            'label' => match ($receiptStatus) {
                'RECEIVED' => 'GR selesai',
                'PARTIALLY_RECEIVED' => 'Menunggu sisa GR',
                default => 'Menunggu GR',
            },
            'at' => null,
            'status' => $receiptStatus === 'RECEIVED' ? 'done' : 'current',
        ];

        return $timeline;
    }

    /**
     * @param  Collection<int, PurchaseOrderItem|PurchaseRequestItem>  $sourceItems
     * @return array<int, array{id: string, number: string, status: string, received_at: string, posted_at: ?string, quantity_received: float, quantity_accepted: float, quantity_rejected: float}>
     */
    private function receiptHistory(Collection $sourceItems): array
    {
        return $sourceItems
            ->flatMap(fn (PurchaseOrderItem|PurchaseRequestItem $item) => $item->goodsReceiptItems)
            ->filter(fn (GoodsReceiptItem $item): bool => $item->goodsReceipt !== null)
            ->groupBy(fn (GoodsReceiptItem $item): string => (string) $item->goodsReceipt?->id)
            ->map(function (Collection $receiptItems): array {
                $goodsReceipt = $receiptItems->first()->goodsReceipt;

                return [
                    'id' => (string) $goodsReceipt->id,
                    'number' => $goodsReceipt->number,
                    'status' => $goodsReceipt->status,
                    'received_at' => $goodsReceipt->received_at->toDateString(),
                    'posted_at' => $goodsReceipt->posted_at?->toIso8601String(),
                    'quantity_received' => round($receiptItems->sum(fn (GoodsReceiptItem $item): float => (float) $item->quantity_received), 3),
                    'quantity_accepted' => round($receiptItems->sum(fn (GoodsReceiptItem $item): float => (float) $item->quantity_accepted), 3),
                    'quantity_rejected' => round($receiptItems->sum(fn (GoodsReceiptItem $item): float => (float) $item->quantity_rejected), 3),
                ];
            })
            ->sortByDesc('received_at')
            ->values()
            ->all();
    }

    /**
     * @return array{source: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    public function sourceDetails(string $sourceType, string $sourceId, ?string $vendorId = null): array
    {
        $source = $this->loadSource($sourceType, $sourceId, $vendorId);

        return [
            'source' => $source['metadata'],
            'items' => $source['items']->map(function (Model $item): array {
                return [
                    'source_item_id' => $item->id,
                    'item_type' => $item->item_type,
                    'item_name' => $item->product?->name ?? $item->serviceType?->name ?? $item->description ?? 'Item',
                    'item_code' => $item->product?->code ?? $item->serviceType?->code ?? null,
                    'description' => $item->description,
                    'quantity_ordered' => (float) $item->quantity,
                    'quantity_previously_accepted' => round((float) $item->quantity - $this->remainingQuantity($item), 3),
                    'remaining_quantity' => $this->remainingQuantity($item),
                    'uom' => $item->uom?->name ?? $item->uom?->code,
                    'uom_code' => $item->uom?->code,
                    'unit_price' => $item instanceof PurchaseOrderItem ? $item->unit_price : $item->estimated_unit_price,
                ];
            })->values()->all(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function createDraft(User $actor, array $data): GoodsReceipt
    {
        return DB::transaction(function () use ($actor, $data): GoodsReceipt {
            $source = $this->loadSource(
                $data['source_type'],
                $data['source_id'],
                $data['vendor_id'] ?? null,
                lock: true,
            );
            $receiptItems = $this->validateReceiptItems($source['items'], $data['items']);
            $sourceModel = $source['model'];
            $warehouseId = $data['warehouse_location_id'] ?? null;
            $this->validateDestinationLocations($sourceModel->company_id, $warehouseId, $data['storage_location_id'] ?? null);
            $warehouseLocation = $warehouseId === null ? null : Location::query()
                ->with('branch:id,code')
                ->findOrFail($warehouseId);
            $vendorCode = $sourceModel instanceof PurchaseOrder
                ? $sourceModel->vendor?->code
                : Vendor::query()->whereKey($data['vendor_id'] ?? null)->value('code');

            $receipt = GoodsReceipt::query()->create([
                'number' => $this->numberingService->generate(
                    NumberingModuleCode::GoodsReceipt,
                    [
                        'company' => $sourceModel->company->code,
                        'branch' => $warehouseLocation?->branch?->code ?? 'NA',
                        'location' => $warehouseLocation?->code ?? 'NA',
                        'document_type' => 'GR',
                        'vendor' => $vendorCode,
                        'department' => $sourceModel->department->code,
                    ],
                ),
                'source_type' => $data['source_type'],
                'purchase_order_id' => $sourceModel instanceof PurchaseOrder ? $sourceModel->id : null,
                'purchase_request_id' => $sourceModel instanceof PurchaseRequest ? $sourceModel->id : null,
                'company_id' => $sourceModel->company_id,
                'department_id' => $sourceModel->department_id,
                'warehouse_location_id' => $warehouseId,
                'storage_location_id' => $data['storage_location_id'] ?? null,
                'vendor_id' => $sourceModel instanceof PurchaseOrder ? $sourceModel->vendor_id : ($data['vendor_id'] ?? null),
                'received_at' => $data['received_at'],
                'delivery_note_number' => $data['delivery_note_number'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'status' => GoodsReceipt::STATUS_DRAFT,
                'created_by' => $actor->id,
            ]);

            foreach ($receiptItems as $item) {
                $receipt->items()->create($item);
            }

            return $receipt;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateDraft(GoodsReceipt $goodsReceipt, array $data): void
    {
        DB::transaction(function () use ($goodsReceipt, $data): void {
            $lockedReceipt = GoodsReceipt::query()
                ->lockForUpdate()
                ->findOrFail($goodsReceipt->id);

            if ($lockedReceipt->status !== GoodsReceipt::STATUS_DRAFT) {
                throw new RuntimeException('Hanya Goods Receipt Draft yang dapat diubah.');
            }

            $sourceId = $lockedReceipt->purchase_order_id ?? $lockedReceipt->purchase_request_id;
            $vendorId = $lockedReceipt->source_type === GoodsReceipt::SOURCE_PURCHASE_REQUEST
                ? $lockedReceipt->vendor_id
                : null;
            $source = $this->loadSource($lockedReceipt->source_type, $sourceId, $vendorId, lock: true);
            $receiptItems = $this->validateReceiptItems($source['items'], $data['items']);
            $warehouseId = $data['warehouse_location_id'] ?? null;
            $this->validateDestinationLocations($lockedReceipt->company_id, $warehouseId, $data['storage_location_id'] ?? null);

            $lockedReceipt->update([
                'received_at' => $data['received_at'],
                'warehouse_location_id' => $warehouseId,
                'storage_location_id' => $data['storage_location_id'] ?? null,
                'delivery_note_number' => $data['delivery_note_number'] ?? null,
                'remarks' => $data['remarks'] ?? null,
            ]);
            $lockedReceipt->items()->get()->each->delete();

            foreach ($receiptItems as $item) {
                $lockedReceipt->items()->create($item);
            }
        });
    }

    public function post(GoodsReceipt $goodsReceipt, User $actor): void
    {
        DB::transaction(function () use ($goodsReceipt, $actor): void {
            $lockedReceipt = GoodsReceipt::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($goodsReceipt->id);

            if ($lockedReceipt->status !== GoodsReceipt::STATUS_DRAFT) {
                throw new RuntimeException('Goods Receipt ini sudah diposting.');
            }

            $sourceId = $lockedReceipt->purchase_order_id ?? $lockedReceipt->purchase_request_id;
            $vendorId = $lockedReceipt->source_type === GoodsReceipt::SOURCE_PURCHASE_REQUEST
                ? $lockedReceipt->vendor_id
                : null;
            $source = $this->loadSource($lockedReceipt->source_type, $sourceId, $vendorId, lock: true);
            $sourceById = $source['items']->keyBy('id');

            foreach ($lockedReceipt->items as $index => $receiptItem) {
                $sourceItemId = $lockedReceipt->source_type === GoodsReceipt::SOURCE_PURCHASE_ORDER
                    ? $receiptItem->purchase_order_item_id
                    : $receiptItem->purchase_request_item_id;
                $sourceItem = $sourceById->get($sourceItemId);

                if ($sourceItem === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.source_item_id" => 'Item sumber tidak lagi tersedia untuk diterima.',
                    ]);
                }

                $this->validateQuantities($receiptItem->toArray(), $index);
                if (
                    $sourceItem->product?->group === 'ASSET'
                    && (float) $receiptItem->quantity_accepted !== (float) (int) $receiptItem->quantity_accepted
                ) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity_accepted" => 'Kuantitas diterima baik untuk produk ASSET harus berupa unit penuh.',
                    ]);
                }

                $remaining = $this->remainingQuantity($sourceItem);
                if ((float) $receiptItem->quantity_received > $remaining) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity_received" => "Kuantitas yang diterima melebihi sisa {$remaining}.",
                    ]);
                }
            }

            if ($lockedReceipt->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Tambahkan minimal satu item sebelum posting Goods Receipt.',
                ]);
            }

            $lockedReceipt->update([
                'status' => GoodsReceipt::STATUS_POSTED,
                'posted_at' => now(),
                'posted_by' => $actor->id,
            ]);

            if ($lockedReceipt->purchase_order_id !== null) {
                $purchaseOrder = PurchaseOrder::query()
                    ->with('items.goodsReceiptItems.goodsReceipt:id,status')
                    ->findOrFail($lockedReceipt->purchase_order_id);
                $hasOutstandingItems = $purchaseOrder->items->contains(
                    fn (PurchaseOrderItem $item): bool => $this->remainingQuantity($item) > 0,
                );

                $purchaseOrder->update([
                    'status' => $hasOutstandingItems ? 'PARTIALLY_RECEIVED' : 'RECEIVED',
                ]);
            }

        });
    }

    private function validateDestinationLocations(string $companyId, ?string $warehouseId, ?string $storageId): void
    {
        if ($warehouseId === null) {
            if ($storageId !== null) {
                throw ValidationException::withMessages([
                    'storage_location_id' => 'Sublokasi hanya dapat dipilih jika gudang tujuan dipilih.',
                ]);
            }

            return;
        }

        $warehouse = Location::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(location_type) IN (?, ?)', ['warehouse', 'gudang'])
            ->find($warehouseId);

        if ($warehouse === null) {
            throw ValidationException::withMessages([
                'warehouse_location_id' => 'Pilih gudang aktif yang sesuai dengan perusahaan sumber GR.',
            ]);
        }

        if ($storageId === null) {
            return;
        }

        $storage = Location::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $warehouse->branch_id)
            ->where('parent_id', $warehouse->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(COALESCE(location_type, \'\')) NOT IN (?, ?)', ['warehouse', 'gudang'])
            ->find($storageId);

        if ($storage === null) {
            throw ValidationException::withMessages([
                'storage_location_id' => 'Pilih sublokasi aktif yang berada di dalam gudang terpilih.',
            ]);
        }
    }

    public function deleteDraft(GoodsReceipt $goodsReceipt): void
    {
        DB::transaction(function () use ($goodsReceipt): void {
            $lockedReceipt = GoodsReceipt::query()
                ->lockForUpdate()
                ->findOrFail($goodsReceipt->id);

            if ($lockedReceipt->status !== GoodsReceipt::STATUS_DRAFT) {
                throw new RuntimeException('Hanya Goods Receipt Draft yang dapat dihapus.');
            }

            $lockedReceipt->delete();
        });
    }

    /**
     * @return array{model: PurchaseOrder|PurchaseRequest, metadata: array<string, mixed>, items: Collection<int, PurchaseOrderItem|PurchaseRequestItem>}
     */
    private function loadSource(string $sourceType, string $sourceId, ?string $vendorId = null, bool $lock = false): array
    {
        if ($sourceType === GoodsReceipt::SOURCE_PURCHASE_ORDER) {
            $query = PurchaseOrder::query()
                ->with([
                    'purchaseRequest:id,number',
                    'company:id,code,name',
                    'department:id,code,name',
                    'vendor:id,code,name',
                    'items.product:id,code,name,group',
                    'items.serviceType:id,code,name',
                    'items.uom:id,code,name',
                    'items.goodsReceiptItems.goodsReceipt:id,status',
                ])
                ->whereIn('status', ['SUBMITTED', 'PARTIALLY_RECEIVED']);

            if ($lock) {
                $query->lockForUpdate();
            }

            $purchaseOrder = $query->find($sourceId);
            if ($purchaseOrder === null) {
                throw ValidationException::withMessages([
                    'source_id' => 'PO ini belum dikirim atau sudah selesai diterima.',
                ]);
            }

            $items = $purchaseOrder->items
                ->filter(fn (PurchaseOrderItem $item): bool => $this->remainingQuantity($item) > 0)
                ->values();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'source_id' => 'Semua item PO sudah diterima.',
                ]);
            }

            return [
                'model' => $purchaseOrder,
                'metadata' => [
                    'source_type' => GoodsReceipt::SOURCE_PURCHASE_ORDER,
                    'source_id' => $purchaseOrder->id,
                    'source_number' => $purchaseOrder->number,
                    'company_id' => $purchaseOrder->company_id,
                    'purchase_request_number' => $purchaseOrder->purchaseRequest?->number,
                    'vendor_id' => $purchaseOrder->vendor_id,
                    'vendor' => $purchaseOrder->vendor?->name,
                    'company' => $purchaseOrder->company?->name,
                    'department' => $purchaseOrder->department?->name,
                    'status' => $purchaseOrder->status,
                ],
                'items' => $items,
            ];
        }

        if ($sourceType === GoodsReceipt::SOURCE_PURCHASE_REQUEST) {
            $query = PurchaseRequest::query()
                ->with([
                    'company:id,code,name',
                    'department:id,code,name',
                    'items' => fn ($query) => $query->where('requires_po', false),
                    'items.vendor:id,code,name',
                    'items.product:id,code,name,group',
                    'items.serviceType:id,code,name',
                    'items.uom:id,code,name',
                    'items.goodsReceiptItems.goodsReceipt:id,status',
                ])
                ->where('status', 'APPROVED');

            if ($lock) {
                $query->lockForUpdate();
            }

            $purchaseRequest = $query->find($sourceId);
            if ($purchaseRequest === null) {
                throw ValidationException::withMessages([
                    'source_id' => 'PR ini belum disetujui atau tidak memiliki item tanpa PO.',
                ]);
            }

            $items = $purchaseRequest->items
                ->filter(fn (PurchaseRequestItem $item): bool => $item->vendor_id === $vendorId && $this->remainingQuantity($item) > 0)
                ->values();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'source_id' => 'Tidak ada item PR tanpa PO yang masih perlu diterima untuk vendor ini.',
                ]);
            }

            return [
                'model' => $purchaseRequest,
                'metadata' => [
                    'source_type' => GoodsReceipt::SOURCE_PURCHASE_REQUEST,
                    'source_id' => $purchaseRequest->id,
                    'source_number' => $purchaseRequest->number,
                    'company_id' => $purchaseRequest->company_id,
                    'purchase_request_number' => $purchaseRequest->number,
                    'vendor_id' => $vendorId,
                    'vendor' => $items->first()->vendor?->name,
                    'company' => $purchaseRequest->company?->name,
                    'department' => $purchaseRequest->department?->name,
                    'status' => $purchaseRequest->status,
                ],
                'items' => $items,
            ];
        }

        throw ValidationException::withMessages([
            'source_type' => 'Sumber penerimaan tidak valid.',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $submittedItems
     * @return array<int, array<string, mixed>>
     */
    private function validateReceiptItems(Collection $sourceItems, array $submittedItems): array
    {
        if ($submittedItems === []) {
            throw ValidationException::withMessages([
                'items' => 'Pilih minimal satu item dan isi kuantitas yang diterima.',
            ]);
        }

        $sourceById = $sourceItems->keyBy('id');
        $savedItems = [];
        $usedSourceItemIds = [];

        foreach ($submittedItems as $index => $submittedItem) {
            $sourceItemId = (string) ($submittedItem['source_item_id'] ?? '');
            $sourceItem = $sourceById->get($sourceItemId);

            if ($sourceItem === null || in_array($sourceItemId, $usedSourceItemIds, true)) {
                throw ValidationException::withMessages([
                    "items.{$index}.source_item_id" => 'Item tidak termasuk dalam sumber penerimaan atau dipilih lebih dari sekali.',
                ]);
            }

            $this->validateQuantities($submittedItem, $index);
            if (
                $sourceItem->product?->group === 'ASSET'
                && (float) $submittedItem['quantity_accepted'] !== (float) (int) $submittedItem['quantity_accepted']
            ) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity_accepted" => 'Kuantitas diterima baik untuk produk ASSET harus berupa unit penuh.',
                ]);
            }

            if ((float) $submittedItem['quantity_received'] > $this->remainingQuantity($sourceItem)) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity_received" => 'Kuantitas yang diterima melebihi sisa kuantitas sumber.',
                ]);
            }

            $isPurchaseOrderItem = $sourceItem instanceof PurchaseOrderItem;
            $savedItems[] = [
                'purchase_order_item_id' => $isPurchaseOrderItem ? $sourceItem->id : null,
                'purchase_request_item_id' => $isPurchaseOrderItem ? null : $sourceItem->id,
                'quantity_received' => $submittedItem['quantity_received'],
                'quantity_accepted' => $submittedItem['quantity_accepted'],
                'quantity_rejected' => $submittedItem['quantity_rejected'],
                'remarks' => $submittedItem['remarks'] ?? null,
            ];
            $usedSourceItemIds[] = $sourceItemId;
        }

        return $savedItems;
    }

    /** @param array<string, mixed> $item */
    private function validateQuantities(array $item, int $index): void
    {
        $received = (float) ($item['quantity_received'] ?? 0);
        $accepted = (float) ($item['quantity_accepted'] ?? 0);
        $rejected = (float) ($item['quantity_rejected'] ?? 0);

        if ($received <= 0 || $accepted < 0 || $rejected < 0 || round($accepted + $rejected, 3) !== round($received, 3)) {
            throw ValidationException::withMessages([
                "items.{$index}.quantity_accepted" => 'Kuantitas diterima harus sama dengan jumlah diterima baik dan ditolak.',
            ]);
        }

        if ($rejected > 0 && blank($item['remarks'] ?? null)) {
            throw ValidationException::withMessages([
                "items.{$index}.remarks" => 'Catatan wajib diisi jika terdapat kuantitas yang ditolak.',
            ]);
        }
    }

    private function remainingQuantity(PurchaseOrderItem|PurchaseRequestItem $item): float
    {
        $acceptedQuantity = $item->goodsReceiptItems
            ->filter(fn (GoodsReceiptItem $receiptItem): bool => $receiptItem->goodsReceipt?->status === GoodsReceipt::STATUS_POSTED)
            ->sum(fn (GoodsReceiptItem $receiptItem): float => (float) $receiptItem->quantity_accepted);

        return max(0, round((float) $item->quantity - $acceptedQuantity, 3));
    }
}
