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
use App\Models\Transaction\Inventory\InventoryDepartmentWarehouse;
use App\Models\Transaction\Inventory\InventoryStockMovement;
use App\Models\Transaction\Inventory\InventoryStocktake;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryStocktakeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_minimum_stock_threshold_shows_alert_and_can_be_removed(): void
    {
        [$user, $department, $location, $product] = $this->createInventoryContext('minimum');
        $this->grantInventoryPermissions($user);
        $this->addMovement($user, $department, $location, $product, 'IN', 2);

        $this->actingAs($user)->put(route('transaction.inventory.minimum-stock.update', [
            'department' => $department->id,
            'location' => $location->id,
            'product' => $product->id,
        ]), ['minimum_quantity' => 5])->assertSessionHasNoErrors();

        $this->actingAs($user)->get(route('transaction.inventory.index', ['department_id' => $department->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transaction/Inventory/Index')
                ->where('lowStockAlerts.0.balance', 2)
                ->where('lowStockAlerts.0.minimum_quantity', 5));

        $this->actingAs($user)->put(route('transaction.inventory.minimum-stock.update', [
            'department' => $department->id,
            'location' => $location->id,
            'product' => $product->id,
        ]), ['minimum_quantity' => null])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('trx_inventory_stock_thresholds', 0);
    }

    public function test_owner_can_view_inventory_but_cannot_edit_stock(): void
    {
        [$owner, $department, $location, $product] = $this->createInventoryContext('owner-read-only');
        $ownerRole = Role::query()->create([
            'name' => 'Owner',
            'guard_name' => 'web',
        ]);
        $ownerRole->givePermissionTo(Permission::query()->firstOrCreate([
            'name' => 'consumable.view',
            'guard_name' => 'web',
        ]));
        $owner->assignRole($ownerRole);

        $this->actingAs($owner)
            ->get(route('transaction.inventory.index'))
            ->assertOk();

        $this->actingAs($owner)
            ->put(route('transaction.inventory.minimum-stock.update', [
                'department' => $department->id,
                'location' => $location->id,
                'product' => $product->id,
            ]), ['minimum_quantity' => 5])
            ->assertForbidden();

        $this->assertDatabaseCount('trx_inventory_stock_thresholds', 0);
    }

    public function test_stocktake_posts_quantity_difference_as_append_only_adjustment_movement(): void
    {
        [$user, $department, $location, $product] = $this->createInventoryContext('stocktake');
        $this->grantInventoryPermissions($user);
        $this->addMovement($user, $department, $location, $product, 'IN', 5);

        $this->actingAs($user)->post(route('transaction.inventory.stocktakes.store'), [
            'department_id' => $department->id,
            'location_id' => $location->id,
            'remarks' => 'Opname bulanan',
        ])->assertSessionHasNoErrors();

        $stocktake = InventoryStocktake::query()->firstOrFail();
        $item = $stocktake->items()->firstOrFail();

        $this->actingAs($user)->put(route('transaction.inventory.stocktakes.update', $stocktake), [
            'items' => [[
                'id' => $item->id,
                'counted_quantity' => 3,
                'serial_numbers' => [],
                'remarks' => 'Dua unit tidak ditemukan',
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(InventoryStocktake::STATUS_COUNTED, $stocktake->fresh()->status);

        $this->actingAs($user)->post(route('transaction.inventory.stocktakes.post', $stocktake))
            ->assertSessionHasNoErrors();

        $this->assertSame(3.0, $this->balance($product, $location));
        $this->assertSame(2, InventoryStockMovement::query()->count());
        $this->assertDatabaseHas('trx_inventory_document_items', [
            'inventory_stocktake_item_id' => $item->id,
            'quantity' => 2,
        ]);
        $this->assertSame(InventoryStocktake::STATUS_POSTED, $stocktake->fresh()->status);
    }

    public function test_stocktake_refuses_to_post_if_ledger_changed_after_snapshot(): void
    {
        [$user, $department, $location, $product] = $this->createInventoryContext('stale');
        $this->grantInventoryPermissions($user);
        $this->addMovement($user, $department, $location, $product, 'IN', 5);

        $this->actingAs($user)->post(route('transaction.inventory.stocktakes.store'), [
            'department_id' => $department->id,
            'location_id' => $location->id,
        ])->assertSessionHasNoErrors();
        $stocktake = InventoryStocktake::query()->firstOrFail();
        $item = $stocktake->items()->firstOrFail();

        $this->actingAs($user)->put(route('transaction.inventory.stocktakes.update', $stocktake), [
            'items' => [['id' => $item->id, 'counted_quantity' => 4, 'serial_numbers' => [], 'remarks' => null]],
        ])->assertSessionHasNoErrors();
        $this->addMovement($user, $department, $location, $product, 'IN', 1);

        $this->actingAs($user)->post(route('transaction.inventory.stocktakes.post', $stocktake))
            ->assertSessionHasErrors('stocktake');

        $this->assertSame(6.0, $this->balance($product, $location));
        $this->assertSame(InventoryStocktake::STATUS_COUNTED, $stocktake->fresh()->status);
        $this->assertDatabaseCount('trx_inventory_documents', 0);
    }

    public function test_serial_stocktake_records_missing_and_unexpected_serials_as_movements(): void
    {
        [$user, $department, $location, $product] = $this->createInventoryContext('serial');
        $this->grantInventoryPermissions($user);
        $product->update(['inventory_tracking_mode' => 'SERIAL']);
        $this->addMovement($user, $department, $location, $product, 'IN', 1, 'SN-A');
        $this->addMovement($user, $department, $location, $product, 'IN', 1, 'SN-B');

        $this->actingAs($user)->post(route('transaction.inventory.stocktakes.store'), [
            'department_id' => $department->id,
            'location_id' => $location->id,
        ])->assertSessionHasNoErrors();

        $stocktake = InventoryStocktake::query()->firstOrFail();
        $item = $stocktake->items()->firstOrFail();

        $this->actingAs($user)->put(route('transaction.inventory.stocktakes.update', $stocktake), [
            'items' => [[
                'id' => $item->id,
                'counted_quantity' => 2,
                'serial_numbers' => ['SN-A', 'SN-C'],
                'remarks' => 'SN-B tidak ditemukan, SN-C tidak tercatat.',
            ]],
        ])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('transaction.inventory.stocktakes.post', $stocktake))
            ->assertSessionHasNoErrors();

        $serialBalances = InventoryStockMovement::query()
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

        $this->assertSame(['SN-A', 'SN-C'], $serialBalances);
        $this->assertDatabaseCount('trx_inventory_stock_movements', 4);
        $this->assertDatabaseCount('trx_inventory_document_items', 2);
    }

    /** @return array{User, Department, Location, Product} */
    private function createInventoryContext(string $suffix): array
    {
        $user = User::query()->create([
            'name' => 'Stocktake Operator',
            'email' => "stocktake-{$suffix}@example.test",
            'password' => 'password',
            'is_active' => true,
        ]);
        $company = Company::query()->create(['code' => "CO-{$suffix}", 'name' => 'Stocktake Company', 'is_active' => true]);
        $department = Department::query()->create(['company_id' => $company->id, 'code' => "DP-{$suffix}", 'name' => 'Stocktake Department', 'is_active' => true]);
        $user->update(['company_id' => $company->id, 'department_id' => $department->id]);
        $location = Location::query()->create([
            'company_id' => $company->id,
            'code' => "WH-{$suffix}",
            'name' => 'Stocktake Warehouse',
            'location_type' => 'Warehouse',
            'is_active' => true,
        ]);
        InventoryDepartmentWarehouse::query()->create([
            'department_id' => $department->id,
            'location_id' => $location->id,
            'configured_by' => $user->id,
            'is_default' => true,
        ]);
        $category = Category::query()->create(['code' => "CT-{$suffix}", 'name' => 'Stocktake Category', 'is_active' => true]);
        $type = Type::query()->create(['category_id' => $category->id, 'code' => "TY-{$suffix}", 'name' => 'Stocktake Type', 'is_active' => true]);
        $brand = Brand::query()->create(['code' => "BR-{$suffix}", 'name' => 'Stocktake Brand', 'is_active' => true]);
        $model = ProductModel::query()->create(['brand_id' => $brand->id, 'type_id' => $type->id, 'code' => "MD-{$suffix}", 'name' => 'Stocktake Model', 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'group' => 'CONSUMABLE',
            'inventory_tracking_mode' => 'QUANTITY',
            'code' => "ST-{$suffix}",
            'name' => 'Stocktake Consumable',
            'is_active' => true,
        ]);

        return [$user, $department, $location, $product];
    }

    private function grantInventoryPermissions(User $user): void
    {
        foreach (['consumable.view', 'consumable.update'] as $permissionName) {
            $user->givePermissionTo(Permission::query()->firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
    }

    private function addMovement(User $actor, Department $department, Location $location, Product $product, string $direction, float $quantity, ?string $serialNumber = null): void
    {
        InventoryStockMovement::query()->create([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'department_id' => $department->id,
            'movement_type' => 'GOODS_RECEIPT',
            'direction' => $direction,
            'quantity' => $quantity,
            'serial_number' => $serialNumber,
            'idempotency_key' => hash('sha256', (string) Str::ulid()),
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
