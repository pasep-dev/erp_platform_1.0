<?php

namespace App\Services\Transaction\Inventory;

use App\Models\Master\Product\Product;
use App\Models\Transaction\Inventory\InventoryDepartmentWarehouse;
use App\Models\Transaction\Inventory\InventoryDocument;
use App\Models\Transaction\Inventory\InventoryDocumentItem;
use App\Models\Transaction\Inventory\InventoryStockMovement;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryOpeningBalancePostingService
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function post(InventoryDocument $document, User $actor): InventoryDocument
    {
        return DB::transaction(function () use ($document, $actor): InventoryDocument {
            $lockedDocument = InventoryDocument::query()
                ->lockForUpdate()
                ->findOrFail($document->id);

            if ($lockedDocument->document_type !== InventoryDocument::TYPE_OPENING_BALANCE) {
                throw ValidationException::withMessages(['approval' => 'Dokumen ini bukan penerimaan saldo awal.']);
            }

            if ($lockedDocument->status === InventoryDocument::STATUS_POSTED) {
                return $lockedDocument;
            }

            if ($lockedDocument->status !== InventoryDocument::STATUS_PENDING_APPROVAL) {
                throw ValidationException::withMessages(['approval' => 'Dokumen tidak lagi menunggu persetujuan.']);
            }

            $mappingExists = InventoryDepartmentWarehouse::query()
                ->where('department_id', $lockedDocument->department_id)
                ->where('location_id', $lockedDocument->source_location_id)
                ->lockForUpdate()
                ->exists();

            if (! $mappingExists) {
                throw ValidationException::withMessages(['approval' => 'Gudang penerimaan tidak lagi terdaftar pada departemen.']);
            }

            $items = $lockedDocument->items()
                ->with('product')
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['approval' => 'Penerimaan harus memiliki minimal satu produk.']);
            }

            Product::query()
                ->whereIn('id', $items->pluck('product_id')->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $allSerials = [];
            foreach ($items as $item) {
                $this->validateItem($item, $allSerials);
            }

            foreach ($items as $item) {
                $serialNumbers = $item->product->inventory_tracking_mode === 'SERIAL'
                    ? $this->normalizeSerials($item->serial_numbers ?? [])
                    : [];
                $units = $serialNumbers === [] ? [null] : $serialNumbers;

                foreach ($units as $serialNumber) {
                    $idempotencyKey = implode(':', [
                        'OPENING_BALANCE',
                        $item->id,
                        'IN',
                        $serialNumber ?? 'QTY',
                    ]);

                    if ($serialNumber !== null && $this->serialIsInStock($item->product_id, $serialNumber)) {
                        throw ValidationException::withMessages([
                            'approval' => "Nomor seri {$serialNumber} sudah tercatat berada dalam stok.",
                        ]);
                    }

                    InventoryStockMovement::query()->firstOrCreate(
                        ['idempotency_key' => hash('sha256', $idempotencyKey)],
                        [
                            'product_id' => $item->product_id,
                            'location_id' => $lockedDocument->source_location_id,
                            'department_id' => $lockedDocument->department_id,
                            'movement_type' => 'OPENING_BALANCE',
                            'direction' => 'IN',
                            'quantity' => $serialNumber === null ? $item->quantity : 1,
                            'serial_number' => $serialNumber,
                            'source_type' => 'INVENTORY_OPENING_BALANCE',
                            'source_id' => $lockedDocument->id,
                            'source_line_id' => $item->id,
                            'inventory_document_item_id' => $item->id,
                            'reference_number' => $lockedDocument->number,
                            'occurred_at' => $lockedDocument->occurred_at,
                            'performed_by' => $actor->id,
                        ],
                    );
                }
            }

            $lockedDocument->update([
                'status' => InventoryDocument::STATUS_POSTED,
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            $this->activityLogger->log(
                action: 'POSTED',
                module: 'INVENTORY_OPENING_BALANCE',
                description: "Penerimaan manual {$lockedDocument->number} diposting ke saldo stok.",
                subjectType: InventoryDocument::class,
                subjectId: $lockedDocument->id,
                newValues: ['status' => InventoryDocument::STATUS_POSTED],
            );

            return $lockedDocument->fresh(['items.product', 'department', 'sourceLocation']);
        });
    }

    /** @param array<string, true> $allSerials */
    private function validateItem(InventoryDocumentItem $item, array &$allSerials): void
    {
        if ($item->product === null || $item->product->group !== 'CONSUMABLE') {
            throw ValidationException::withMessages(['approval' => 'Produk pada penerimaan tidak lagi valid sebagai consumable.']);
        }

        $quantity = (float) $item->quantity;
        $serialNumbers = $this->normalizeSerials($item->serial_numbers ?? []);

        if ($quantity <= 0) {
            throw ValidationException::withMessages(['approval' => "Kuantitas {$item->product->code} harus lebih besar dari nol."]);
        }

        if ($item->product->inventory_tracking_mode === 'SERIAL') {
            if (
                $quantity !== (float) (int) $quantity
                || count($serialNumbers) !== (int) $quantity
                || count($serialNumbers) !== count(array_unique($serialNumbers))
            ) {
                throw ValidationException::withMessages(['approval' => "Jumlah nomor seri {$item->product->code} tidak sesuai kuantitas."]);
            }

            foreach ($serialNumbers as $serialNumber) {
                $serialKey = $item->product_id.':'.$serialNumber;
                if (isset($allSerials[$serialKey])) {
                    throw ValidationException::withMessages(['approval' => "Nomor seri {$serialNumber} tercantum lebih dari satu kali."]);
                }

                $allSerials[$serialKey] = true;
            }

            return;
        }

        if ($serialNumbers !== []) {
            throw ValidationException::withMessages(['approval' => "Produk {$item->product->code} tidak menggunakan pelacakan nomor seri."]);
        }
    }

    /** @param array<int, string> $serialNumbers
     * @return array<int, string>
     */
    private function normalizeSerials(array $serialNumbers): array
    {
        return collect($serialNumbers)
            ->map(fn (string $serialNumber): string => mb_strtoupper(trim($serialNumber)))
            ->filter()
            ->values()
            ->all();
    }

    private function serialIsInStock(string $productId, string $serialNumber): bool
    {
        return InventoryStockMovement::query()
            ->where('product_id', $productId)
            ->where('serial_number', $serialNumber)
            ->select('location_id')
            ->selectRaw("SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END) AS balance")
            ->groupBy('location_id')
            ->havingRaw("SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END) > 0")
            ->exists();
    }
}
