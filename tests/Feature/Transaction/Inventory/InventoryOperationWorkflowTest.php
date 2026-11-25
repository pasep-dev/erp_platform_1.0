<?php

namespace Tests\Feature\Transaction\Inventory;

use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Master\Product\Brand;
use App\Models\Master\Product\Category;
use App\Models\Master\Product\Model as ProductModel;
use App\Models\Master\Product\Product;
use App\Models\Master\Product\Type;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetAssignmentHistory;
use App\Models\Transaction\Inventory\InventoryDepartmentWarehouse;
use App\Models\Transaction\Inventory\InventoryStockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InventoryOperationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_supports_multiple_destinations_and_records_asset_upgrade_in_the_same_transaction(): void
    {
        [$actor, $company, $department, $warehouse, $product, $category, $type, $brand, $model] = $this->createInventoryContext('issue');
        $this->grantIssuePermission($actor);
        $this->addMovement($actor, $department, $warehouse, $product, 'IN', 5);
        $recipient = User::query()->create([
            'name' => 'Inventory Recipient',
            'email' => 'inventory-recipient@example.test',
            'password' => 'password',
            'is_active' => true,
            'company_id' => $company->id,
            'department_id' => $department->id,
        ]);
        $assetProduct = Product::query()->create([
            'category_id' => $category->id,
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'group' => 'ASSET',
            'code' => 'ASSET-ISSUE-PRODUCT',
            'name' => 'Laptop',
            'is_active' => true,
        ]);
        $asset = Asset::query()->create([
            'number' => 'ASSET-INV-ISSUE-01',
            'registration_method' => Asset::METHOD_EXISTING,
            'status' => Asset::STATUS_ACTIVE,
            'condition' => 'GOOD',
            'product_id' => $assetProduct->id,
            'category_id' => $category->id,
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'location_id' => $warehouse->id,
            'acquisition_date' => now()->toDateString(),
            'specifications' => ['RAM' => '8GB'],
            'registered_by' => $actor->id,
        ]);

        $this->actingAs($actor)->post(route('transaction.inventory.issues.store'), [
            'department_id' => $department->id,
            'source_location_id' => $warehouse->id,
            'occurred_at' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'party_type' => 'USER',
                    'party_user_id' => $recipient->id,
                ],
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'party_type' => 'ASSET',
                    'party_asset_id' => $asset->id,
                    'specification_changes' => ['RAM' => '16GB'],
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('trx_inventory_document_items', 2);
        $this->assertDatabaseCount('trx_inventory_stock_movements', 3);
        $this->assertSame(2.0, $this->balance($product, $warehouse));
        $this->assertSame(['RAM' => '16GB'], $asset->fresh()->specifications);
        $this->assertDatabaseHas('trx_asset_assignment_histories', [
            'asset_id' => $asset->id,
            'event_type' => 'UPGRADE',
        ]);
        $this->assertSame(1, AssetAssignmentHistory::query()->where('asset_id', $asset->id)->count());
    }

    public function test_issue_rejects_insufficient_stock_without_writing_document_or_movement(): void
    {
        [$actor, , $department, $warehouse, $product] = $this->createInventoryContext('negative');
        $this->grantIssuePermission($actor);
        $this->addMovement($actor, $department, $warehouse, $product, 'IN', 1);

        $this->actingAs($actor)
            ->from(route('transaction.inventory.issues.create'))
            ->post(route('transaction.inventory.issues.store'), [
                'department_id' => $department->id,
                'source_location_id' => $warehouse->id,
                'occurred_at' => now()->toDateString(),
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'party_type' => 'LOCATION',
                    'party_location_id' => $warehouse->id,
                ]],
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('trx_inventory_documents', 0);
        $this->assertDatabaseCount('trx_inventory_stock_movements', 1);
        $this->assertSame(1.0, $this->balance($product, $warehouse));
    }

    public function test_transfer_posts_paired_movements_and_return_and_adjustment_append_new_movements(): void
    {
        [$actor, $company, $department, $source, $product] = $this->createInventoryContext('transfer');
        $target = Location::query()->create([
            'company_id' => $company->id,
            'code' => 'WH-TRANSFER-TO',
            'name' => 'Transfer Target',
            'location_type' => 'Warehouse',
            'is_active' => true,
        ]);
        InventoryDepartmentWarehouse::query()->create([
            'department_id' => $department->id,
            'location_id' => $target->id,
            'configured_by' => $actor->id,
            'is_default' => false,
        ]);
        $this->grantIssuePermission($actor);
        $this->addMovement($actor, $department, $source, $product, 'IN', 5);

        $this->actingAs($actor)->post(route('transaction.inventory.transfers.store'), [
            'department_id' => $department->id,
            'source_location_id' => $source->id,
            'target_location_id' => $target->id,
            'occurred_at' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('trx_inventory_stock_movements', 3);
        $this->assertSame(3.0, $this->balance($product, $source));
        $this->assertSame(2.0, $this->balance($product, $target));

        $this->actingAs($actor)->post(route('transaction.inventory.returns.store'), [
            'department_id' => $department->id,
            'target_location_id' => $source->id,
            'occurred_at' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'party_type' => 'USER',
                'party_user_id' => $actor->id,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(4.0, $this->balance($product, $source));

        $this->actingAs($actor)->post(route('transaction.inventory.adjustments.store'), [
            'department_id' => $department->id,
            'location_id' => $source->id,
            'direction' => 'OUT',
            'occurred_at' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'reason' => 'Barang rusak saat stock opname.',
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(3.0, $this->balance($product, $source));
        $this->assertDatabaseCount('trx_inventory_documents', 3);
        $this->assertDatabaseCount('trx_inventory_stock_movements', 5);
    }

    /** @return array{User, Company, Department, Location, Product, Category, Type, Brand, ProductModel} */
    private function createInventoryContext(string $suffix): array
    {
        $user = User::query()->create([
            'name' => 'Inventory Operator',
            'email' => "inventory-{$suffix}@example.test",
            'password' => 'password',
            'is_active' => true,
        ]);
        $company = Company::query()->create(['code' => "C-{$suffix}", 'name' => 'Inventory Company', 'is_active' => true]);
        $department = Department::query()->create(['company_id' => $company->id, 'code' => "D-{$suffix}", 'name' => 'Inventory Department', 'is_active' => true]);
        $user->update(['company_id' => $company->id, 'department_id' => $department->id]);
        $warehouse = Location::query()->create([
            'company_id' => $company->id,
            'code' => "WH-{$suffix}",
            'name' => 'Inventory Warehouse',
            'location_type' => 'Warehouse',
            'is_active' => true,
        ]);
        InventoryDepartmentWarehouse::query()->create([
            'department_id' => $department->id,
            'location_id' => $warehouse->id,
            'configured_by' => $user->id,
            'is_default' => true,
        ]);
        $category = Category::query()->create(['code' => "CAT-{$suffix}", 'name' => 'Inventory Category', 'is_active' => true]);
        $type = Type::query()->create(['category_id' => $category->id, 'code' => "TYPE-{$suffix}", 'name' => 'Inventory Type', 'is_active' => true]);
        $brand = Brand::query()->create(['code' => "BR-{$suffix}", 'name' => 'Inventory Brand', 'is_active' => true]);
        $model = ProductModel::query()->create(['brand_id' => $brand->id, 'type_id' => $type->id, 'code' => "MODEL-{$suffix}", 'name' => 'Inventory Model', 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'group' => 'CONSUMABLE',
            'inventory_tracking_mode' => 'QUANTITY',
            'code' => "INV-{$suffix}",
            'name' => 'Inventory Consumable',
            'is_active' => true,
        ]);

        return [$user, $company, $department, $warehouse, $product, $category, $type, $brand, $model];
    }

    private function grantIssuePermission(User $user): void
    {
        $user->givePermissionTo(Permission::query()->firstOrCreate([
            'name' => 'consumable.issue',
            'guard_name' => 'web',
        ]));
    }

    private function addMovement(User $actor, Department $department, Location $location, Product $product, string $direction, float $quantity): void
    {
        $idempotencyKey = (string) Str::ulid();
        InventoryStockMovement::query()->create([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'department_id' => $department->id,
            'movement_type' => 'GOODS_RECEIPT',
            'direction' => $direction,
            'quantity' => $quantity,
            'idempotency_key' => hash('sha256', $idempotencyKey),
            'source_type' => 'TEST',
            'source_id' => (string) Str::ulid(),
            'reference_number' => 'TEST-OPENING',
            'occurred_at' => now(),
            'performed_by' => $actor->id,
        ]);
    }

    private function balance(Product $product, Location $location): float
    {
        return (float) InventoryStockMovement::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END), 0) AS balance")
            ->value('balance');
    }
}
