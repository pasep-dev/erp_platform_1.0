<?php

namespace Tests\Feature\Transaction\AssetManagement;

use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Master\Procurement\UnitOfMeasure;
use App\Models\Master\Procurement\Vendor;
use App\Models\Master\Procurement\VendorType;
use App\Models\Master\Product\Brand;
use App\Models\Master\Product\Category;
use App\Models\Master\Product\Model as AssetModel;
use App\Models\Master\Product\Product;
use App\Models\Master\Product\Type;
use App\Models\NumberingDocument;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\Procurement\GoodsReceipt;
use App\Models\Transaction\Procurement\GoodsReceiptItem;
use App\Models\Transaction\Procurement\PurchaseOrder;
use App\Models\Transaction\Procurement\PurchaseRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AssetRegistrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_posted_goods_receipt_units_are_registered_once_as_active_assets(): void
    {
        [$user, $company, $department, $product, $location] = $this->createAssetContext('procurement');
        $this->grantRegistrationPermissions($user);
        $this->createAssetNumbering();
        $receiptItem = $this->createPostedReceiptItem($user, $company, $department, $product, 2);

        $this->actingAs($user)
            ->get(route('transaction.asset-management.registrations.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transaction/AssetManagement/Registration/Index')
                ->has('queue', 1)
                ->where('queue.0.goods_receipt_item_id', $receiptItem->id)
                ->where('queue.0.pending_count', 2));

        $payload = [
            'goods_receipt_item_id' => $receiptItem->id,
            'location_id' => $location->id,
            'units' => [
                ['serial_number' => 'LAPTOP-SN-001', 'specifications' => [['name' => 'RAM', 'value' => '16 GB'], ['name' => 'Storage', 'value' => '512 GB']], 'warranty_years' => 2],
                ['serial_number' => 'LAPTOP-SN-002', 'specifications' => [['name' => 'RAM', 'value' => '32 GB']]],
            ],
        ];

        $this->actingAs($user)
            ->post(route('transaction.asset-management.registrations.store'), $payload)
            ->assertSessionHasNoErrors();

        $firstAsset = Asset::query()->firstOrFail();
        $this->assertSame('TST-LTP001BR', $firstAsset->number);
        $this->assertSame(Asset::METHOD_PROCUREMENT, $firstAsset->registration_method);
        $this->assertSame(Asset::STATUS_ACTIVE, $firstAsset->status);
        $this->assertSame(1, $firstAsset->source_unit_number);
        $this->assertNull($firstAsset->acquisition_cost);
        $this->assertSame('LAPTOP-SN-001', $firstAsset->serial_number);
        $this->assertSame(['RAM' => '16 GB', 'Storage' => '512 GB'], $firstAsset->specifications);
        $receiptDate = $receiptItem->goodsReceipt()->firstOrFail()->received_at->toDateString();
        $this->assertSame($receiptDate, $firstAsset->warranty_start_date->toDateString());
        $this->assertSame(24, $firstAsset->warranty_duration_months);
        $this->assertSame(CarbonImmutable::parse($receiptDate)->addMonthsNoOverflow(24)->subDay()->toDateString(), $firstAsset->warranty_end_date->toDateString());
        $this->assertSame($user->id, $firstAsset->registered_by);

        $this->actingAs($user)
            ->post(route('transaction.asset-management.registrations.store'), [
                'goods_receipt_item_id' => $receiptItem->id,
                'location_id' => $location->id,
                'units' => [['serial_number' => null, 'specifications' => []]],
            ])
            ->assertSessionHasErrors('units');

        $this->assertDatabaseCount('assets', 2);
        $this->assertDatabaseHas('assets', [
            'goods_receipt_item_id' => $receiptItem->id,
            'source_unit_number' => 2,
            'number' => 'TST-LTP002BR',
            'serial_number' => 'LAPTOP-SN-002',
        ]);
        $this->assertSame(['RAM' => '32 GB'], Asset::query()->where('serial_number', 'LAPTOP-SN-002')->firstOrFail()->specifications);

        $this->actingAs($user)
            ->get(route('transaction.asset-management.registrations.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('queue', 0));
    }

    public function test_existing_asset_can_be_registered_without_a_goods_receipt_source(): void
    {
        [$user, $company, $department, $product, $location] = $this->createAssetContext('existing');
        $this->grantRegistrationPermissions($user);
        $this->createAssetNumbering();

        $this->actingAs($user)
            ->post(route('transaction.asset-management.registrations.store'), [
                'product_id' => $product->id,
                'company_id' => $company->id,
                'department_id' => $department->id,
                'location_id' => $location->id,
                'acquisition_date' => '2024-05-12',
                'acquisition_cost' => 2500000,
                'units' => [['serial_number' => 'EXISTING-SERIAL-001', 'specifications' => [['name' => 'Kapasitas', 'value' => '2 TB']]]],
                'remarks' => 'Aset ditemukan saat stock opname.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assets', [
            'number' => 'TST-LTP001BR',
            'registration_method' => Asset::METHOD_EXISTING,
            'goods_receipt_item_id' => null,
            'serial_number' => 'EXISTING-SERIAL-001',
            'acquisition_date' => '2024-05-12',
            'acquisition_cost' => 2500000,
        ]);
        $this->assertSame(['Kapasitas' => '2 TB'], Asset::query()->where('serial_number', 'EXISTING-SERIAL-001')->firstOrFail()->specifications);
    }

    public function test_purchase_order_price_is_recorded_when_registering_its_received_asset(): void
    {
        [$user, $company, $department, $product, $location] = $this->createAssetContext('po-price');
        $this->grantRegistrationPermissions($user);
        $this->createAssetNumbering();
        $vendorType = VendorType::query()->create([
            'code' => 'SUPPLIER',
            'name' => 'Supplier',
            'is_active' => true,
        ]);
        $vendor = Vendor::query()->create([
            'vendor_type_id' => $vendorType->id,
            'code' => 'SUP-001',
            'name' => 'Asset Vendor',
            'is_active' => true,
        ]);
        $unit = UnitOfMeasure::query()->create([
            'code' => 'UNIT-PO',
            'name' => 'Unit',
            'is_active' => true,
        ]);
        $purchaseRequest = PurchaseRequest::query()->create([
            'number' => 'PR-PO-ASSET',
            'request_date' => now()->toDateString(),
            'requester_id' => $user->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'purpose' => 'Asset registration PO price test',
            'status' => 'APPROVED',
            'approved_at' => now(),
        ]);
        $purchaseRequestItem = $purchaseRequest->items()->create([
            'item_type' => 'PRODUCT',
            'product_id' => $product->id,
            'quantity' => 1,
            'uom_id' => $unit->id,
            'estimated_unit_price' => 19000,
            'vendor_id' => $vendor->id,
            'requires_po' => true,
        ]);
        $purchaseOrder = PurchaseOrder::query()->create([
            'number' => 'PO-ASSET-001',
            'purchase_request_id' => $purchaseRequest->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'vendor_id' => $vendor->id,
            'status' => 'SUBMITTED',
            'submitted_at' => now(),
        ]);
        $purchaseOrderItem = $purchaseOrder->items()->create([
            'purchase_request_item_id' => $purchaseRequestItem->id,
            'item_type' => 'PRODUCT',
            'product_id' => $product->id,
            'quantity' => 1,
            'uom_id' => $unit->id,
            'pr_estimated_unit_price' => 19000,
            'unit_price' => 21000,
            'tax_included' => false,
        ]);
        $receipt = GoodsReceipt::query()->create([
            'number' => 'GR-PO-ASSET-001',
            'source_type' => GoodsReceipt::SOURCE_PURCHASE_ORDER,
            'purchase_order_id' => $purchaseOrder->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'vendor_id' => $vendor->id,
            'received_at' => now()->toDateString(),
            'status' => GoodsReceipt::STATUS_POSTED,
            'created_by' => $user->id,
            'posted_at' => now(),
            'posted_by' => $user->id,
        ]);
        $receiptItem = $receipt->items()->create([
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'quantity_received' => 1,
            'quantity_accepted' => 1,
            'quantity_rejected' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('transaction.asset-management.registrations.store'), [
                'goods_receipt_item_id' => $receiptItem->id,
                'location_id' => $location->id,
                'units' => [['serial_number' => 'PO-ASSET-SN-001', 'specifications' => []]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assets', [
            'goods_receipt_item_id' => $receiptItem->id,
            'acquisition_cost' => 21000,
        ]);
    }

    public function test_registration_queue_excludes_non_asset_products(): void
    {
        [$user, $company, $department, $assetProduct] = $this->createAssetContext('queue');
        $this->grantRegistrationPermissions($user);
        $consumableProduct = $this->createProduct('CONSUMABLE', 'PPR', 'Paper');
        $this->createPostedReceiptItem($user, $company, $department, $assetProduct, 1);
        $this->createPostedReceiptItem($user, $company, $department, $consumableProduct, 8);

        $this->actingAs($user)
            ->get(route('transaction.asset-management.registrations.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('queue', fn ($queue) => count($queue) === 1 && $queue[0]['product_code'] === $assetProduct->code));
    }

    public function test_registration_asset_pagination_uses_selected_page_size_and_page_number(): void
    {
        [$user, $company, $department, $product, $location] = $this->createAssetContext('pagination');
        $this->grantRegistrationPermissions($user);

        for ($index = 1; $index <= 16; $index++) {
            Asset::query()->create([
                'number' => "PAGINATION-ASSET-{$index}",
                'registration_method' => Asset::METHOD_EXISTING,
                'status' => Asset::STATUS_ACTIVE,
                'product_id' => $product->id,
                'category_id' => $product->category_id,
                'type_id' => $product->type_id,
                'brand_id' => $product->brand_id,
                'model_id' => $product->model_id,
                'company_id' => $company->id,
                'department_id' => $department->id,
                'location_id' => $location->id,
                'branch_id' => $location->branch_id,
                'acquisition_date' => now()->toDateString(),
                'registered_by' => $user->id,
            ]);
        }

        $this->actingAs($user)
            ->get(route('transaction.asset-management.registrations.index', ['per_page' => 5]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('assets.total', 16)
                ->where('assets.per_page', 5)
                ->where('assets.current_page', 1)
                ->has('assets.data', 5));

        $this->actingAs($user)
            ->get(route('transaction.asset-management.registrations.index', ['per_page' => 5, 'page' => 2]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('assets.current_page', 2)
                ->has('assets.data', 5));
    }

    public function test_specification_requires_both_a_name_and_a_value(): void
    {
        [$user, $company, $department, $product, $location] = $this->createAssetContext('partial-specification');
        $this->grantRegistrationPermissions($user);
        $this->createAssetNumbering();

        $this->actingAs($user)
            ->from(route('transaction.asset-management.registrations.create'))
            ->post(route('transaction.asset-management.registrations.store'), [
                'product_id' => $product->id,
                'company_id' => $company->id,
                'department_id' => $department->id,
                'location_id' => $location->id,
                'acquisition_date' => '2024-05-12',
                'units' => [['serial_number' => null, 'specifications' => [['name' => 'RAM', 'value' => '']]]],
            ])
            ->assertSessionHasErrors('units.0.specifications.0.value');

        $this->assertDatabaseCount('assets', 0);
    }

    public function test_user_without_registration_permission_cannot_create_an_asset(): void
    {
        [$user] = $this->createAssetContext('unauthorized');

        $this->actingAs($user)
            ->post(route('transaction.asset-management.registrations.store'), [])
            ->assertForbidden();

        $this->assertDatabaseCount('assets', 0);
    }

    public function test_existing_asset_cannot_be_assigned_to_a_location_from_another_company(): void
    {
        [$user, $company, $department, $product] = $this->createAssetContext('location-scope');
        $this->grantRegistrationPermissions($user);
        $otherCompany = Company::query()->create([
            'code' => 'OTHER',
            'name' => 'Other Company',
            'is_active' => true,
        ]);
        $otherBranch = Branch::query()->create([
            'company_id' => $otherCompany->id,
            'code' => 'OTH',
            'name' => 'Other Branch',
            'is_active' => true,
        ]);
        $otherLocation = Location::query()->create([
            'company_id' => $otherCompany->id,
            'branch_id' => $otherBranch->id,
            'code' => 'OTHER-ROOM',
            'name' => 'Other Room',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->from(route('transaction.asset-management.registrations.create'))
            ->post(route('transaction.asset-management.registrations.store'), [
                'product_id' => $product->id,
                'company_id' => $company->id,
                'department_id' => $department->id,
                'location_id' => $otherLocation->id,
                'acquisition_date' => '2024-05-12',
            ])
            ->assertSessionHasErrors('location_id');

        $this->assertDatabaseCount('assets', 0);
    }

    public function test_goods_receipt_draft_cannot_create_an_asset(): void
    {
        [$user, $company, $department, $product, $location] = $this->createAssetContext('draft-source');
        $this->grantRegistrationPermissions($user);
        $this->createAssetNumbering();
        $receiptItem = $this->createPostedReceiptItem($user, $company, $department, $product, 1, GoodsReceipt::STATUS_DRAFT);

        $this->actingAs($user)
            ->from(route('transaction.asset-management.registrations.create'))
            ->post(route('transaction.asset-management.registrations.store'), [
                'goods_receipt_item_id' => $receiptItem->id,
                'location_id' => $location->id,
                'units' => [['serial_number' => null, 'specifications' => []]],
            ])
            ->assertSessionHasErrors('goods_receipt_item_id');

        $this->assertDatabaseCount('assets', 0);
    }

    public function test_procurement_registration_requires_a_location_and_keeps_the_asset_unregistered(): void
    {
        [$user, $company, $department, $product] = $this->createAssetContext('missing-location');
        $this->grantRegistrationPermissions($user);
        $receiptItem = $this->createPostedReceiptItem($user, $company, $department, $product, 1);

        $this->actingAs($user)
            ->from(route('transaction.asset-management.registrations.create', ['goods_receipt_item_id' => $receiptItem->id]))
            ->post(route('transaction.asset-management.registrations.store'), [
                'goods_receipt_item_id' => $receiptItem->id,
                'units' => [['serial_number' => 'NO-LOCATION-SERIAL', 'specifications' => [], 'warranty_years' => null]],
            ])
            ->assertSessionHasErrors('location_id');

        $this->assertDatabaseCount('assets', 0);
    }

    public function test_goods_receipt_rejects_fractional_accepted_quantity_for_asset_products(): void
    {
        [$user, $company, $department, $product] = $this->createAssetContext('fractional-gr');
        $user->givePermissionTo(Permission::query()->firstOrCreate([
            'name' => 'good-receipt.create',
            'guard_name' => 'web',
        ]));
        $unit = UnitOfMeasure::query()->create([
            'code' => 'UNIT-FRACTIONAL',
            'name' => 'Unit',
            'is_active' => true,
        ]);
        $purchaseRequest = PurchaseRequest::query()->create([
            'number' => 'PR-FRACTIONAL-ASSET',
            'request_date' => now()->toDateString(),
            'requester_id' => $user->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'purpose' => 'Fractional asset receipt test',
            'status' => 'APPROVED',
            'approved_at' => now(),
        ]);
        $requestItem = $purchaseRequest->items()->create([
            'item_type' => 'PRODUCT',
            'product_id' => $product->id,
            'quantity' => 2,
            'uom_id' => $unit->id,
            'estimated_unit_price' => 15000,
            'requires_po' => false,
        ]);

        $this->actingAs($user)
            ->from(route('transaction.procurement.goods-receipts.create', [
                'source_type' => GoodsReceipt::SOURCE_PURCHASE_REQUEST,
                'source_id' => $purchaseRequest->id,
            ]))
            ->post(route('transaction.procurement.goods-receipts.store'), [
                'source_type' => GoodsReceipt::SOURCE_PURCHASE_REQUEST,
                'source_id' => $purchaseRequest->id,
                'received_at' => now()->toDateString(),
                'items' => [[
                    'source_item_id' => $requestItem->id,
                    'quantity_received' => 1.5,
                    'quantity_accepted' => 1.5,
                    'quantity_rejected' => 0,
                ]],
            ])
            ->assertSessionHasErrors('items.0.quantity_accepted');

        $this->assertDatabaseCount('goods_receipts', 0);
    }

    /** @return array{User, Company, Department, Product, Location} */
    private function createAssetContext(string $prefix): array
    {
        $user = User::query()->create([
            'name' => 'Asset Registration User',
            'email' => "{$prefix}@example.test",
            'password' => 'password',
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'code' => 'TST',
            'name' => 'Test Company',
            'is_active' => true,
        ]);
        $department = Department::query()->create([
            'company_id' => $company->id,
            'code' => "{$prefix}-DEP",
            'name' => 'Test Department',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'BR',
            'name' => 'Main Branch',
            'is_active' => true,
        ]);
        $location = Location::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'code' => 'ROOM-01',
            'name' => 'Server Room',
            'is_active' => true,
        ]);
        $product = $this->createProduct('ASSET', 'LTP', 'Laptop');

        return [$user, $company, $department, $product, $location];
    }

    private function createProduct(string $group, string $code, string $name): Product
    {
        $category = Category::query()->firstOrCreate([
            'code' => 'IT',
        ], [
            'name' => 'Information Technology',
            'is_active' => true,
        ]);
        $type = Type::query()->firstOrCreate([
            'category_id' => $category->id,
            'code' => 'LTP',
        ], [
            'name' => 'Laptop',
            'is_active' => true,
        ]);
        $brand = Brand::query()->firstOrCreate([
            'code' => 'TST-BRAND',
        ], [
            'name' => 'Test Brand',
            'is_active' => true,
        ]);
        $model = AssetModel::query()->firstOrCreate([
            'code' => 'TST-MODEL',
        ], [
            'brand_id' => $brand->id,
            'type_id' => $type->id,
            'name' => 'Test Model',
            'is_active' => true,
        ]);

        return Product::query()->create([
            'category_id' => $category->id,
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'group' => $group,
            'code' => $code,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function createPostedReceiptItem(
        User $user,
        Company $company,
        Department $department,
        Product $product,
        int $quantity,
        string $receiptStatus = GoodsReceipt::STATUS_POSTED,
    ): GoodsReceiptItem {
        $purchaseRequest = PurchaseRequest::query()->create([
            'number' => 'PR-'.$product->code.'-'.$quantity.'-'.PurchaseRequest::query()->count(),
            'request_date' => now()->toDateString(),
            'requester_id' => $user->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'purpose' => 'Asset registration workflow test',
            'status' => 'APPROVED',
            'approved_at' => now(),
        ]);
        $unit = UnitOfMeasure::query()->create([
            'code' => 'UNIT-'.$purchaseRequest->id,
            'name' => 'Unit',
            'is_active' => true,
        ]);
        $requestItem = $purchaseRequest->items()->create([
            'item_type' => 'PRODUCT',
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => $quantity,
            'uom_id' => $unit->id,
            'estimated_unit_price' => 15000,
            'requires_po' => false,
        ]);
        $receipt = GoodsReceipt::query()->create([
            'number' => 'GR-'.$product->code.'-'.$quantity.'-'.GoodsReceipt::query()->count(),
            'source_type' => GoodsReceipt::SOURCE_PURCHASE_REQUEST,
            'purchase_request_id' => $purchaseRequest->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'received_at' => now()->toDateString(),
            'status' => $receiptStatus,
            'created_by' => $user->id,
            'posted_at' => $receiptStatus === GoodsReceipt::STATUS_POSTED ? now() : null,
            'posted_by' => $receiptStatus === GoodsReceipt::STATUS_POSTED ? $user->id : null,
        ]);

        return $receipt->items()->create([
            'purchase_request_item_id' => $requestItem->id,
            'quantity_received' => $quantity,
            'quantity_accepted' => $quantity,
            'quantity_rejected' => 0,
        ]);
    }

    private function grantRegistrationPermissions(User $user): void
    {
        foreach (['registration.view', 'registration.create'] as $permissionName) {
            $user->givePermissionTo(Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]));
        }
    }

    private function createAssetNumbering(): void
    {
        NumberingDocument::query()->create([
            'code' => 'ASSET',
            'name' => 'Asset Number',
            'pattern' => '{COMPANY}-{ASSET_TYPE}{SEQ}{BRANCH}',
            'sequence_length' => 3,
            'reset_period' => 'never',
            'is_active' => true,
        ]);
    }
}
