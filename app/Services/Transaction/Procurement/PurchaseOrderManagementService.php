<?php

namespace App\Services\Transaction\Procurement;

use App\Models\Transaction\Procurement\PurchaseOrder;
use App\Models\Transaction\Procurement\PurchaseOrderItem;
use App\Models\Transaction\Procurement\PurchaseOrderItemPriceHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PurchaseOrderManagementService
{
    /**
     * @param  array<int, array{id: string, unit_price: numeric-string|int|float, reason?: string|null}>  $items
     */
    public function updatePrices(PurchaseOrder $purchaseOrder, User $actor, array $items): void
    {
        DB::transaction(function () use ($purchaseOrder, $actor, $items): void {
            $lockedPurchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if ($lockedPurchaseOrder->status !== 'DRAFT') {
                throw new RuntimeException('Harga hanya dapat diubah selama PO berstatus DRAFT.');
            }

            foreach ($items as $priceChange) {
                $orderItem = PurchaseOrderItem::query()
                    ->where('purchase_order_id', $lockedPurchaseOrder->id)
                    ->lockForUpdate()
                    ->findOrFail($priceChange['id']);
                $newPrice = number_format((float) $priceChange['unit_price'], 2, '.', '');
                $previousPrice = $orderItem->unit_price === null
                    ? null
                    : number_format((float) $orderItem->unit_price, 2, '.', '');

                if ($newPrice === $previousPrice) {
                    continue;
                }

                $reason = trim((string) ($priceChange['reason'] ?? ''));
                if ($reason === '') {
                    throw ValidationException::withMessages([
                        "items.{$orderItem->id}.reason" => 'Alasan perubahan harga wajib diisi.',
                    ]);
                }

                PurchaseOrderItemPriceHistory::query()->create([
                    'purchase_order_item_id' => $orderItem->id,
                    'previous_unit_price' => $previousPrice,
                    'new_unit_price' => $newPrice,
                    'reason' => $reason,
                    'changed_by' => $actor->id,
                ]);

                $orderItem->update(['unit_price' => $newPrice]);
            }
        });
    }

    public function submit(PurchaseOrder $purchaseOrder, User $actor): void
    {
        DB::transaction(function () use ($purchaseOrder, $actor): void {
            $lockedPurchaseOrder = PurchaseOrder::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if ($lockedPurchaseOrder->status !== 'DRAFT') {
                throw new RuntimeException('Hanya PO Draft yang dapat dikirim.');
            }

            if ($lockedPurchaseOrder->items->isEmpty()) {
                throw new RuntimeException('PO tidak memiliki item untuk dikirim.');
            }

            if ($lockedPurchaseOrder->items->contains(fn (PurchaseOrderItem $item): bool => $item->unit_price === null)) {
                throw ValidationException::withMessages([
                    'items' => 'Lengkapi harga setiap item sebelum mengirim PO.',
                ]);
            }

            $lockedPurchaseOrder->update([
                'status' => 'SUBMITTED',
                'submitted_at' => now(),
                'submitted_by' => $actor->id,
            ]);
        });
    }

    public function cancel(PurchaseOrder $purchaseOrder, User $actor, string $reason): void
    {
        DB::transaction(function () use ($purchaseOrder, $actor, $reason): void {
            $lockedPurchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if (! in_array($lockedPurchaseOrder->status, ['DRAFT', 'SUBMITTED'], true)) {
                throw new RuntimeException('PO ini sudah dibatalkan.');
            }

            $lockedPurchaseOrder->update([
                'status' => 'CANCELLED',
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => trim($reason),
            ]);
        });
    }
}
