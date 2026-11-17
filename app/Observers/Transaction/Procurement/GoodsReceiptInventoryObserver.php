<?php

namespace App\Observers\Transaction\Procurement;

use App\Models\Transaction\Inventory\InventoryReceiptIntegration;
use App\Models\Transaction\Procurement\GoodsReceipt;
use App\Models\Transaction\Procurement\GoodsReceiptItem;
use App\Services\Transaction\Inventory\InventoryStockService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GoodsReceiptInventoryObserver
{
    public function updated(GoodsReceipt $goodsReceipt): void
    {
        if (! $goodsReceipt->wasChanged('status') || $goodsReceipt->status !== GoodsReceipt::STATUS_POSTED) {
            return;
        }

        if (! Schema::hasTable('inventory_receipt_integrations')) {
            return;
        }

        try {
            app(InventoryStockService::class)->capturePostedReceipt($goodsReceipt);
        } catch (Throwable $exception) {
            Log::error('Goods Receipt inventory integration failed.', [
                'goods_receipt_id' => $goodsReceipt->id,
                'exception' => $exception->getMessage(),
            ]);

            try {
                foreach (GoodsReceiptItem::query()->where('goods_receipt_id', $goodsReceipt->id)->get() as $receiptItem) {
                    $sourceItem = $receiptItem->purchaseOrderItem()->with(['product' => fn ($query) => $query->withTrashed()])->first()
                        ?? $receiptItem->purchaseRequestItem()->with(['product' => fn ($query) => $query->withTrashed()])->first();
                    $product = $sourceItem?->product;

                    if ($product === null || $product->group !== 'CONSUMABLE' || (float) $receiptItem->quantity_accepted <= 0) {
                        continue;
                    }

                    InventoryReceiptIntegration::query()->firstOrCreate(
                        ['goods_receipt_item_id' => $receiptItem->id],
                        [
                            'product_id' => $product->id,
                            'department_id' => $goodsReceipt->department_id,
                            'accepted_quantity' => $receiptItem->quantity_accepted,
                            'status' => InventoryReceiptIntegration::STATUS_FAILED,
                            'failure_message' => 'Sinkronisasi stok gagal. Coba ulang dari halaman Inventory.',
                        ],
                    );
                }
            } catch (Throwable $integrationException) {
                Log::critical('Goods Receipt was posted but its Inventory failure record could not be created.', [
                    'goods_receipt_id' => $goodsReceipt->id,
                    'exception' => $integrationException->getMessage(),
                ]);
            }
        }
    }
}
