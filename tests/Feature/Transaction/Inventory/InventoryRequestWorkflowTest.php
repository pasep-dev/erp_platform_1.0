<?php

namespace Tests\Feature\Transaction\Inventory;

use App\Models\Administrator\Workflow\ApprovalWorkflow;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Master\Product\Brand;
use App\Models\Master\Product\Category;
use App\Models\Master\Product\Model as ProductModel;
use App\Models\Master\Product\Product;
use App\Models\Master\Product\Type;
use App\Models\Transaction\Inventory\InventoryDepartmentWarehouse;
use App\Models\Transaction\Inventory\InventoryDocumentItem;
use App\Models\Transaction\Inventory\InventoryRequest;
use App\Models\Transaction\Inventory\InventoryRequestItem;
use App\Models\Transaction\Inventory\InventoryStockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InventoryRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_request_can_be_fulfilled_partially_until_all_requested_quantity_is_issued(): void
    {
        [$requester, $approver, $company, $department, $warehouse, $product] = $this->createContext('partial');
        $this->grant($requester, 'consumable.request');
        $this->grant($requester, 'consumable.view');
        $this->grant($approver, 'consumable.issue');
        $this->grant($approver, 'consumable.view');
        $this->createWorkflow($company, $department, $approver);
        $this->addStock($approver, $department, $warehouse, $product, 5);

        $this->actingAs($requester)->post(route('transaction.inventory.requests.store'), [
            'department_id' => $department->id,
            'requested_at' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'requested_quantity' => 4]],
        ])->assertRedirect();

        $inventoryRequest = InventoryRequest::query()->firstOrFail();
        $this->actingAs($requester)->post(route('transaction.inventory.requests.submit', $inventoryRequest))->assertSessionHasNoErrors();
        $this->assertSame(InventoryRequest::STATUS_PENDING_APPROVAL, $inventoryRequest->fresh()->status);

        $approvalRequest = $inventoryRequest->fresh()->approvalRequest;
        $this->actingAs($approver)->post(route('approval.inbox.approve', $approvalRequest), ['remarks' => 'Disetujui.'])->assertSessionHasNoErrors();
        $this->assertSame(InventoryRequest::STATUS_APPROVED, $inventoryRequest->fresh()->status);

        $requestItem = $inventoryRequest->items()->firstOrFail();
        $this->postFulfillment($approver, $inventoryRequest, $requestItem, $product, $warehouse, 2);

        $this->assertSame(InventoryRequest::STATUS_PARTIALLY_FULFILLED, $inventoryRequest->fresh()->status);
        $this->assertSame('2.000', $requestItem->fresh()->fulfilled_quantity);
        $this->assertSame(3.0, $this->balance($product, $warehouse));
        $this->assertSame(1, InventoryDocumentItem::query()->where('inventory_request_item_id', $requestItem->id)->count());

        $this->postFulfillment($approver, $inventoryRequest, $requestItem, $product, $warehouse, 2);

        $this->assertSame(InventoryRequest::STATUS_FULFILLED, $inventoryRequest->fresh()->status);
        $this->assertSame('4.000', $requestItem->fresh()->fulfilled_quantity);
        $this->assertSame(1.0, $this->balance($product, $warehouse));
        $this->assertDatabaseCount('inventory_documents', 2);
        $this->assertDatabaseCount('approval_actions', 1);
    }

    public function test_rejected_request_is_not_available_for_fulfillment(): void
    {
        [$requester, $approver, $company, $department, $warehouse, $product] = $this->createContext('reject');
        $this->grant($requester, 'consumable.request');
        $this->grant($requester, 'consumable.view');
        $this->grant($approver, 'consumable.issue');
        $this->createWorkflow($company, $department, $approver);
        $this->addStock($approver, $department, $warehouse, $product, 1);

        $this->actingAs($requester)->post(route('transaction.inventory.requests.store'), [
            'department_id' => $department->id,
            'requested_at' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'requested_quantity' => 1]],
        ])->assertRedirect();

        $inventoryRequest = InventoryRequest::query()->firstOrFail();
        $this->actingAs($requester)->post(route('transaction.inventory.requests.submit', $inventoryRequest))->assertSessionHasNoErrors();
        $approvalRequest = $inventoryRequest->fresh()->approvalRequest;

        $this->actingAs($approver)->post(route('approval.inbox.reject', $approvalRequest), ['remarks' => 'Tidak ada kebutuhan yang disetujui.'])->assertSessionHasNoErrors();

        $this->assertSame(InventoryRequest::STATUS_REJECTED, $inventoryRequest->fresh()->status);
        $this->assertDatabaseCount('inventory_documents', 0);
        $this->assertDatabaseHas('approval_actions', [
            'approval_request_id' => $approvalRequest->id,
            'action' => 'REJECTED',
        ]);

        $this->actingAs($approver)->post(route('transaction.inventory.requests.fulfill', $inventoryRequest), [
            'source_location_id' => $warehouse->id,
            'occurred_at' => now()->toDateString(),
            'items' => [[
                'inventory_request_item_id' => $inventoryRequest->items()->firstOrFail()->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'party_type' => 'USER',
                'party_user_id' => $requester->id,
            ]],
        ])->assertSessionHasErrors('inventory_request');

        $this->assertDatabaseCount('inventory_documents', 0);
    }

    public function test_user_cannot_view_another_departments_inventory_request(): void
    {
        [$requester, , , , , $product] = $this->createContext('scope');
        $this->grant($requester, 'consumable.request');
        $this->grant($requester, 'consumable.view');
        $inventoryRequest = InventoryRequest::query()->create([
            'number' => 'IR-TEST-SCOPE',
            'department_id' => $requester->department_id,
            'requested_by' => $requester->id,
            'status' => InventoryRequest::STATUS_DRAFT,
            'requested_at' => now()->toDateString(),
        ]);
        $inventoryRequest->items()->create(['product_id' => $product->id, 'requested_quantity' => 1]);
        $otherDepartment = Department::query()->create([
            'company_id' => $requester->company_id,
            'code' => 'OTHER-SCOPE',
            'name' => 'Other Department',
            'is_active' => true,
        ]);
        $otherUser = User::query()->create([
            'name' => 'Other Department User',
            'email' => 'other-scope@example.test',
            'password' => 'password',
            'is_active' => true,
            'company_id' => $requester->company_id,
            'department_id' => $otherDepartment->id,
        ]);
        $this->grant($otherUser, 'consumable.view');

        $this->actingAs($otherUser)->get(route('transaction.inventory.requests.show', $inventoryRequest))->assertNotFound();
    }

    /** @return array{User, User, Company, Department, Location, Product} */
    private function createContext(string $suffix): array
    {
        $company = Company::query()->create(['code' => 'IR-'.$suffix, 'name' => 'Inventory Request Company', 'is_active' => true]);
        $department = Department::query()->create(['company_id' => $company->id, 'code' => 'IRD-'.$suffix, 'name' => 'Inventory Request Department', 'is_active' => true]);
        $requester = User::query()->create([
            'name' => 'Inventory Requester',
            'email' => "requester-{$suffix}@example.test",
            'password' => 'password',
            'is_active' => true,
            'company_id' => $company->id,
            'department_id' => $department->id,
        ]);
        $approver = User::query()->create([
            'name' => 'Inventory Approver',
            'email' => "approver-{$suffix}@example.test",
            'password' => 'password',
            'is_active' => true,
            'company_id' => $company->id,
            'department_id' => $department->id,
        ]);
        $warehouse = Location::query()->create([
            'company_id' => $company->id,
            'code' => 'IR-WH-'.$suffix,
            'name' => 'Inventory Request Warehouse',
            'location_type' => 'Warehouse',
            'is_active' => true,
        ]);
        InventoryDepartmentWarehouse::query()->create([
            'department_id' => $department->id,
            'location_id' => $warehouse->id,
            'configured_by' => $approver->id,
            'is_default' => true,
        ]);

        $category = Category::query()->create(['code' => 'IR-CAT-'.$suffix, 'name' => 'Consumable Category', 'is_active' => true]);
        $type = Type::query()->create(['category_id' => $category->id, 'code' => 'IR-TYPE-'.$suffix, 'name' => 'Consumable Type', 'is_active' => true]);
        $brand = Brand::query()->create(['code' => 'IR-BRAND-'.$suffix, 'name' => 'Consumable Brand', 'is_active' => true]);
        $model = ProductModel::query()->create(['brand_id' => $brand->id, 'type_id' => $type->id, 'code' => 'IR-MODEL-'.$suffix, 'name' => 'Consumable Model', 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'group' => 'CONSUMABLE',
            'inventory_tracking_mode' => 'QUANTITY',
            'code' => 'IR-PRODUCT-'.$suffix,
            'name' => 'Inventory Request Product',
            'is_active' => true,
        ]);

        return [$requester, $approver, $company, $department, $warehouse, $product];
    }

    private function createWorkflow(Company $company, Department $department, User $approver): void
    {
        $workflow = ApprovalWorkflow::query()->create([
            'module' => 'INVENTORY_REQUEST',
            'company_id' => $company->id,
            'department_id' => $department->id,
            'name' => 'Inventory Request Approval',
            'is_active' => true,
        ]);
        $workflow->levels()->create(['level' => 1, 'approver_user_id' => $approver->id]);
    }

    private function postFulfillment(User $approver, InventoryRequest $request, InventoryRequestItem $item, Product $product, Location $warehouse, int $quantity): void
    {
        $this->actingAs($approver)->post(route('transaction.inventory.requests.fulfill', $request), [
            'source_location_id' => $warehouse->id,
            'occurred_at' => now()->toDateString(),
            'items' => [[
                'inventory_request_item_id' => $item->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'party_type' => 'USER',
                'party_user_id' => $approver->id,
            ]],
        ])->assertSessionHasNoErrors();
    }

    private function grant(User $user, string $permissionName): void
    {
        $user->givePermissionTo(Permission::query()->firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ]));
    }

    private function addStock(User $actor, Department $department, Location $warehouse, Product $product, float $quantity): void
    {
        InventoryStockMovement::query()->create([
            'product_id' => $product->id,
            'location_id' => $warehouse->id,
            'department_id' => $department->id,
            'movement_type' => 'GOODS_RECEIPT',
            'direction' => 'IN',
            'quantity' => $quantity,
            'idempotency_key' => hash('sha256', (string) Str::ulid()),
            'source_type' => 'TEST',
            'source_id' => (string) Str::ulid(),
            'reference_number' => 'TEST-OPENING',
            'occurred_at' => now(),
            'performed_by' => $actor->id,
        ]);
    }

    private function balance(Product $product, Location $warehouse): float
    {
        return (float) InventoryStockMovement::query()
            ->where('product_id', $product->id)
            ->where('location_id', $warehouse->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END), 0) AS balance")
            ->value('balance');
    }
}
