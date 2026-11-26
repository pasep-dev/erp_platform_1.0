<?php

namespace Tests\Feature\Transaction\AssetManagement;

use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Master\Product\Brand;
use App\Models\Master\Product\Category;
use App\Models\Master\Product\Model as AssetModel;
use App\Models\Master\Product\Product;
use App\Models\Master\Product\Type;
use App\Models\NumberingDocument;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetAssignmentHistory;
use App\Models\Transaction\AssetManagement\AssetTransferHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AssetTransferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_updates_organization_and_keeps_custodian_and_condition_unchanged(): void
    {
        [$actor, $asset, $target] = $this->createTransferSetup('move');
        $custodian = User::query()->create([
            'name' => 'Asset Custodian',
            'email' => 'custodian-move@example.test',
            'password' => 'password',
            'company_id' => $target['company']->id,
            'is_active' => true,
        ]);
        $asset->update(['custodian_id' => $custodian->id, 'condition' => 'FAIR']);
        $this->grantAssetPermissions($actor, 'asset.transfer', 'asset.view');

        $this->actingAs($actor)
            ->post(route('transaction.asset-management.transfers.store'), [
                'asset_id' => $asset->id,
                'company_id' => $target['company']->id,
                'department_id' => $target['department']->id,
                'location_id' => $target['location']->id,
                'effective_at' => now()->toDateString(),
                'reason' => 'Relokasi aset ke kantor cabang baru.',
            ])
            ->assertSessionHasNoErrors();

        $transfer = AssetTransferHistory::query()->firstOrFail();
        $asset->refresh();

        $this->assertSame($target['company']->id, $asset->company_id);
        $this->assertSame($target['department']->id, $asset->department_id);
        $this->assertSame($target['location']->id, $asset->location_id);
        $this->assertSame($target['branch']->id, $asset->branch_id);
        $this->assertSame($custodian->id, $asset->custodian_id);
        $this->assertSame('FAIR', $asset->condition);
        $this->assertSame('Relokasi aset ke kantor cabang baru.', $transfer->reason);
        $this->assertSame($asset->getKey(), $transfer->asset_id);
        $this->assertSame('D-move · IT', $transfer->from_snapshot['department']);
        $this->assertSame('D-TARGET-move · Finance', $transfer->to_snapshot['department']);

        $this->actingAs($actor)
            ->get(route('transaction.asset-management.transfers.show', $transfer))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transaction/AssetManagement/Transfer/Show')
                ->where('transfer.number', $transfer->number)
                ->where('transfer.asset.number', $asset->number));

        $this->actingAs($actor)
            ->get(route('transaction.asset-management.assignments.show', $asset))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transaction/AssetManagement/Assignment/Show')
                ->has('asset.transfer_histories', 1)
                ->where('asset.transfer_histories.0.number', $transfer->number));
    }

    public function test_transfer_to_another_company_requires_return_from_current_company_custodian(): void
    {
        [$actor, $asset, $target] = $this->createTransferSetup('block');
        $custodian = User::query()->create([
            'name' => 'Current Company Custodian',
            'email' => 'custodian-block@example.test',
            'password' => 'password',
            'company_id' => $asset->company_id,
            'is_active' => true,
        ]);
        $asset->update(['custodian_id' => $custodian->id]);
        $this->grantAssetPermissions($actor, 'asset.transfer');

        $this->actingAs($actor)
            ->from(route('transaction.asset-management.transfers.create', $asset))
            ->post(route('transaction.asset-management.transfers.store'), [
                'asset_id' => $asset->id,
                'company_id' => $target['company']->id,
                'department_id' => $target['department']->id,
                'location_id' => $target['location']->id,
                'effective_at' => now()->toDateString(),
                'reason' => 'Pemindahan ke perusahaan lain untuk operasional.',
            ])
            ->assertSessionHasErrors('asset_id');

        $this->assertSame(0, AssetTransferHistory::query()->count());
        $this->assertSame($asset->company_id, $asset->fresh()->company_id);
        $this->assertSame($custodian->id, $asset->fresh()->custodian_id);
    }

    public function test_assignment_can_place_an_asset_in_a_room_without_a_user_custodian(): void
    {
        [$actor, $asset] = $this->createTransferSetup('room');
        $room = Location::query()->create([
            'company_id' => $asset->company_id,
            'branch_id' => $asset->branch_id,
            'code' => 'ROOM-ROOM',
            'name' => 'Meeting Room',
            'location_type' => 'Room',
            'is_active' => true,
        ]);
        NumberingDocument::query()->create([
            'code' => 'ASSET_ASSIGNMENT',
            'name' => 'Asset Assignment',
            'pattern' => 'ASG-{SEQ}',
            'sequence_length' => 6,
            'reset_period' => 'never',
            'is_active' => true,
        ]);
        $this->grantAssetPermissions($actor, 'asset.transfer');

        $this->actingAs($actor)
            ->post(route('transaction.asset-management.assignments.assign', $asset), [
                'assignment_target' => Asset::ASSIGNMENT_LOCATION,
                'location_id' => $room->id,
                'condition' => 'GOOD',
                'effective_at' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $asset->refresh();
        $history = AssetAssignmentHistory::query()->where('asset_id', $asset->id)->firstOrFail();

        $this->assertSame(Asset::ASSIGNMENT_LOCATION, $asset->assignment_type);
        $this->assertNull($asset->custodian_id);
        $this->assertSame($room->id, $asset->location_id);
        $this->assertSame('PLACED', $history->event_type);
        $this->assertSame('ROOM-ROOM · Meeting Room', $history->to_snapshot['assignment_target']);
    }

    public function test_transfer_pages_require_asset_permissions(): void
    {
        [$user, $asset] = $this->createTransferSetup('access');

        $this->actingAs($user)
            ->get(route('transaction.asset-management.transfers.index'))
            ->assertForbidden();
        $this->actingAs($user)
            ->get(route('transaction.asset-management.transfers.create', $asset))
            ->assertForbidden();
    }

    /** @return array{User, Asset, array{company: Company, department: Department, branch: Branch, location: Location}} */
    private function createTransferSetup(string $prefix): array
    {
        $user = User::query()->create([
            'name' => 'Transfer Actor',
            'email' => "transfer-{$prefix}@example.test",
            'password' => 'password',
            'is_active' => true,
        ]);
        $company = Company::query()->create(['code' => strtoupper(substr($prefix, 0, 3)), 'name' => 'Origin Company', 'is_active' => true]);
        $department = Department::query()->create(['company_id' => $company->id, 'code' => "D-{$prefix}", 'name' => 'IT', 'is_active' => true]);
        $branch = Branch::query()->create(['company_id' => $company->id, 'code' => "B-{$prefix}", 'name' => 'Main Branch', 'is_active' => true]);
        $location = Location::query()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'code' => "L-{$prefix}", 'name' => 'IT Office', 'is_active' => true]);
        $category = Category::query()->create(['code' => "C-{$prefix}", 'name' => 'IT', 'is_active' => true]);
        $type = Type::query()->create(['category_id' => $category->id, 'code' => "T-{$prefix}", 'name' => 'Laptop', 'is_active' => true]);
        $brand = Brand::query()->create(['code' => "BR-{$prefix}", 'name' => 'Brand', 'is_active' => true]);
        $model = AssetModel::query()->create(['brand_id' => $brand->id, 'type_id' => $type->id, 'code' => "M-{$prefix}", 'name' => 'Model', 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'group' => 'ASSET',
            'code' => "P-{$prefix}",
            'name' => 'Test Laptop',
            'is_active' => true,
        ]);
        $asset = Asset::query()->create([
            'number' => "ASSET-TRANSFER-{$prefix}",
            'registration_method' => Asset::METHOD_EXISTING,
            'status' => Asset::STATUS_ACTIVE,
            'condition' => 'GOOD',
            'product_id' => $product->id,
            'category_id' => $category->id,
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'location_id' => $location->id,
            'branch_id' => $branch->id,
            'acquisition_date' => now()->toDateString(),
            'registered_by' => $user->id,
        ]);

        $targetCompany = Company::query()->create(['code' => "T-{$prefix}", 'name' => 'Target Company', 'is_active' => true]);
        $targetDepartment = Department::query()->create(['company_id' => $targetCompany->id, 'code' => "D-TARGET-{$prefix}", 'name' => 'Finance', 'is_active' => true]);
        $targetBranch = Branch::query()->create(['company_id' => $targetCompany->id, 'code' => "B-TARGET-{$prefix}", 'name' => 'Target Branch', 'is_active' => true]);
        $targetLocation = Location::query()->create(['company_id' => $targetCompany->id, 'branch_id' => $targetBranch->id, 'code' => "L-TARGET-{$prefix}", 'name' => 'Finance Office', 'is_active' => true]);

        return [$user, $asset, [
            'company' => $targetCompany,
            'department' => $targetDepartment,
            'branch' => $targetBranch,
            'location' => $targetLocation,
        ]];
    }

    private function grantAssetPermissions(User $user, string ...$permissionNames): void
    {
        foreach ($permissionNames as $permissionName) {
            $user->givePermissionTo(Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]));
        }
    }
}
