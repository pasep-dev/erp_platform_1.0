<?php

namespace App\Services\Transaction\Inventory;

use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Master\Product\Product;
use App\Models\Transaction\Inventory\InventoryDepartmentWarehouse;
use App\Models\Transaction\Inventory\InventoryReceiptIntegration;
use App\Models\Transaction\Inventory\InventoryStockMovement;
use App\Models\Transaction\Procurement\GoodsReceipt;
use App\Models\Transaction\Procurement\GoodsReceiptItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryStockService
{
    public function capturePostedReceipt(GoodsReceipt $goodsReceipt): void
    {
        if ($goodsReceipt->status !== GoodsReceipt::STATUS_POSTED) {
            return;
        }

        DB::transaction(function () use ($goodsReceipt): void {
            $receipt = GoodsReceipt::query()
                ->with([
                    'items.purchaseOrderItem.product' => fn ($query) => $query->withTrashed(),
                    'items.purchaseRequestItem.product' => fn ($query) => $query->withTrashed(),
                ])
                ->lockForUpdate()
                ->findOrFail($goodsReceipt->id);

            foreach ($receipt->items as $receiptItem) {
                $sourceItem = $receiptItem->purchaseOrderItem ?? $receiptItem->purchaseRequestItem;
                $product = $sourceItem?->product;

                if ($product === null || $product->group !== 'CONSUMABLE' || (float) $receiptItem->quantity_accepted <= 0) {
                    continue;
                }

                $integration = InventoryReceiptIntegration::query()->firstOrCreate(
                    ['goods_receipt_item_id' => $receiptItem->id],
                    [
                        'product_id' => $product->id,
                        'department_id' => $receipt->department_id,
                        'accepted_quantity' => $receiptItem->quantity_accepted,
                        'status' => InventoryReceiptIntegration::STATUS_PENDING_WAREHOUSE,
                    ],
                );

                $this->processIntegration($integration, $receiptItem, $receipt, $product);
            }
        });
    }

    /** @param array<int, string> $serialNumbers */
    public function receiveSerials(InventoryReceiptIntegration $integration, array $serialNumbers, User $actor): void
    {
        DB::transaction(function () use ($integration, $serialNumbers, $actor): void {
            $lockedIntegration = InventoryReceiptIntegration::query()
                ->lockForUpdate()
                ->findOrFail($integration->id);

            if ($lockedIntegration->status !== InventoryReceiptIntegration::STATUS_PENDING_SERIALS) {
                throw ValidationException::withMessages([
                    'serial_numbers' => 'Penerimaan ini tidak sedang menunggu nomor seri.',
                ]);
            }

            $receiptItem = $lockedIntegration->goodsReceiptItem()->with('goodsReceipt')->firstOrFail();
            $receipt = $receiptItem->goodsReceipt;
            $product = Product::query()->lockForUpdate()->findOrFail($lockedIntegration->product_id);
            $location = Location::query()
                ->where('company_id', $receipt->company_id)
                ->where('is_active', true)
                ->find($lockedIntegration->location_id);

            if ($location === null) {
                throw ValidationException::withMessages([
                    'serial_numbers' => 'Gudang tujuan GR tidak tersedia. Perbarui draft GR dan pilih lokasi yang aktif.',
                ]);
            }

            $serialNumbers = array_map(fn (string $serial): string => mb_strtoupper(trim($serial)), $serialNumbers);
            $expectedCount = (int) $lockedIntegration->accepted_quantity;

            if ((float) $lockedIntegration->accepted_quantity !== (float) $expectedCount || count($serialNumbers) !== $expectedCount) {
                throw ValidationException::withMessages([
                    'serial_numbers' => "Masukkan tepat {$expectedCount} nomor seri yang berbeda.",
                ]);
            }

            if (count(array_unique($serialNumbers)) !== $expectedCount) {
                throw ValidationException::withMessages([
                    'serial_numbers' => 'Nomor seri dalam penerimaan harus unik.',
                ]);
            }

            $existingSerial = InventoryStockMovement::query()
                ->where('product_id', $product->id)
                ->where('direction', 'IN')
                ->whereIn('serial_number', $serialNumbers)
                ->exists();

            if ($existingSerial) {
                throw ValidationException::withMessages([
                    'serial_numbers' => 'Salah satu nomor seri sudah pernah diterima untuk produk ini.',
                ]);
            }

            foreach ($serialNumbers as $serialNumber) {
                $this->createReceiptMovement(
                    $receiptItem,
                    $receipt,
                    $product,
                    $location->id,
                    $serialNumber,
                    $actor->id,
                );
            }

            $lockedIntegration->update([
                'location_id' => $location->id,
                'status' => InventoryReceiptIntegration::STATUS_PROCESSED,
                'failure_message' => null,
                'processed_at' => now(),
            ]);
        });
    }

    public function retryIntegration(InventoryReceiptIntegration $integration): void
    {
        DB::transaction(function () use ($integration): void {
            $lockedIntegration = InventoryReceiptIntegration::query()
                ->lockForUpdate()
                ->findOrFail($integration->id);
            $receiptItem = $lockedIntegration->goodsReceiptItem()->with('goodsReceipt')->firstOrFail();
            $receipt = $receiptItem->goodsReceipt;
            $product = $lockedIntegration->product;

            if ($receipt->status === GoodsReceipt::STATUS_POSTED && $product !== null) {
                $this->processIntegration($lockedIntegration, $receiptItem, $receipt, $product);
            }
        });
    }

    /** @return array<string, mixed> */
    public function stockCard(Product $product, Location $location, Department $department): array
    {
        $movements = InventoryStockMovement::query()
            ->with('performedBy:id,name')
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->where('department_id', $department->id)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $balance = 0.0;
        $rows = $movements->map(function (InventoryStockMovement $movement) use (&$balance): array {
            $quantity = (float) $movement->quantity;
            $balance += $movement->direction === 'IN' ? $quantity : -$quantity;

            return [
                'id' => $movement->id,
                'occurred_at' => $movement->occurred_at?->toIso8601String(),
                'movement_type' => $movement->movement_type,
                'direction' => $movement->direction,
                'quantity' => $quantity,
                'serial_number' => $movement->serial_number,
                'reference_number' => $movement->reference_number,
                'source_type' => $movement->source_type,
                'balance' => $balance,
                'performed_by' => $movement->performedBy?->name,
            ];
        });

        return [
            'product' => $product->only(['id', 'code', 'name', 'group', 'inventory_tracking_mode']),
            'warehouse' => $location->only(['id', 'code', 'name']),
            'department' => $department->only(['id', 'code', 'name']),
            'balance' => $balance,
            'movements' => $rows->values()->all(),
        ];
    }

    private function processIntegration(
        InventoryReceiptIntegration $integration,
        GoodsReceiptItem $receiptItem,
        GoodsReceipt $receipt,
        Product $product,
    ): void {
        if ($integration->status === InventoryReceiptIntegration::STATUS_PROCESSED) {
            return;
        }

        $product = Product::withTrashed()->lockForUpdate()->findOrFail($product->id);

        $locationId = $receipt->storage_location_id
            ?? $receipt->warehouse_location_id
            ?? $integration->location_id;
        $location = $locationId === null
            ? null
            : Location::query()
                ->where('company_id', $receipt->company_id)
                ->where('is_active', true)
                ->find($locationId);

        if ($location === null) {
            $integration->update([
                'status' => InventoryReceiptIntegration::STATUS_PENDING_WAREHOUSE,
                'failure_message' => 'Gudang tujuan GR belum dipilih atau sudah tidak aktif.',
            ]);

            return;
        }

        InventoryDepartmentWarehouse::query()->firstOrCreate(
            ['department_id' => $receipt->department_id, 'location_id' => $location->id],
            ['configured_by' => $receipt->posted_by],
        );

        $integration->update([
            'location_id' => $location->id,
            'failure_message' => null,
        ]);

        if ($product->inventory_tracking_mode === 'SERIAL') {
            if ((float) $integration->accepted_quantity !== (float) (int) $integration->accepted_quantity) {
                $integration->update([
                    'status' => InventoryReceiptIntegration::STATUS_FAILED,
                    'failure_message' => 'Kuantitas produk bernomor seri harus berupa unit penuh.',
                ]);

                return;
            }

            $integration->update(['status' => InventoryReceiptIntegration::STATUS_PENDING_SERIALS]);

            return;
        }

        $this->createReceiptMovement(
            $receiptItem,
            $receipt,
            $product,
            $location->id,
            null,
            $receipt->posted_by,
        );

        $integration->update([
            'status' => InventoryReceiptIntegration::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);
    }

    private function createReceiptMovement(
        GoodsReceiptItem $receiptItem,
        GoodsReceipt $receipt,
        Product $product,
        string $locationId,
        ?string $serialNumber,
        ?int $performedBy,
    ): void {
        $key = $serialNumber === null
            ? 'GR:'.$receiptItem->id.':IN'
            : 'GR:'.$receiptItem->id.':SERIAL:'.$serialNumber;

        InventoryStockMovement::query()->firstOrCreate(
            ['idempotency_key' => hash('sha256', $key)],
            [
                'product_id' => $product->id,
                'location_id' => $locationId,
                'department_id' => $receipt->department_id,
                'movement_type' => 'GOODS_RECEIPT',
                'direction' => 'IN',
                'quantity' => $serialNumber === null ? $receiptItem->quantity_accepted : 1,
                'serial_number' => $serialNumber,
                'source_type' => 'GOODS_RECEIPT',
                'source_id' => $receipt->id,
                'source_line_id' => $receiptItem->id,
                'reference_number' => $receipt->number,
                'occurred_at' => $receipt->posted_at ?? now(),
                'performed_by' => $performedBy,
            ],
        );
    }
}
