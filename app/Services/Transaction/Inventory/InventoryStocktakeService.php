<?php

namespace App\Services\Transaction\Inventory;

use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Master\Product\Product;
use App\Models\Transaction\Inventory\InventoryDepartmentWarehouse;
use App\Models\Transaction\Inventory\InventoryStockMovement;
use App\Models\Transaction\Inventory\InventoryStocktake;
use App\Models\Transaction\Inventory\InventoryStocktakeItem;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryStocktakeService
{
    public function __construct(
        private readonly InventoryOperationService $inventoryOperationService,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function createSnapshot(Department $department, Location $location, User $actor, ?string $remarks): InventoryStocktake
    {
        return DB::transaction(function () use ($department, $location, $actor, $remarks): InventoryStocktake {
            $this->lockMappedWarehouse($department, $location);
            $products = Product::query()
                ->where('group', 'CONSUMABLE')
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($products->isEmpty()) {
                throw ValidationException::withMessages(['department_id' => 'Belum ada produk consumable aktif yang dapat dihitung.']);
            }

            $id = (string) Str::ulid();
            $stocktake = InventoryStocktake::query()->create([
                'id' => $id,
                'number' => 'SO-'.now()->format('Ymd').'-'.strtoupper(substr($id, -8)),
                'department_id' => $department->id,
                'location_id' => $location->id,
                'initiated_by' => $actor->id,
                'status' => InventoryStocktake::STATUS_DRAFT,
                'remarks' => $remarks,
            ]);

            foreach ($products as $product) {
                $serials = $product->inventory_tracking_mode === 'SERIAL'
                    ? $this->serialsAt($product, $location)
                    : [];

                $stocktake->items()->create([
                    'product_id' => $product->id,
                    'snapshot_quantity' => $this->balanceAt($product, $location),
                    'snapshot_serial_numbers' => $product->inventory_tracking_mode === 'SERIAL' ? $serials : null,
                ]);
            }

            $this->activityLogger->log(
                'CREATED', 'INVENTORY_STOCKTAKE', "Sesi opname {$stocktake->number} dibuat.",
                InventoryStocktake::class, $stocktake->id, null,
                ['department_id' => $department->id, 'location_id' => $location->id, 'items' => $products->count()],
            );

            return $stocktake;
        });
    }

    /** @param array<int, array{id: string, counted_quantity: float, serial_numbers: array<int, string>, remarks: ?string}> $items */
    public function saveCounts(InventoryStocktake $stocktake, array $items): InventoryStocktake
    {
        return DB::transaction(function () use ($stocktake, $items): InventoryStocktake {
            $locked = InventoryStocktake::query()->lockForUpdate()->findOrFail($stocktake->id);

            if ($locked->status === InventoryStocktake::STATUS_POSTED) {
                throw ValidationException::withMessages(['stocktake' => 'Sesi opname sudah diposting dan tidak dapat diubah.']);
            }

            $stocktakeItems = $locked->items()->with('product')->orderBy('product_id')->lockForUpdate()->get()->keyBy('id');
            $oldCounts = [];
            $newCounts = [];

            foreach ($items as $count) {
                $item = $stocktakeItems->get($count['id']);

                if ($item === null) {
                    throw ValidationException::withMessages(['items' => 'Ada baris produk yang tidak termasuk dalam sesi opname ini.']);
                }

                $serials = $this->normalizeSerials($count['serial_numbers']);
                $quantity = (float) $count['counted_quantity'];

                if ($item->product->inventory_tracking_mode === 'SERIAL') {
                    if ($quantity !== (float) count($serials) || count($serials) !== count(array_unique($serials))) {
                        throw ValidationException::withMessages(['items' => "Nomor seri unik harus sesuai jumlah hitung untuk {$item->product->code}."]);
                    }
                } elseif ($serials !== []) {
                    throw ValidationException::withMessages(['items' => "Produk {$item->product->code} tidak menggunakan pelacakan nomor seri."]);
                }

                $oldCounts[$item->id] = [
                    'counted_quantity' => $item->counted_quantity,
                    'counted_serial_numbers' => $item->counted_serial_numbers,
                ];

                $item->update([
                    'counted_quantity' => $quantity,
                    'counted_serial_numbers' => $item->product->inventory_tracking_mode === 'SERIAL' ? $serials : null,
                    'remarks' => $count['remarks'],
                ]);
                $newCounts[$item->id] = [
                    'counted_quantity' => $quantity,
                    'counted_serial_numbers' => $item->product->inventory_tracking_mode === 'SERIAL' ? $serials : null,
                ];
            }

            $complete = ! $stocktakeItems->contains(fn (InventoryStocktakeItem $item): bool => $item->fresh()->counted_quantity === null);
            $locked->update([
                'status' => $complete ? InventoryStocktake::STATUS_COUNTED : InventoryStocktake::STATUS_DRAFT,
                'counted_at' => $complete ? now() : null,
            ]);
            $this->activityLogger->log(
                'COUNT_UPDATED', 'INVENTORY_STOCKTAKE', "Hasil hitung sesi {$locked->number} diperbarui.",
                InventoryStocktake::class, $locked->id, $oldCounts, $newCounts,
            );

            return $locked->load('items.product');
        });
    }

    public function post(InventoryStocktake $stocktake, User $actor): InventoryStocktake
    {
        return DB::transaction(function () use ($stocktake, $actor): InventoryStocktake {
            $locked = InventoryStocktake::query()->lockForUpdate()->findOrFail($stocktake->id);

            if ($locked->status !== InventoryStocktake::STATUS_COUNTED) {
                throw ValidationException::withMessages(['stocktake' => 'Lengkapi seluruh hasil hitung sebelum memposting opname.']);
            }

            $this->lockMappedWarehouse($locked->department, $locked->location);
            $items = $locked->items()->with('product')->orderBy('product_id')->lockForUpdate()->get();
            Product::query()->whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get();
            $increases = [];
            $decreases = [];

            foreach ($items as $item) {
                $product = $item->product;
                $bookQuantity = $this->balanceAt($product, $locked->location);
                $bookSerials = $product->inventory_tracking_mode === 'SERIAL' ? $this->serialsAt($product, $locked->location) : [];
                $snapshotSerials = $this->normalizeSerials($item->snapshot_serial_numbers ?? []);

                if (
                    abs($bookQuantity - (float) $item->snapshot_quantity) >= 0.0005
                    || ($product->inventory_tracking_mode === 'SERIAL' && $bookSerials !== $snapshotSerials)
                ) {
                    throw ValidationException::withMessages([
                        'stocktake' => "Stok {$product->code} berubah setelah sesi dibuat. Buat sesi opname baru agar koreksi memakai saldo terbaru.",
                    ]);
                }

                if ($product->inventory_tracking_mode === 'SERIAL') {
                    $countedSerials = $this->normalizeSerials($item->counted_serial_numbers ?? []);
                    $removed = array_values(array_diff($bookSerials, $countedSerials));
                    $added = array_values(array_diff($countedSerials, $bookSerials));

                    if ($removed !== []) {
                        $decreases[] = $this->adjustmentItem($item, count($removed), $removed);
                    }

                    if ($added !== []) {
                        $increases[] = $this->adjustmentItem($item, count($added), $added);
                    }

                    continue;
                }

                $difference = round((float) $item->counted_quantity - $bookQuantity, 3);

                if (abs($difference) < 0.0005) {
                    continue;
                }

                $target = $difference > 0 ? 'increases' : 'decreases';
                ${$target}[] = $this->adjustmentItem($item, abs($difference), []);
            }

            foreach ([['IN', $increases], ['OUT', $decreases]] as [$direction, $adjustmentItems]) {
                if ($adjustmentItems === []) {
                    continue;
                }

                $this->inventoryOperationService->adjust($locked->department, $actor, [
                    'location_id' => $locked->location_id,
                    'direction' => $direction,
                    'occurred_at' => now(),
                    'remarks' => "Koreksi hasil stock opname {$locked->number}.",
                    'items' => $adjustmentItems,
                ]);
            }

            $locked->update(['status' => InventoryStocktake::STATUS_POSTED, 'posted_at' => now()]);
            $this->activityLogger->log(
                'POSTED', 'INVENTORY_STOCKTAKE', "Hasil opname {$locked->number} diposting.",
                InventoryStocktake::class, $locked->id,
                ['status' => InventoryStocktake::STATUS_COUNTED],
                ['status' => InventoryStocktake::STATUS_POSTED, 'increased_items' => count($increases), 'decreased_items' => count($decreases)],
            );

            return $locked->load('items.product');
        });
    }

    private function lockMappedWarehouse(Department $department, Location $location): void
    {
        $mapping = InventoryDepartmentWarehouse::query()
            ->where('department_id', $department->id)
            ->where('location_id', $location->id)
            ->lockForUpdate()
            ->first();

        if ($mapping === null) {
            throw ValidationException::withMessages(['location_id' => 'Gudang tidak terdaftar untuk departemen ini.']);
        }
    }

    private function balanceAt(Product $product, Location $location): float
    {
        return (float) InventoryStockMovement::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END), 0) AS balance")
            ->value('balance');
    }

    /** @return array<int, string> */
    private function serialsAt(Product $product, Location $location): array
    {
        return InventoryStockMovement::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->whereNotNull('serial_number')
            ->select('serial_number')
            ->selectRaw("SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END) AS balance")
            ->groupBy('serial_number')
            ->havingRaw("SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END) > 0")
            ->orderBy('serial_number')
            ->pluck('serial_number')
            ->all();
    }

    /** @param array<int, string> $serials
     * @return array<int, string>
     */
    private function normalizeSerials(array $serials): array
    {
        $serials = array_map(fn (string $serial): string => mb_strtoupper(trim($serial)), $serials);
        $serials = array_values(array_filter($serials, fn (string $serial): bool => $serial !== ''));
        sort($serials);

        return $serials;
    }

    /** @param array<int, string> $serials
     * @return array<string, mixed>
     */
    private function adjustmentItem(InventoryStocktakeItem $item, float $quantity, array $serials): array
    {
        return [
            'product_id' => $item->product_id,
            'quantity' => $quantity,
            'serial_numbers' => $serials,
            'reason' => "Hasil hitung {$item->product->code} pada {$item->stocktake->number}.",
            'inventory_stocktake_item_id' => $item->id,
        ];
    }
}
