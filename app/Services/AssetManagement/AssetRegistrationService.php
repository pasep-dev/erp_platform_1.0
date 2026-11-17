<?php

namespace App\Services\AssetManagement;

use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Master\Product\Product;
use App\Models\NumberingDocument;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\Procurement\GoodsReceipt;
use App\Models\Transaction\Procurement\GoodsReceiptItem;
use App\Models\Transaction\Procurement\PurchaseOrderItem;
use App\Models\User;
use App\Services\BranchAccessService;
use App\Services\DepartmentAccessService;
use App\Services\NumberingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetRegistrationService
{
    public function __construct(
        private readonly NumberingService $numberingService,
        private readonly DepartmentAccessService $departmentAccess,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function registrationQueue(): array
    {
        return GoodsReceiptItem::query()
            ->whereHas('goodsReceipt', fn ($query) => $query->where('status', GoodsReceipt::STATUS_POSTED))
            ->with([
                'goodsReceipt:id,number,source_type,purchase_order_id,purchase_request_id,company_id,department_id,received_at',
                'goodsReceipt.company:id,name',
                'goodsReceipt.purchaseOrder:id,number',
                'goodsReceipt.purchaseRequest:id,number',
                'purchaseOrderItem.product:id,code,name,group,type_id',
                'purchaseOrderItem.product.type:id,code,name',
                'purchaseRequestItem.product:id,code,name,group,type_id',
                'purchaseRequestItem.product.type:id,code,name',
            ])
            ->withCount('registeredAssets')
            ->orderBy('goods_receipt_id')
            ->orderBy('id')
            ->get()
            ->map(function (GoodsReceiptItem $item): ?array {
                $sourceItem = $this->sourceItem($item);
                $product = $sourceItem?->product;

                if ($product === null || $product->group !== 'ASSET') {
                    return null;
                }

                $acceptedQuantity = (float) $item->quantity_accepted;
                $wholeAcceptedQuantity = (int) floor($acceptedQuantity);
                $registeredCount = (int) $item->registered_assets_count;
                $pendingCount = max(0, $wholeAcceptedQuantity - $registeredCount);

                if ($pendingCount === 0) {
                    return null;
                }

                return [
                    'goods_receipt_item_id' => (string) $item->id,
                    'goods_receipt_number' => $item->goodsReceipt->number,
                    'source_number' => $item->goodsReceipt->purchaseOrder?->number
                        ?? $item->goodsReceipt->purchaseRequest?->number
                        ?? '-',
                    'received_at' => $item->goodsReceipt->received_at->toDateString(),
                    'company_name' => $item->goodsReceipt->company->name,
                    'product_name' => $product->name,
                    'product_code' => $product->code,
                    'type_name' => $product->type?->name,
                    'quantity_accepted' => $acceptedQuantity,
                    'registered_count' => $registeredCount,
                    'pending_count' => $pendingCount,
                    'has_fractional_quantity' => $acceptedQuantity !== (float) $wholeAcceptedQuantity,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function procurementDetails(string $goodsReceiptItemId): array
    {
        $receiptItem = $this->loadReceiptItem($goodsReceiptItemId);
        $this->validateProcurementSource($receiptItem);
        $sourceItem = $this->sourceItem($receiptItem);
        $receipt = $receiptItem->goodsReceipt;
        $acceptedQuantity = (float) $receiptItem->quantity_accepted;
        $registeredCount = $receiptItem->registeredAssets()->count();
        $pendingCount = (int) floor($acceptedQuantity) - $registeredCount;

        if ($pendingCount < 1) {
            throw ValidationException::withMessages([
                'goods_receipt_item_id' => 'Semua unit yang diterima baik dari item ini sudah diregistrasi.',
            ]);
        }

        return [
            'goods_receipt_item_id' => (string) $receiptItem->id,
            'goods_receipt_number' => $receipt->number,
            'source_number' => $receipt->purchaseOrder?->number ?? $receipt->purchaseRequest?->number ?? '-',
            'received_at' => $receipt->received_at->toDateString(),
            'company_id' => (string) $receipt->company_id,
            'company_name' => $receipt->company->name,
            'department_id' => (string) $receipt->department_id,
            'department_name' => $receipt->department->name,
            'product_id' => (string) $sourceItem->product->id,
            'product_code' => $sourceItem->product->code,
            'product_name' => $sourceItem->product->name,
            'category_name' => $sourceItem->product->category?->name,
            'type_name' => $sourceItem->product->type?->name,
            'brand_name' => $sourceItem->product->brand?->name,
            'model_name' => $sourceItem->product->model?->name,
            'pending_count' => $pendingCount,
            'acquisition_cost' => $sourceItem instanceof PurchaseOrderItem
                ? $sourceItem->unit_price
                : null,
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<int, Asset>
     */
    public function registerFromProcurement(User $actor, array $data): array
    {
        return DB::transaction(function () use ($actor, $data): array {
            $receiptItem = GoodsReceiptItem::query()
                ->lockForUpdate()
                ->findOrFail($data['goods_receipt_item_id']);
            $receiptItem->load([
                'goodsReceipt.company',
                'goodsReceipt.department',
                'goodsReceipt.purchaseOrder',
                'goodsReceipt.purchaseRequest',
                'purchaseOrderItem.product.category',
                'purchaseOrderItem.product.type',
                'purchaseOrderItem.product.brand',
                'purchaseOrderItem.product.model',
                'purchaseRequestItem.product.category',
                'purchaseRequestItem.product.type',
                'purchaseRequestItem.product.brand',
                'purchaseRequestItem.product.model',
            ]);
            $this->validateProcurementSource($receiptItem);

            $acceptedQuantity = (float) $receiptItem->quantity_accepted;
            if ($acceptedQuantity !== (float) (int) $acceptedQuantity) {
                throw ValidationException::withMessages([
                    'goods_receipt_item_id' => 'Kuantitas produk berjenis ASSET harus berupa unit penuh agar setiap unit memiliki identitas aset.',
                ]);
            }

            $registeredCount = $receiptItem->registeredAssets()->count();
            $units = $data['units'];
            if ($registeredCount + count($units) > (int) $acceptedQuantity) {
                throw ValidationException::withMessages([
                    'units' => 'Jumlah unit yang akan diregistrasi melebihi sisa kuantitas yang diterima baik.',
                ]);
            }

            $sourceItem = $this->sourceItem($receiptItem);
            $receipt = $receiptItem->goodsReceipt;
            $this->departmentAccess->authorize($actor, $receipt->department_id);
            $location = $this->validatedLocation($data['location_id'], (string) $receipt->company_id);
            if (! $sourceItem->product->branches()->whereKey($location->branch_id)->exists()) {
                throw ValidationException::withMessages(['product_id' => 'Produk tidak tersedia pada branch lokasi aset.']);
            }
            $this->assertCustodianDepartment($data['custodian_id'] ?? null, (string) $receipt->department_id, (string) $location->branch_id);

            $assets = [];
            foreach ($units as $index => $unit) {
                $assets[] = $this->createAsset(
                    actor: $actor,
                    product: $sourceItem->product,
                    companyId: (string) $receipt->company_id,
                    departmentId: (string) $receipt->department_id,
                    location: $location,
                    custodianId: $data['custodian_id'] ?? null,
                    acquisitionDate: $receipt->received_at->toDateString(),
                    acquisitionCost: $sourceItem instanceof PurchaseOrderItem
                        ? $sourceItem->unit_price
                        : null,
                    serialNumber: $unit['serial_number'] ?? null,
                    specifications: $this->normalizeSpecifications($unit['specifications'] ?? []),
                    warrantyDurationMonths: $this->warrantyDurationMonths($unit),
                    remarks: $data['remarks'] ?? null,
                    registrationMethod: Asset::METHOD_PROCUREMENT,
                    goodsReceiptItemId: (string) $receiptItem->id,
                    sourceUnitNumber: $registeredCount + $index + 1,
                );
            }

            return $assets;
        });
    }

    /** @param array<string, mixed> $data */
    /** @return array<int, Asset> */
    public function registerExisting(User $actor, array $data): array
    {
        return DB::transaction(function () use ($actor, $data): array {
            $product = Product::query()
                ->with(['category', 'type', 'brand', 'model'])
                ->where('group', 'ASSET')
                ->findOrFail($data['product_id']);
            $location = $this->validatedLocation($data['location_id'], $data['company_id']);
            $departmentBelongsToCompany = Department::query()
                ->whereKey($data['department_id'])
                ->where('company_id', $data['company_id'])
                ->exists();

            if (! $departmentBelongsToCompany) {
                throw ValidationException::withMessages([
                    'company_id' => 'Perusahaan aset tidak sesuai dengan data yang dipilih.',
                ]);
            }

            $this->departmentAccess->authorize($actor, $data['department_id']);
            if (! $product->departments()->whereKey($data['department_id'])->exists()) {
                throw ValidationException::withMessages(['product_id' => 'Produk belum ditautkan ke department aset.']);
            }
            if (! $product->branches()->whereKey($location->branch_id)->exists()) {
                throw ValidationException::withMessages(['product_id' => 'Produk tidak tersedia pada branch lokasi aset.']);
            }
            $this->assertCustodianDepartment($data['custodian_id'] ?? null, $data['department_id'], (string) $location->branch_id);

            $assets = [];
            foreach ($data['units'] as $unit) {
                $assets[] = $this->createAsset(
                    actor: $actor,
                    product: $product,
                    companyId: $data['company_id'],
                    departmentId: $data['department_id'],
                    location: $location,
                    custodianId: $data['custodian_id'] ?? null,
                    acquisitionDate: $data['acquisition_date'],
                    acquisitionCost: $data['acquisition_cost'] ?? null,
                    serialNumber: $unit['serial_number'] ?? null,
                    specifications: $this->normalizeSpecifications($unit['specifications'] ?? []),
                    warrantyDurationMonths: $this->warrantyDurationMonths($unit),
                    remarks: $data['remarks'] ?? null,
                    registrationMethod: Asset::METHOD_EXISTING,
                    goodsReceiptItemId: null,
                    sourceUnitNumber: null,
                );
            }

            return $assets;
        });
    }

    private function createAsset(
        User $actor,
        Product $product,
        string $companyId,
        string $departmentId,
        Location $location,
        ?int $custodianId,
        string $acquisitionDate,
        string|float|int|null $acquisitionCost,
        ?string $serialNumber,
        array $specifications,
        ?int $warrantyDurationMonths,
        ?string $remarks,
        string $registrationMethod,
        ?string $goodsReceiptItemId,
        ?int $sourceUnitNumber,
    ): Asset {
        if (
            $custodianId !== null
            && ! User::query()
                ->whereKey($custodianId)
                ->where('is_active', true)
                ->where(function ($query) use ($companyId): void {
                    $query->whereNull('company_id')->orWhere('company_id', $companyId);
                })
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'custodian_id' => 'Penanggung jawab harus aktif dan berasal dari perusahaan aset.',
            ]);
        }

        if ($product->category === null || $product->type === null || $product->brand === null) {
            throw ValidationException::withMessages([
                'product_id' => 'Data kategori, tipe, dan merek produk harus lengkap sebelum registrasi.',
            ]);
        }

        $number = $this->numberingService->generate(NumberingDocument::CODE_ASSET, [
            'company' => $location->company->code,
            'asset_type' => $product->type->code,
            'branch' => $location->branch?->code ?? $location->code,
        ]);

        $warrantyStartDate = $warrantyDurationMonths === null
            ? null
            : CarbonImmutable::parse($acquisitionDate)->toDateString();
        $warrantyEndDate = $warrantyDurationMonths === null
            ? null
            : CarbonImmutable::parse($acquisitionDate)
                ->addMonthsNoOverflow($warrantyDurationMonths)
                ->subDay()
                ->toDateString();

        return Asset::query()->create([
            'number' => $number,
            'registration_method' => $registrationMethod,
            'status' => Asset::STATUS_ACTIVE,
            'product_id' => $product->id,
            'category_id' => $product->category_id,
            'type_id' => $product->type_id,
            'brand_id' => $product->brand_id,
            'model_id' => $product->model_id,
            'company_id' => $companyId,
            'department_id' => $departmentId,
            'location_id' => $location->id,
            'branch_id' => $location->branch_id,
            'custodian_id' => $custodianId,
            'goods_receipt_item_id' => $goodsReceiptItemId,
            'source_unit_number' => $sourceUnitNumber,
            'serial_number' => $serialNumber,
            'specifications' => $specifications,
            'warranty_start_date' => $warrantyStartDate,
            'warranty_end_date' => $warrantyEndDate,
            'warranty_duration_months' => $warrantyDurationMonths,
            'acquisition_date' => $acquisitionDate,
            'acquisition_cost' => $acquisitionCost,
            'remarks' => $remarks,
            'registered_by' => $actor->id,
        ]);
    }

    private function assertCustodianDepartment(?int $custodianId, string $departmentId, string $branchId): void
    {
        if ($custodianId === null) {
            return;
        }

        $custodian = User::query()->find($custodianId);
        $belongsToDepartment = $custodian !== null && (
            $custodian->hasGlobalDepartmentAccess()
            || $custodian->department_id === $departmentId
            || $custodian->additionalDepartments()->whereKey($departmentId)->exists()
        );

        if (! $belongsToDepartment) {
            throw ValidationException::withMessages(['custodian_id' => 'Penanggung jawab harus memiliki akses ke department aset.']);
        }

        if (! app(BranchAccessService::class)->canAccess($custodian, $branchId)) {
            throw ValidationException::withMessages(['custodian_id' => 'Penanggung jawab harus memiliki akses ke branch aset.']);
        }
    }

    private function loadReceiptItem(string $goodsReceiptItemId): GoodsReceiptItem
    {
        return GoodsReceiptItem::query()
            ->with([
                'goodsReceipt.company',
                'goodsReceipt.department',
                'goodsReceipt.purchaseOrder',
                'goodsReceipt.purchaseRequest',
                'purchaseOrderItem.product.category',
                'purchaseOrderItem.product.type',
                'purchaseOrderItem.product.brand',
                'purchaseOrderItem.product.model',
                'purchaseRequestItem.product.category',
                'purchaseRequestItem.product.type',
                'purchaseRequestItem.product.brand',
                'purchaseRequestItem.product.model',
            ])
            ->findOrFail($goodsReceiptItemId);
    }

    private function validateProcurementSource(GoodsReceiptItem $receiptItem): void
    {
        if ($receiptItem->goodsReceipt?->status !== GoodsReceipt::STATUS_POSTED) {
            throw ValidationException::withMessages([
                'goods_receipt_item_id' => 'Aset hanya dapat diregistrasi dari Goods Receipt yang sudah diposting.',
            ]);
        }

        $sourceItem = $this->sourceItem($receiptItem);
        if (
            $sourceItem?->product?->group !== 'ASSET'
            || $sourceItem->product->type === null
            || (float) $receiptItem->quantity_accepted < 1
        ) {
            throw ValidationException::withMessages([
                'goods_receipt_item_id' => 'Sumber ini tidak memiliki unit produk ASSET yang diterima baik.',
            ]);
        }
    }

    private function sourceItem(GoodsReceiptItem $receiptItem): ?Model
    {
        return $receiptItem->purchaseOrderItem ?? $receiptItem->purchaseRequestItem;
    }

    private function validatedLocation(string $locationId, string $companyId): Location
    {
        $location = Location::query()
            ->with('company:id,code,name', 'branch:id,code,name')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->find($locationId);

        if ($location === null) {
            throw ValidationException::withMessages([
                'location_id' => 'Lokasi harus aktif dan berasal dari perusahaan aset.',
            ]);
        }

        return $location;
    }

    /** @param array<int, array{name: string, value: string}> $specifications
     * @return array<string, string>
     */
    private function normalizeSpecifications(array $specifications): array
    {
        $normalized = [];
        foreach ($specifications as $specification) {
            $name = trim($specification['name']);
            $value = trim($specification['value']);
            if ($name !== '' && $value !== '') {
                $normalized[$name] = $value;
            }
        }

        return $normalized;
    }

    /** @param array<string, mixed> $unit */
    private function warrantyDurationMonths(array $unit): ?int
    {
        if (! isset($unit['warranty_years'])) {
            return null;
        }

        return (int) round((float) $unit['warranty_years'] * 12);
    }
}
