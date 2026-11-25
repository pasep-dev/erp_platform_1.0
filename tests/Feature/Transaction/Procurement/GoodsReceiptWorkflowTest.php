<?php

namespace Tests\Feature\Transaction\Procurement;

use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Master\Procurement\ServiceType;
use App\Models\Master\Procurement\UnitOfMeasure;
use App\Models\Master\Procurement\Vendor;
use App\Models\Master\Procurement\VendorType;
use App\Models\Master\Product\Brand;
use App\Models\Master\Product\Category;
use App\Models\Master\Product\Model as ProductModel;
use App\Models\Master\Product\Product;
use App\Models\Master\Product\Type;
use App\Models\NumberingDocument;
use App\Models\Transaction\Inventory\InventoryDepartmentWarehouse;
use App\Models\Transaction\Inventory\InventoryReceiptIntegration;
use App\Models\Transaction\Procurement\GoodsReceipt;
use App\Models\Transaction\Procurement\PurchaseOrder;
use App\Models\Transaction\Procurement\PurchaseRequest;
use App\Models\User;
use App\Services\Transaction\Inventory\InventoryStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GoodsReceiptWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_order_can_be_received_partially_and_then_completed(): void
    {
        [$receiver, $company, $department, $vendor, $serviceType, $unit] = $this->createProcurementContext('po-receive');
        $this->grantGoodsReceiptPermissions($receiver);
        $this->createGoodsReceiptNumbering();
        $purchaseRequest = $this->createPurchaseRequest($receiver, $company, $department, $serviceType, $unit, true);
        $purchaseOrder = $this->createPurchaseOrder($purchaseRequest, $company, $department, $vendor, $serviceType, $unit, 5);

        $this->actingAs($receiver)
            ->get(route('transaction.procurement.goods-receipts.create', [
                'source_type' => GoodsReceipt::SOURCE_PURCHASE_ORDER,
                'source_id' => $purchaseOrder->id,
            ]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transaction/Procurement/GoodReceive/Create')
                ->where('source.company_id', $company->id));

        $this->actingAs($receiver)
            ->get(route('transaction.procurement.goods-receipts.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transaction/Procurement/GoodReceive/Index')
                ->where('queue.0.source_type', GoodsReceipt::SOURCE_PURCHASE_ORDER)
                ->where('queue.0.source_id', $purchaseOrder->id)
                ->where('queue.0.outstanding_quantity', 5)
                ->where('queue.0.timeline.0.label', 'PR dibuat')
                ->where('queue.0.timeline.1.label', 'PR disetujui')
                ->where('queue.0.timeline.2.label', 'PO dibuat')
                ->where('queue.0.timeline.3.label', 'PO dikirim')
                ->where('queue.0.timeline.4.label', 'Menunggu GR'));

        $firstReceipt = $this->createDraftReceipt($receiver, $purchaseOrder, 3, 2, 1, '1 unit has a damaged case.');
        $this->actingAs($receiver)
            ->post(route('transaction.procurement.goods-receipts.submit', $firstReceipt))
            ->assertRedirect(route('transaction.procurement.goods-receipts.show', $firstReceipt));

        $this->assertDatabaseHas('trx_purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'PARTIALLY_RECEIVED',
        ]);
        $this->assertDatabaseHas('trx_goods_receipt_items', [
            'goods_receipt_id' => $firstReceipt->id,
            'quantity_received' => 3,
            'quantity_accepted' => 2,
            'quantity_rejected' => 1,
        ]);
        $this->actingAs($receiver)
            ->get(route('transaction.procurement.goods-receipts.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('queue.0.queue_status', 'PARTIALLY_RECEIVED')
                ->where('queue.0.receipts.0.number', $firstReceipt->number)
                ->where('queue.0.receipts.0.status', GoodsReceipt::STATUS_POSTED));

        $secondReceipt = $this->createDraftReceipt($receiver, $purchaseOrder, 3, 3, 0);
        $this->actingAs($receiver)
            ->post(route('transaction.procurement.goods-receipts.submit', $secondReceipt))
            ->assertRedirect(route('transaction.procurement.goods-receipts.show', $secondReceipt));

        $this->assertDatabaseHas('trx_purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'RECEIVED',
        ]);
        $this->assertDatabaseHas('trx_goods_receipts', [
            'id' => $firstReceipt->id,
            'status' => GoodsReceipt::STATUS_POSTED,
            'posted_by' => $receiver->id,
        ]);

        $this->actingAs($receiver)
            ->get(route('transaction.procurement.goods-receipts.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('queue.0.queue_status', 'RECEIVED')
                ->where('queue.0.outstanding_quantity', 0)
                ->has('queue.0.receipts', 2));
    }

    public function test_approved_pr_without_po_can_be_received_directly(): void
    {
        [$receiver, $company, $department, $vendor, $serviceType, $unit] = $this->createProcurementContext('pr-direct-receive');
        $this->grantGoodsReceiptPermissions($receiver);
        $this->createGoodsReceiptNumbering();
        $purchaseRequest = $this->createPurchaseRequest($receiver, $company, $department, $serviceType, $unit, false, $vendor);

        $this->actingAs($receiver)
            ->get(route('transaction.procurement.goods-receipts.create', [
                'source_type' => GoodsReceipt::SOURCE_PURCHASE_REQUEST,
                'source_id' => $purchaseRequest->id,
                'vendor_id' => $vendor->id,
            ]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transaction/Procurement/GoodReceive/Create')
                ->where('source.company_id', $company->id));

        $this->actingAs($receiver)
            ->get(route('transaction.procurement.goods-receipts.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('queue.0.source_type', GoodsReceipt::SOURCE_PURCHASE_REQUEST)
                ->where('queue.0.source_id', $purchaseRequest->id)
                ->where('queue.0.vendor', $vendor->name));

        $this->actingAs($receiver)
            ->post(route('transaction.procurement.goods-receipts.store'), [
                'source_type' => GoodsReceipt::SOURCE_PURCHASE_REQUEST,
                'source_id' => $purchaseRequest->id,
                'vendor_id' => $vendor->id,
                'received_at' => now()->toDateString(),
                'delivery_note_number' => 'ECOM-DELIVERY-01',
                'items' => [[
                    'source_item_id' => $purchaseRequest->items()->firstOrFail()->id,
                    'quantity_received' => 5,
                    'quantity_accepted' => 5,
                    'quantity_rejected' => 0,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $goodsReceipt = GoodsReceipt::query()->firstOrFail();
        $this->actingAs($receiver)
            ->post(route('transaction.procurement.goods-receipts.submit', $goodsReceipt))
            ->assertRedirect(route('transaction.procurement.goods-receipts.show', $goodsReceipt));

        $this->assertDatabaseHas('trx_goods_receipts', [
            'id' => $goodsReceipt->id,
            'purchase_request_id' => $purchaseRequest->id,
            'purchase_order_id' => null,
            'status' => GoodsReceipt::STATUS_POSTED,
        ]);
        $this->assertDatabaseHas('trx_purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'APPROVED',
        ]);
        $this->actingAs($receiver)
            ->get(route('transaction.procurement.goods-receipts.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('queue.0.queue_status', 'RECEIVED')
                ->where('queue.0.outstanding_quantity', 0));
    }

    public function test_posted_goods_receipt_adds_consumables_to_the_mapped_warehouse_once(): void
    {
        [$receiver, $company, $department, $vendor, $serviceType, $unit] = $this->createProcurementContext('inventory-gr');
        $receiver->update(['department_id' => $department->id]);
        $this->grantGoodsReceiptPermissions($receiver);
        $receiver->givePermissionTo(Permission::query()->firstOrCreate([
            'name' => 'consumable.view',
            'guard_name' => 'web',
        ]));
        $this->createGoodsReceiptNumbering();
        $product = $this->createInventoryProduct('INV-GR-01');
        $warehouse = Location::query()->create([
            'company_id' => $company->id,
            'code' => 'WH-INV-01',
            'name' => 'Inventory Warehouse',
            'location_type' => 'Warehouse',
            'is_active' => true,
        ]);
        InventoryDepartmentWarehouse::query()->create([
            'department_id' => $department->id,
            'location_id' => $warehouse->id,
            'configured_by' => $receiver->id,
            'is_default' => true,
        ]);
        $purchaseRequest = $this->createPurchaseRequest($receiver, $company, $department, $serviceType, $unit, false, $vendor, $product);

        $this->actingAs($receiver)
            ->post(route('transaction.procurement.goods-receipts.store'), [
                'source_type' => GoodsReceipt::SOURCE_PURCHASE_REQUEST,
                'source_id' => $purchaseRequest->id,
                'vendor_id' => $vendor->id,
                'received_at' => now()->toDateString(),
                'items' => [[
                    'source_item_id' => $purchaseRequest->items()->firstOrFail()->id,
                    'quantity_received' => 3,
                    'quantity_accepted' => 2,
                    'quantity_rejected' => 1,
                    'remarks' => '1 item has a damaged package.',
                ]],
            ])->assertSessionHasNoErrors();

        $goodsReceipt = GoodsReceipt::query()->firstOrFail();
        $this->actingAs($receiver)
            ->post(route('transaction.procurement.goods-receipts.submit', $goodsReceipt))
            ->assertRedirect(route('transaction.procurement.goods-receipts.show', $goodsReceipt));

        $receiptItem = $goodsReceipt->items()->firstOrFail();
        app(InventoryStockService::class)->capturePostedReceipt($goodsReceipt->fresh());

        $this->assertDatabaseCount('trx_inventory_stock_movements', 1);
        $this->assertDatabaseHas('trx_inventory_stock_movements', [
            'product_id' => $product->id,
            'location_id' => $warehouse->id,
            'source_line_id' => $receiptItem->id,
            'quantity' => 2,
            'direction' => 'IN',
        ]);
        $this->assertSame(2.0, app(InventoryStockService::class)->stockCard($product, $warehouse)['balance']);
        $this->assertDatabaseHas('trx_goods_receipts', [
            'id' => $goodsReceipt->id,
            'status' => GoodsReceipt::STATUS_POSTED,
        ]);

        $this->actingAs($receiver)
            ->get(route('transaction.inventory.receipts.index', ['department_id' => $department->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transaction/Inventory/Receipts/Index')
                ->where('receipts.data.0.record_type', 'GOODS_RECEIPT')
                ->where('receipts.data.0.status', InventoryReceiptIntegration::STATUS_PROCESSED)
                ->where('receipts.data.0.product_code', $product->code)
                ->where('receipts.data.0.document_number', $goodsReceipt->number)
                ->where('receipts.data.0.warehouse', $warehouse->name));
    }

    public function test_goods_receipt_still_posts_without_a_warehouse_and_waits_for_warehouse_mapping(): void
    {
        [$receiver, $company, $department, $vendor, $serviceType, $unit] = $this->createProcurementContext('inventory-pending');
        $this->grantGoodsReceiptPermissions($receiver);
        $this->createGoodsReceiptNumbering();
        NumberingDocument::query()->where('code', 'GOODS_RECEIPT')->update([
            'pattern' => '{COMPANY}/{BRANCH}/{LOCATION}/{DOCUMENT_TYPE}/{DEPARTMENT}/{YYYY}/{SEQ}',
        ]);
        $product = $this->createInventoryProduct('INV-PENDING-01');
        $purchaseRequest = $this->createPurchaseRequest($receiver, $company, $department, $serviceType, $unit, false, $vendor, $product);

        $this->actingAs($receiver)->post(route('transaction.procurement.goods-receipts.store'), [
            'source_type' => GoodsReceipt::SOURCE_PURCHASE_REQUEST,
            'source_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'received_at' => now()->toDateString(),
            'items' => [[
                'source_item_id' => $purchaseRequest->items()->firstOrFail()->id,
                'quantity_received' => 2,
                'quantity_accepted' => 2,
                'quantity_rejected' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $goodsReceipt = GoodsReceipt::query()->firstOrFail();
        $this->actingAs($receiver)
            ->post(route('transaction.procurement.goods-receipts.submit', $goodsReceipt))
            ->assertRedirect(route('transaction.procurement.goods-receipts.show', $goodsReceipt));

        $this->assertDatabaseHas('trx_goods_receipts', [
            'id' => $goodsReceipt->id,
            'status' => GoodsReceipt::STATUS_POSTED,
            'warehouse_location_id' => null,
        ]);
        $this->assertDatabaseHas('trx_inventory_receipt_integrations', [
            'goods_receipt_item_id' => $goodsReceipt->items()->firstOrFail()->id,
            'status' => InventoryReceiptIntegration::STATUS_PENDING_WAREHOUSE,
        ]);
        $this->assertDatabaseCount('trx_inventory_stock_movements', 0);
    }

    public function test_serial_tracked_receipt_adds_a_movement_for_each_recorded_serial(): void
    {
        [$receiver, $company, $department, $vendor, $serviceType, $unit] = $this->createProcurementContext('inventory-serial');
        $receiver->update(['department_id' => $department->id]);
        $this->grantGoodsReceiptPermissions($receiver);
        $this->createGoodsReceiptNumbering();
        $product = $this->createInventoryProduct('INV-SERIAL-01', 'SERIAL');
        $warehouse = Location::query()->create([
            'company_id' => $company->id,
            'code' => 'WH-SER-01',
            'name' => 'Serial Warehouse',
            'location_type' => 'Warehouse',
            'is_active' => true,
        ]);
        InventoryDepartmentWarehouse::query()->create([
            'department_id' => $department->id,
            'location_id' => $warehouse->id,
            'configured_by' => $receiver->id,
            'is_default' => true,
        ]);
        $purchaseRequest = $this->createPurchaseRequest($receiver, $company, $department, $serviceType, $unit, false, $vendor, $product);

        $this->actingAs($receiver)->post(route('transaction.procurement.goods-receipts.store'), [
            'source_type' => GoodsReceipt::SOURCE_PURCHASE_REQUEST,
            'source_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'received_at' => now()->toDateString(),
            'items' => [[
                'source_item_id' => $purchaseRequest->items()->firstOrFail()->id,
                'quantity_received' => 2,
                'quantity_accepted' => 2,
                'quantity_rejected' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $goodsReceipt = GoodsReceipt::query()->firstOrFail();
        $this->actingAs($receiver)
            ->post(route('transaction.procurement.goods-receipts.submit', $goodsReceipt))
            ->assertRedirect(route('transaction.procurement.goods-receipts.show', $goodsReceipt));

        $integration = InventoryReceiptIntegration::query()->firstOrFail();
        $this->assertSame(InventoryReceiptIntegration::STATUS_PENDING_SERIALS, $integration->status);
        $this->actingAs($receiver)
            ->post(route('transaction.inventory.receipt-integrations.serials', $integration), [
                'serial_numbers' => ['sn-001', 'SN-001'],
            ])
            ->assertSessionHasErrors('serial_numbers');

        $this->actingAs($receiver)
            ->post(route('transaction.inventory.receipt-integrations.serials', $integration), [
                'serial_numbers' => ['sn-001', 'sn-002'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('trx_inventory_stock_movements', 2);
        $this->assertDatabaseHas('trx_inventory_stock_movements', [
            'product_id' => $product->id,
            'location_id' => $warehouse->id,
            'serial_number' => 'SN-001',
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('trx_inventory_receipt_integrations', [
            'id' => $integration->id,
            'status' => InventoryReceiptIntegration::STATUS_PROCESSED,
        ]);
    }

    public function test_purchase_orders_and_direct_prs_share_one_goods_receipt_queue(): void
    {
        [$receiver, $company, $department, $vendor, $serviceType, $unit] = $this->createProcurementContext('unified-gr-queue');
        $this->grantGoodsReceiptPermissions($receiver);
        $poPurchaseRequest = $this->createPurchaseRequest($receiver, $company, $department, $serviceType, $unit, true);
        $purchaseOrder = $this->createPurchaseOrder($poPurchaseRequest, $company, $department, $vendor, $serviceType, $unit, 5);
        $directPurchaseRequest = $this->createPurchaseRequest($receiver, $company, $department, $serviceType, $unit, false, $vendor);

        $this->actingAs($receiver)
            ->get(route('transaction.procurement.goods-receipts.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transaction/Procurement/GoodReceive/Index')
                ->has('queue', 2)
                ->where('queue.0.source_type', GoodsReceipt::SOURCE_PURCHASE_ORDER)
                ->where('queue.0.source_id', $purchaseOrder->id)
                ->where('queue.1.source_type', GoodsReceipt::SOURCE_PURCHASE_REQUEST)
                ->where('queue.1.source_id', $directPurchaseRequest->id));
    }

    public function test_stale_draft_cannot_overreceive_after_another_receipt_is_posted(): void
    {
        [$receiver, $company, $department, $vendor, $serviceType, $unit] = $this->createProcurementContext('po-overreceive');
        $this->grantGoodsReceiptPermissions($receiver);
        $this->createGoodsReceiptNumbering();
        $purchaseRequest = $this->createPurchaseRequest($receiver, $company, $department, $serviceType, $unit, true);
        $purchaseOrder = $this->createPurchaseOrder($purchaseRequest, $company, $department, $vendor, $serviceType, $unit, 5);
        $staleDraft = $this->createDraftReceipt($receiver, $purchaseOrder, 4, 4, 0);
        $firstReceipt = $this->createDraftReceipt($receiver, $purchaseOrder, 3, 3, 0);

        $this->actingAs($receiver)
            ->post(route('transaction.procurement.goods-receipts.submit', $firstReceipt));

        $this->actingAs($receiver)
            ->from(route('transaction.procurement.goods-receipts.show', $staleDraft))
            ->post(route('transaction.procurement.goods-receipts.submit', $staleDraft))
            ->assertRedirect(route('transaction.procurement.goods-receipts.show', $staleDraft))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('trx_goods_receipts', [
            'id' => $staleDraft->id,
            'status' => GoodsReceipt::STATUS_DRAFT,
        ]);
    }

    /** @return array{User, Company, Department, Vendor, ServiceType, UnitOfMeasure} */
    private function createProcurementContext(string $prefix): array
    {
        $user = User::query()->create([
            'name' => 'Goods Receipt Receiver',
            'email' => "{$prefix}@example.test",
            'password' => 'password',
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'code' => strtoupper(substr($prefix, 0, 8)),
            'name' => 'Goods Receipt Company',
            'is_active' => true,
        ]);
        $department = Department::query()->create([
            'company_id' => $company->id,
            'code' => strtoupper(substr($prefix, 0, 8)).'-D',
            'name' => 'Goods Receipt Department',
            'is_active' => true,
        ]);
        $vendorType = VendorType::query()->create([
            'code' => strtoupper(substr($prefix, 0, 8)).'-VT',
            'name' => 'Supplier',
            'is_active' => true,
        ]);
        $vendor = Vendor::query()->create([
            'vendor_type_id' => $vendorType->id,
            'code' => strtoupper(substr($prefix, 0, 8)).'-V',
            'name' => 'Goods Receipt Vendor',
            'is_active' => true,
        ]);
        $serviceType = ServiceType::query()->create([
            'code' => strtoupper(substr($prefix, 0, 8)).'-S',
            'name' => 'Goods Receipt Service',
            'requires_asset' => false,
            'is_active' => true,
        ]);
        $unit = UnitOfMeasure::query()->create([
            'code' => strtoupper(substr($prefix, 0, 8)).'-U',
            'name' => 'Unit',
            'is_active' => true,
        ]);

        return [$user, $company, $department, $vendor, $serviceType, $unit];
    }

    private function grantGoodsReceiptPermissions(User $user): void
    {
        foreach ([
            'good-receipt.view',
            'good-receipt.create',
            'good-receipt.update',
            'good-receipt.delete',
            'good-receipt.submit',
            'consumable.update',
        ] as $permissionName) {
            $user->givePermissionTo(Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]));
        }
    }

    private function createGoodsReceiptNumbering(): void
    {
        NumberingDocument::query()->create([
            'code' => 'GOODS_RECEIPT',
            'name' => 'Goods Receipt',
            'pattern' => '{COMPANY}/{DOCUMENT_TYPE}/{DEPARTMENT}/{YYYY}/{SEQ}',
            'sequence_length' => 3,
            'reset_period' => 'yearly',
            'is_active' => true,
        ]);
    }

    private function createPurchaseRequest(
        User $requester,
        Company $company,
        Department $department,
        ServiceType $serviceType,
        UnitOfMeasure $unit,
        bool $requiresPo,
        ?Vendor $vendor = null,
        ?Product $product = null,
    ): PurchaseRequest {
        $purchaseRequest = PurchaseRequest::query()->create([
            'number' => 'GR-PR-'.strtoupper(substr((string) $company->code, 0, 8)).'-'.(PurchaseRequest::query()->count() + 1),
            'request_date' => now()->toDateString(),
            'requester_id' => $requester->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'purpose' => 'Goods Receipt workflow test',
            'status' => 'APPROVED',
            'approved_at' => now(),
        ]);
        $purchaseRequest->items()->create([
            'item_type' => $product === null ? 'SERVICE' : 'PRODUCT',
            'product_id' => $product?->id,
            'service_type_id' => $product === null ? $serviceType->id : null,
            'description' => $product === null ? 'Service to receive' : null,
            'quantity' => 5,
            'uom_id' => $unit->id,
            'estimated_unit_price' => 10000,
            'vendor_id' => $vendor?->id,
            'requires_po' => $requiresPo,
            'tax_included' => false,
        ]);

        return $purchaseRequest;
    }

    private function createInventoryProduct(string $code, string $trackingMode = 'QUANTITY'): Product
    {
        $suffix = strtoupper(substr($code, -6));
        $category = Category::query()->create([
            'code' => 'CAT-'.$suffix,
            'name' => 'Inventory '.$suffix,
            'is_active' => true,
        ]);
        $type = Type::query()->create([
            'category_id' => $category->id,
            'code' => 'TYPE-'.$suffix,
            'name' => 'Consumable '.$suffix,
            'is_active' => true,
        ]);
        $brand = Brand::query()->create([
            'code' => 'BR-'.$suffix,
            'name' => 'Inventory Brand '.$suffix,
            'is_active' => true,
        ]);
        $model = ProductModel::query()->create([
            'brand_id' => $brand->id,
            'type_id' => $type->id,
            'code' => 'MODEL-'.$suffix,
            'name' => 'Inventory Model '.$suffix,
            'is_active' => true,
        ]);

        return Product::query()->create([
            'category_id' => $category->id,
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'group' => 'CONSUMABLE',
            'inventory_tracking_mode' => $trackingMode,
            'code' => $code,
            'name' => 'Inventory Product '.$suffix,
            'is_active' => true,
        ]);
    }

    private function createPurchaseOrder(
        PurchaseRequest $purchaseRequest,
        Company $company,
        Department $department,
        Vendor $vendor,
        ServiceType $serviceType,
        UnitOfMeasure $unit,
        float $quantity,
    ): PurchaseOrder {
        $purchaseOrder = PurchaseOrder::query()->create([
            'number' => 'GR-PO-'.strtoupper(substr((string) $company->code, 0, 8)),
            'purchase_request_id' => $purchaseRequest->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'vendor_id' => $vendor->id,
            'status' => 'SUBMITTED',
            'submitted_at' => now(),
        ]);
        $purchaseOrder->items()->create([
            'purchase_request_item_id' => $purchaseRequest->items()->firstOrFail()->id,
            'item_type' => 'SERVICE',
            'service_type_id' => $serviceType->id,
            'description' => 'Service to receive',
            'quantity' => $quantity,
            'uom_id' => $unit->id,
            'pr_estimated_unit_price' => 10000,
            'unit_price' => 10000,
            'tax_included' => false,
        ]);

        return $purchaseOrder;
    }

    private function createDraftReceipt(
        User $actor,
        PurchaseOrder $purchaseOrder,
        float $received,
        float $accepted,
        float $rejected,
        ?string $remarks = null,
    ): GoodsReceipt {
        $this->actingAs($actor)
            ->post(route('transaction.procurement.goods-receipts.store'), [
                'source_type' => GoodsReceipt::SOURCE_PURCHASE_ORDER,
                'source_id' => $purchaseOrder->id,
                'received_at' => now()->toDateString(),
                'delivery_note_number' => null,
                'items' => [[
                    'source_item_id' => $purchaseOrder->items()->firstOrFail()->id,
                    'quantity_received' => $received,
                    'quantity_accepted' => $accepted,
                    'quantity_rejected' => $rejected,
                    'remarks' => $remarks,
                ]],
            ])
            ->assertSessionHasNoErrors();

        return GoodsReceipt::query()->latest('id')->firstOrFail();
    }
}
