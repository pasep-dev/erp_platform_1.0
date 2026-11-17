<?php

namespace App\Services\Transaction\Inventory;

use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Master\Product\Product;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetAssignmentHistory;
use App\Models\Transaction\Inventory\InventoryDepartmentWarehouse;
use App\Models\Transaction\Inventory\InventoryDocument;
use App\Models\Transaction\Inventory\InventoryDocumentItem;
use App\Models\Transaction\Inventory\InventoryRequest;
use App\Models\Transaction\Inventory\InventoryRequestItem;
use App\Models\Transaction\Inventory\InventoryStockMovement;
use App\Models\User;
use App\Services\BranchAccessService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryOperationService
{
    /** @param array<string, mixed> $data */
    public function issue(Department $department, User $actor, array $data, ?InventoryRequest $inventoryRequest = null): InventoryDocument
    {
        return DB::transaction(function () use ($department, $actor, $data, $inventoryRequest): InventoryDocument {
            if ($inventoryRequest !== null) {
                $inventoryRequest = InventoryRequest::query()->lockForUpdate()->findOrFail($inventoryRequest->id);

                if (
                    $inventoryRequest->department_id !== $department->id
                    || ! in_array($inventoryRequest->status, [InventoryRequest::STATUS_APPROVED, InventoryRequest::STATUS_PARTIALLY_FULFILLED], true)
                ) {
                    throw ValidationException::withMessages(['inventory_request' => 'Permintaan barang belum disetujui atau sudah dipenuhi.']);
                }
            }

            $location = $this->mappedWarehouse($department, (string) $data['source_location_id']);
            $this->authorizeBranch($actor, $location);
            if ($inventoryRequest !== null && $inventoryRequest->branch_id !== $location->branch_id) {
                throw ValidationException::withMessages(['source_location_id' => 'Gudang harus berada di branch permintaan.']);
            }
            $items = $data['items'];
            $products = $this->lockConsumableProducts($items);
            $document = $this->createDocument($department, $actor, InventoryDocument::TYPE_ISSUE, [
                'source_location_id' => $location->id,
                'occurred_at' => $data['occurred_at'],
                'remarks' => $data['remarks'] ?? null,
            ]);

            foreach ($items as $itemData) {
                $product = $products->get($itemData['product_id']);
                $this->assertProductBranch($product, $location);
                $quantity = (float) $itemData['quantity'];
                $requestItem = $inventoryRequest === null
                    ? null
                    : $this->lockRequestItem($inventoryRequest, $itemData, $product, $quantity);
                $serials = $this->normalizeSerials($itemData['serial_numbers'] ?? []);
                $this->assertQuantityAndSerials($product, $quantity, $serials);
                $this->assertAvailable($product, $location, $quantity, $serials);
                $this->assertParty($department, $location, $itemData);

                $item = $this->createItem($document, $product, $quantity, $itemData);
                $this->writeMovementPair(
                    document: $document,
                    item: $item,
                    product: $product,
                    from: $location,
                    to: null,
                    quantity: $quantity,
                    serials: $serials,
                    movementType: 'ISSUE',
                    actor: $actor,
                );

                if (($itemData['party_type'] ?? null) === 'ASSET') {
                    $this->recordAssetIssue($document, $item, $product, $actor);
                }

                $requestItem?->increment('fulfilled_quantity', $quantity);
            }

            if ($inventoryRequest !== null) {
                $inventoryRequest->load('items');
                $isFulfilled = $inventoryRequest->items->every(
                    fn (InventoryRequestItem $item): bool => (float) $item->fulfilled_quantity >= (float) $item->requested_quantity,
                );
                $hasFulfillment = $inventoryRequest->items->contains(
                    fn (InventoryRequestItem $item): bool => (float) $item->fulfilled_quantity > 0,
                );

                $inventoryRequest->update([
                    'status' => $isFulfilled
                        ? InventoryRequest::STATUS_FULFILLED
                        : ($hasFulfillment ? InventoryRequest::STATUS_PARTIALLY_FULFILLED : InventoryRequest::STATUS_APPROVED),
                ]);
            }

            return $document->load('items.product');
        });
    }

    /** @param array<string, mixed> $data */
    public function transfer(Department $department, User $actor, array $data): InventoryDocument
    {
        return DB::transaction(function () use ($department, $actor, $data): InventoryDocument {
            $source = $this->mappedWarehouse($department, (string) $data['source_location_id']);
            $target = $this->mappedWarehouse($department, (string) $data['target_location_id']);
            $this->authorizeBranch($actor, $source);
            $this->authorizeBranch($actor, $target);

            if ($source->id === $target->id) {
                throw ValidationException::withMessages(['target_location_id' => 'Gudang tujuan harus berbeda dari gudang asal.']);
            }

            $items = $data['items'];
            $products = $this->lockConsumableProducts($items);
            $document = $this->createDocument($department, $actor, InventoryDocument::TYPE_TRANSFER, [
                'source_location_id' => $source->id,
                'target_location_id' => $target->id,
                'occurred_at' => $data['occurred_at'],
                'remarks' => $data['remarks'] ?? null,
            ]);

            foreach ($items as $itemData) {
                $product = $products->get($itemData['product_id']);
                $this->assertProductBranch($product, $source);
                $this->assertProductBranch($product, $target);
                $quantity = (float) $itemData['quantity'];
                $serials = $this->normalizeSerials($itemData['serial_numbers'] ?? []);
                $this->assertQuantityAndSerials($product, $quantity, $serials);
                $this->assertAvailable($product, $source, $quantity, $serials);
                $item = $this->createItem($document, $product, $quantity, $itemData);

                $this->writeMovementPair(
                    document: $document,
                    item: $item,
                    product: $product,
                    from: $source,
                    to: $target,
                    quantity: $quantity,
                    serials: $serials,
                    movementType: 'TRANSFER',
                    actor: $actor,
                );
            }

            return $document->load('items.product');
        });
    }

    /** @param array<string, mixed> $data */
    public function returnStock(Department $department, User $actor, array $data): InventoryDocument
    {
        return DB::transaction(function () use ($department, $actor, $data): InventoryDocument {
            $location = $this->mappedWarehouse($department, (string) $data['target_location_id']);
            $this->authorizeBranch($actor, $location);
            $items = $data['items'];
            $products = $this->lockConsumableProducts($items);
            $document = $this->createDocument($department, $actor, InventoryDocument::TYPE_RETURN, [
                'target_location_id' => $location->id,
                'occurred_at' => $data['occurred_at'],
                'remarks' => $data['remarks'] ?? null,
            ]);

            foreach ($items as $itemData) {
                $product = $products->get($itemData['product_id']);
                $this->assertProductBranch($product, $location);
                $quantity = (float) $itemData['quantity'];
                $serials = $this->normalizeSerials($itemData['serial_numbers'] ?? []);
                $this->assertQuantityAndSerials($product, $quantity, $serials);
                $this->assertParty($department, $location, $itemData);
                $relatedIssueItem = $this->validateRelatedIssue($department, $product, $quantity, $serials, $itemData['related_issue_item_id'] ?? null);
                $this->assertSerialsAbsentFromStock($product, $serials);
                $item = $this->createItem($document, $product, $quantity, $itemData);

                $this->writeMovementPair(
                    document: $document,
                    item: $item,
                    product: $product,
                    from: null,
                    to: $location,
                    quantity: $quantity,
                    serials: $serials,
                    movementType: 'RETURN',
                    actor: $actor,
                    relatedIssueItemId: $relatedIssueItem?->id,
                );
            }

            return $document->load('items.product');
        });
    }

    /** @param array<string, mixed> $data */
    public function adjust(Department $department, User $actor, array $data): InventoryDocument
    {
        return DB::transaction(function () use ($department, $actor, $data): InventoryDocument {
            $location = $this->mappedWarehouse($department, (string) $data['location_id']);
            $this->authorizeBranch($actor, $location);
            $items = $data['items'];
            $products = $this->lockConsumableProducts($items);
            $direction = $data['direction'];
            $document = $this->createDocument($department, $actor, InventoryDocument::TYPE_ADJUSTMENT, [
                'source_location_id' => $location->id,
                'adjustment_direction' => $direction,
                'occurred_at' => $data['occurred_at'],
                'remarks' => $data['remarks'] ?? null,
            ]);

            foreach ($items as $itemData) {
                $product = $products->get($itemData['product_id']);
                $this->assertProductBranch($product, $location);
                $quantity = (float) $itemData['quantity'];
                $serials = $this->normalizeSerials($itemData['serial_numbers'] ?? []);
                $this->assertQuantityAndSerials($product, $quantity, $serials);

                if ($direction === 'OUT') {
                    $this->assertAvailable($product, $location, $quantity, $serials);
                } else {
                    $this->assertSerialsAbsentFromStock($product, $serials);
                }

                $item = $this->createItem($document, $product, $quantity, $itemData);
                $this->writeMovement(
                    document: $document,
                    item: $item,
                    product: $product,
                    location: $location,
                    direction: $direction,
                    quantity: $quantity,
                    serials: $serials,
                    movementType: 'ADJUSTMENT',
                    actor: $actor,
                );
            }

            return $document->load('items.product');
        });
    }

    private function createDocument(Department $department, User $actor, string $type, array $attributes): InventoryDocument
    {
        $id = (string) Str::ulid();
        $prefix = match ($type) {
            InventoryDocument::TYPE_ISSUE => 'GI',
            InventoryDocument::TYPE_TRANSFER => 'ST',
            InventoryDocument::TYPE_RETURN => 'RT',
            default => 'ADJ',
        };

        return InventoryDocument::query()->create(array_merge($attributes, [
            'id' => $id,
            'number' => $prefix.'-'.now()->format('Ymd').'-'.strtoupper(substr($id, -8)),
            'department_id' => $department->id,
            'document_type' => $type,
            'status' => InventoryDocument::STATUS_POSTED,
            'performed_by' => $actor->id,
        ]));
    }

    /** @param array<string, mixed> $data */
    private function createItem(InventoryDocument $document, Product $product, float $quantity, array $data): InventoryDocumentItem
    {
        return $document->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'serial_numbers' => $this->normalizeSerials($data['serial_numbers'] ?? []),
            'party_type' => $data['party_type'] ?? null,
            'party_user_id' => $data['party_user_id'] ?? null,
            'party_asset_id' => $data['party_asset_id'] ?? null,
            'party_location_id' => $data['party_location_id'] ?? null,
            'inventory_request_item_id' => $data['inventory_request_item_id'] ?? null,
            'inventory_stocktake_item_id' => $data['inventory_stocktake_item_id'] ?? null,
            'related_issue_item_id' => $data['related_issue_item_id'] ?? null,
            'specification_changes' => $data['specification_changes'] ?? null,
            'reason' => $data['reason'] ?? null,
        ]);
    }

    private function mappedWarehouse(Department $department, string $locationId): Location
    {
        $mapping = InventoryDepartmentWarehouse::query()
            ->where('department_id', $department->id)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if ($mapping === null) {
            throw ValidationException::withMessages(['location_id' => 'Gudang tidak terdaftar untuk departemen ini.']);
        }

        return Location::query()->findOrFail($mapping->location_id);
    }

    /** @param array<int, array<string, mixed>> $items
     * @return Collection<string, Product>
     */
    private function lockConsumableProducts(array $items): Collection
    {
        $ids = collect($items)->pluck('product_id')->unique()->sort()->values();
        $products = Product::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        foreach ($ids as $id) {
            $product = $products->get($id);

            if ($product === null || $product->group !== 'CONSUMABLE' || ! $product->is_active) {
                throw ValidationException::withMessages(['items' => 'Semua produk harus bertipe consumable.']);
            }
        }

        return $products;
    }

    /** @param array<int, string> $serials */
    private function assertQuantityAndSerials(Product $product, float $quantity, array $serials): void
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['items' => 'Kuantitas harus lebih besar dari nol.']);
        }

        if ($product->inventory_tracking_mode === 'SERIAL') {
            if ($quantity !== (float) (int) $quantity || count($serials) !== (int) $quantity || count(array_unique($serials)) !== count($serials)) {
                throw ValidationException::withMessages(['items' => "Masukkan nomor seri unik sebanyak kuantitas produk {$product->code}."]);
            }

            return;
        }

        if ($serials !== []) {
            throw ValidationException::withMessages(['items' => "Produk {$product->code} dilacak berdasarkan kuantitas, bukan nomor seri."]);
        }
    }

    /** @param array<int, string> $serials */
    private function assertAvailable(Product $product, Location $location, float $quantity, array $serials): void
    {
        if ($product->inventory_tracking_mode === 'SERIAL') {
            foreach ($serials as $serial) {
                $balance = $this->serialBalanceAt($product, $serial, $location);

                if ($balance < 1) {
                    throw ValidationException::withMessages(['items' => "Nomor seri {$serial} tidak tersedia di gudang {$location->name}."]);
                }
            }

            return;
        }

        if ($this->quantityBalance($product, $location) < $quantity) {
            throw ValidationException::withMessages(['items' => "Stok {$product->code} di {$location->name} tidak mencukupi."]);
        }
    }

    /** @param array<int, string> $serials */
    private function assertSerialsAbsentFromStock(Product $product, array $serials): void
    {
        foreach ($serials as $serial) {
            $isPresent = InventoryStockMovement::query()
                ->where('product_id', $product->id)
                ->where('serial_number', $serial)
                ->select('location_id')
                ->selectRaw("SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END) AS balance")
                ->groupBy('location_id')
                ->havingRaw("SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END) > 0")
                ->exists();

            if ($isPresent) {
                throw ValidationException::withMessages(['items' => "Nomor seri {$serial} masih tercatat berada dalam stok."]);
            }
        }
    }

    private function quantityBalance(Product $product, Location $location): float
    {
        return (float) InventoryStockMovement::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->whereNull('serial_number')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END), 0) AS balance")
            ->value('balance');
    }

    private function serialBalanceAt(Product $product, string $serial, Location $location): float
    {
        return (float) InventoryStockMovement::query()
            ->where('product_id', $product->id)
            ->where('serial_number', $serial)
            ->where('location_id', $location->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END), 0) AS balance")
            ->value('balance');
    }

    /** @param array<string, mixed> $item */
    private function assertParty(Department $department, Location $operationLocation, array $item): void
    {
        $type = $item['party_type'] ?? null;

        if ($type === 'USER') {
            $user = User::query()->whereKey($item['party_user_id'] ?? null)->first();

            if ($user === null
                || $user->department_id !== $department->id
                || ! app(BranchAccessService::class)->canAccess($user, $operationLocation->branch_id)) {
                throw ValidationException::withMessages(['items' => 'Pengguna tujuan harus berasal dari departemen dokumen.']);
            }
        } elseif ($type === 'ASSET') {
            $asset = Asset::query()->whereKey($item['party_asset_id'] ?? null)->lockForUpdate()->first();

            if ($asset === null || $asset->department_id !== $department->id || $asset->branch_id !== $operationLocation->branch_id || $asset->status !== Asset::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['items' => 'Aset tujuan tidak aktif atau berada di departemen lain.']);
            }
        } elseif ($type === 'LOCATION') {
            $location = Location::query()->whereKey($item['party_location_id'] ?? null)->first();

            if (
                $location === null
                || $location->company_id !== $department->company_id
                || $location->branch_id !== $operationLocation->branch_id
                || ! $location->is_active
                || in_array(mb_strtolower((string) $location->location_type), ['warehouse', 'gudang'], true)
            ) {
                throw ValidationException::withMessages(['items' => 'Lokasi tujuan harus aktif, bukan gudang, dan berasal dari perusahaan departemen.']);
            }
        } else {
            throw ValidationException::withMessages(['items' => 'Pilih tujuan pengeluaran untuk setiap baris.']);
        }

        if ($type !== 'ASSET' && ! empty($item['specification_changes'])) {
            throw ValidationException::withMessages(['items' => 'Perubahan spesifikasi hanya dapat dicatat untuk tujuan berupa aset.']);
        }
    }

    private function authorizeBranch(User $actor, Location $location): void
    {
        if (! app(BranchAccessService::class)->canAccess($actor, $location->branch_id)) {
            throw ValidationException::withMessages(['branch_id' => 'Anda tidak memiliki akses ke branch gudang ini.']);
        }
    }

    private function assertProductBranch(Product $product, Location $location): void
    {
        if (! $product->branches()->whereKey($location->branch_id)->exists()) {
            throw ValidationException::withMessages(['items' => 'Produk tidak tersedia pada branch gudang yang dipilih.']);
        }
    }

    /** @param array<string, mixed> $itemData */
    private function lockRequestItem(InventoryRequest $inventoryRequest, array $itemData, Product $product, float $quantity): InventoryRequestItem
    {
        $requestItem = InventoryRequestItem::query()
            ->where('inventory_request_id', $inventoryRequest->id)
            ->whereKey($itemData['inventory_request_item_id'] ?? '')
            ->lockForUpdate()
            ->first();

        if ($requestItem === null || $requestItem->product_id !== $product->id) {
            throw ValidationException::withMessages(['items' => 'Baris barang tidak sesuai dengan permintaan ini.']);
        }

        if ((float) $requestItem->fulfilled_quantity + $quantity > (float) $requestItem->requested_quantity) {
            throw ValidationException::withMessages(['items' => "Kuantitas pemenuhan {$product->code} melebihi sisa permintaan."]);
        }

        return $requestItem;
    }

    private function recordAssetIssue(
        InventoryDocument $document,
        InventoryDocumentItem $item,
        Product $product,
        User $actor,
    ): void {
        $asset = Asset::query()->lockForUpdate()->findOrFail($item->party_asset_id);
        $oldSpecifications = $asset->specifications ?? [];
        $changes = collect($item->specification_changes ?? [])
            ->mapWithKeys(fn (mixed $value, string|int $key): array => [trim((string) $key) => trim((string) $value)])
            ->filter(fn (string $value, string $key): bool => $key !== '' && $value !== '')
            ->all();
        $newSpecifications = array_merge($oldSpecifications, $changes);

        if ($changes !== []) {
            $asset->update(['specifications' => $newSpecifications]);
        }

        AssetAssignmentHistory::query()->create([
            'asset_id' => $asset->id,
            'event_type' => $changes === [] ? 'CONSUMABLE_ISSUED' : 'UPGRADE',
            'from_custodian_id' => $asset->custodian_id,
            'to_custodian_id' => $asset->custodian_id,
            'from_snapshot' => ['specifications' => $oldSpecifications],
            'to_snapshot' => [
                'specifications' => $newSpecifications,
                'inventory_document' => $document->number,
                'product_code' => $product->code,
                'quantity' => (float) $item->quantity,
                'serial_numbers' => $item->serial_numbers,
            ],
            'performed_by' => $actor->id,
            'effective_at' => $document->occurred_at->toDateString(),
            'remarks' => $item->reason ?? "Pengeluaran {$product->code} melalui {$document->number}.",
        ]);
    }

    private function validateRelatedIssue(
        Department $department,
        Product $product,
        float $quantity,
        array $serials,
        ?string $issueItemId,
    ): ?InventoryDocumentItem {
        if ($issueItemId === null) {
            return null;
        }

        $issueItem = InventoryDocumentItem::query()
            ->with('document')
            ->lockForUpdate()
            ->find($issueItemId);

        if (
            $issueItem === null
            || $issueItem->document?->document_type !== InventoryDocument::TYPE_ISSUE
            || $issueItem->document?->department_id !== $department->id
            || $issueItem->product_id !== $product->id
        ) {
            throw ValidationException::withMessages(['items' => 'Referensi pengeluaran asal tidak valid.']);
        }

        $returnedQuantity = (float) InventoryStockMovement::query()
            ->where('related_issue_item_id', $issueItem->id)
            ->where('direction', 'IN')
            ->sum('quantity');

        if ($returnedQuantity + $quantity > (float) $issueItem->quantity) {
            throw ValidationException::withMessages(['items' => 'Jumlah retur melebihi kuantitas yang pernah dikeluarkan.']);
        }

        if ($serials !== []) {
            $issuedSerials = InventoryStockMovement::query()
                ->where('inventory_document_item_id', $issueItem->id)
                ->where('direction', 'OUT')
                ->whereNotNull('serial_number')
                ->pluck('serial_number')
                ->all();

            if (array_diff($serials, $issuedSerials) !== []) {
                throw ValidationException::withMessages(['items' => 'Nomor seri retur tidak cocok dengan pengeluaran asal.']);
            }
        }

        return $issueItem;
    }

    /** @param array<int, string> $serials */
    private function writeMovementPair(
        InventoryDocument $document,
        InventoryDocumentItem $item,
        Product $product,
        ?Location $from,
        ?Location $to,
        float $quantity,
        array $serials,
        string $movementType,
        User $actor,
        ?string $relatedIssueItemId = null,
    ): void {
        if ($from !== null) {
            $this->writeMovement($document, $item, $product, $from, 'OUT', $quantity, $serials, $movementType, $actor, $relatedIssueItemId);
        }

        if ($to !== null) {
            $this->writeMovement($document, $item, $product, $to, 'IN', $quantity, $serials, $movementType, $actor, $relatedIssueItemId);
        }
    }

    /** @param array<int, string> $serials */
    private function writeMovement(
        InventoryDocument $document,
        InventoryDocumentItem $item,
        Product $product,
        Location $location,
        string $direction,
        float $quantity,
        array $serials,
        string $movementType,
        User $actor,
        ?string $relatedIssueItemId = null,
    ): void {
        $units = $serials === [] ? [null] : $serials;

        foreach ($units as $serial) {
            $key = implode(':', [$document->id, $item->id, $direction, $serial ?? 'QTY']);
            InventoryStockMovement::query()->create([
                'product_id' => $product->id,
                'location_id' => $location->id,
                'department_id' => $document->department_id,
                'movement_type' => $movementType,
                'direction' => $direction,
                'quantity' => $serial === null ? $quantity : 1,
                'serial_number' => $serial,
                'idempotency_key' => hash('sha256', $key),
                'source_type' => 'INVENTORY_'.$document->document_type,
                'source_id' => $document->id,
                'source_line_id' => $item->id,
                'inventory_document_item_id' => $item->id,
                'related_issue_item_id' => $relatedIssueItemId,
                'reference_number' => $document->number,
                'occurred_at' => $document->occurred_at,
                'performed_by' => $actor->id,
            ]);
        }
    }

    /** @param array<int, string>|null $serials
     * @return array<int, string>
     */
    private function normalizeSerials(?array $serials): array
    {
        return collect($serials ?? [])
            ->map(fn (string $serial): string => mb_strtoupper(trim($serial)))
            ->filter()
            ->values()
            ->all();
    }
}
