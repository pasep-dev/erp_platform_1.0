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
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetDisposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AssetDisposalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_disposal_keeps_history_and_marks_asset_disposed(): void
    {
        Storage::fake('local');
        [$user, $asset] = $this->createDisposalAsset('approve');
        $this->grantAssetPermissions($user, 'asset.view', 'asset.dispose');

        $this->actingAs($user)
            ->post(route('transaction.asset-management.disposals.store'), [
                'asset_id' => $asset->id,
                'disposal_type' => AssetDisposal::TYPE_SOLD,
                'disposal_date' => now()->toDateString(),
                'amount' => 150000,
                'reason' => 'Perangkat dijual karena sudah tidak digunakan.',
                'evidence' => UploadedFile::fake()->create('sale.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $disposal = AssetDisposal::query()->firstOrFail();
        $this->assertSame(Asset::STATUS_DISPOSAL_PENDING, $asset->fresh()->status);
        $this->assertSame('ASSET-DISPOSAL-approve', $disposal->asset_snapshot['number']);
        Storage::disk('local')->assertExists($disposal->evidence_path);

        $this->actingAs($user)
            ->post(route('transaction.asset-management.disposals.approve', $disposal), [
                'decision_notes' => 'Disetujui sesuai dokumen penjualan.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Asset::STATUS_DISPOSED, $asset->fresh()->status);
        $this->assertSame(AssetDisposal::STATUS_APPROVED, $disposal->fresh()->status);
        $this->assertSame($user->id, $disposal->fresh()->decided_by);

        $this->actingAs($user)
            ->get(route('transaction.asset-management.disposals.show', $disposal))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transaction/AssetManagement/Disposal/Show')
                ->where('disposal.status', AssetDisposal::STATUS_APPROVED)
                ->where('disposal.asset.status', Asset::STATUS_DISPOSED));
    }

    public function test_rejected_disposal_restores_asset_to_active_and_preserves_decision_reason(): void
    {
        [$user, $asset] = $this->createDisposalAsset('reject');
        $this->grantAssetPermissions($user, 'asset.dispose');
        $disposal = $this->requestDisposal($user, $asset);

        $this->actingAs($user)
            ->post(route('transaction.asset-management.disposals.reject', $disposal), [
                'decision_notes' => 'Dokumen nilai jual belum mencukupi.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Asset::STATUS_ACTIVE, $asset->fresh()->status);
        $this->assertSame(AssetDisposal::STATUS_REJECTED, $disposal->fresh()->status);
        $this->assertSame('Dokumen nilai jual belum mencukupi.', $disposal->fresh()->decision_notes);
    }

    public function test_assigned_asset_cannot_be_submitted_for_disposal(): void
    {
        [$user, $asset] = $this->createDisposalAsset('assigned');
        $custodian = User::query()->create([
            'name' => 'Asset Custodian',
            'email' => 'disposal-custodian@example.test',
            'password' => 'password',
            'company_id' => $asset->company_id,
            'is_active' => true,
        ]);
        $asset->update(['custodian_id' => $custodian->id]);
        $this->grantAssetPermissions($user, 'asset.dispose');

        $this->actingAs($user)
            ->post(route('transaction.asset-management.disposals.store'), [
                'asset_id' => $asset->id,
                'disposal_type' => AssetDisposal::TYPE_WRITTEN_OFF,
                'disposal_date' => now()->toDateString(),
                'reason' => 'Aset tidak lagi dibutuhkan operasional.',
            ])
            ->assertSessionHasErrors('asset_id');

        $this->assertSame(Asset::STATUS_ACTIVE, $asset->fresh()->status);
        $this->assertDatabaseCount('asset_disposals', 0);
    }

    public function test_disposal_pages_require_view_and_disposal_permissions(): void
    {
        [$user] = $this->createDisposalAsset('permission');

        $this->actingAs($user)
            ->get(route('transaction.asset-management.disposals.index'))
            ->assertForbidden();
        $this->actingAs($user)
            ->get(route('transaction.asset-management.disposals.create'))
            ->assertForbidden();
    }

    private function requestDisposal(User $user, Asset $asset): AssetDisposal
    {
        $this->actingAs($user)
            ->post(route('transaction.asset-management.disposals.store'), [
                'asset_id' => $asset->id,
                'disposal_type' => AssetDisposal::TYPE_DONATED,
                'disposal_date' => now()->toDateString(),
                'reason' => 'Aset dihibahkan untuk kegiatan sosial perusahaan.',
            ])
            ->assertSessionHasNoErrors();

        return AssetDisposal::query()->firstOrFail();
    }

    /** @return array{User, Asset} */
    private function createDisposalAsset(string $prefix): array
    {
        $user = User::query()->create([
            'name' => 'Disposal User',
            'email' => "disposal-{$prefix}@example.test",
            'password' => 'password',
            'is_active' => true,
        ]);
        $company = Company::query()->create(['code' => strtoupper(substr($prefix, 0, 3)), 'name' => 'Test Company', 'is_active' => true]);
        $department = Department::query()->create(['company_id' => $company->id, 'code' => "D-{$prefix}", 'name' => 'IT', 'is_active' => true]);
        $branch = Branch::query()->create(['company_id' => $company->id, 'code' => "B-{$prefix}", 'name' => 'Branch', 'is_active' => true]);
        $location = Location::query()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'code' => "L-{$prefix}", 'name' => 'Office', 'is_active' => true]);
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
            'number' => "ASSET-DISPOSAL-{$prefix}",
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

        return [$user, $asset];
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
