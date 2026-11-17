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
use App\Models\Transaction\AssetManagement\AssetIncident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AssetIncidentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_incident_report_keeps_asset_unchanged_until_investigation_is_resolved(): void
    {
        Storage::fake('local');
        [$user, $asset] = $this->createIncidentAsset('report');
        $this->grantAssetPermissions($user, 'asset.create');

        $this->actingAs($user)
            ->from(route('transaction.asset-management.incidents.create'))
            ->post(route('transaction.asset-management.incidents.store'), [
                'asset_id' => $asset->id,
                'incident_type' => AssetIncident::TYPE_DAMAGED,
                'severity' => 'HIGH',
                'occurred_at' => now()->subHour()->format('Y-m-d\TH:i'),
                'reported_condition' => 'DAMAGED',
                'description' => 'Laptop mati setelah terkena cairan.',
                'evidence' => UploadedFile::fake()->create('photo.pdf', 120, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $incident = AssetIncident::query()->firstOrFail();
        $this->assertSame('GOOD', $asset->fresh()->condition);
        $this->assertSame(Asset::STATUS_ACTIVE, $asset->fresh()->status);
        $this->assertDatabaseHas('asset_incident_events', [
            'asset_incident_id' => $incident->id,
            'event_type' => 'REPORTED',
            'status_to' => AssetIncident::STATUS_REPORTED,
        ]);
        Storage::disk('local')->assertExists($incident->evidence_path);

        $this->grantAssetPermissions($user, 'asset.update');
        $this->actingAs($user)
            ->post(route('transaction.asset-management.incidents.investigate', $incident), [
                'investigation_findings' => 'Pemeriksaan awal memastikan ada kerusakan pada unit.',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame(AssetIncident::STATUS_INVESTIGATING, $incident->fresh()->status);

        $this->actingAs($user)
            ->post(route('transaction.asset-management.incidents.resolve', $incident), [
                'resolution_type' => AssetIncident::RESOLUTION_REPAIRED,
                'resolution_notes' => 'Unit sudah diperiksa dan kondisinya masih perlu perbaikan lanjutan.',
                'condition_after' => 'NEEDS_REPAIR',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(AssetIncident::STATUS_RESOLVED, $incident->fresh()->status);
        $this->assertSame('NEEDS_REPAIR', $asset->fresh()->condition);
        $this->assertDatabaseHas('asset_incident_events', [
            'asset_incident_id' => $incident->id,
            'event_type' => 'RESOLVED',
            'condition_from' => 'GOOD',
            'condition_to' => 'NEEDS_REPAIR',
        ]);
    }

    public function test_confirmed_lost_incident_marks_asset_missing_and_recovery_restores_active_status(): void
    {
        [$user, $asset] = $this->createIncidentAsset('missing');
        $this->grantAssetPermissions($user, 'asset.create', 'asset.update');

        $this->actingAs($user)
            ->post(route('transaction.asset-management.incidents.store'), [
                'asset_id' => $asset->id,
                'incident_type' => AssetIncident::TYPE_LOST,
                'severity' => 'URGENT',
                'occurred_at' => now()->subDay()->format('Y-m-d\TH:i'),
                'description' => 'Aset tidak ditemukan setelah pemeriksaan ruang kerja.',
            ])
            ->assertSessionHasNoErrors();
        $incident = AssetIncident::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('transaction.asset-management.incidents.investigate', $incident), [
                'investigation_findings' => 'Pemeriksaan lokasi dan konfirmasi kepada pengguna selesai.',
            ])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->post(route('transaction.asset-management.incidents.resolve', $incident), [
                'resolution_type' => AssetIncident::RESOLUTION_MISSING_CONFIRMED,
                'resolution_notes' => 'Aset dikonfirmasi hilang dan belum ditemukan.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Asset::STATUS_MISSING, $asset->fresh()->status);
        $this->assertDatabaseHas('asset_incident_events', [
            'asset_incident_id' => $incident->id,
            'asset_status_from' => Asset::STATUS_ACTIVE,
            'asset_status_to' => Asset::STATUS_MISSING,
        ]);
    }

    public function test_dismissed_report_does_not_change_asset_condition_or_status(): void
    {
        [$user, $asset] = $this->createIncidentAsset('dismiss');
        $this->grantAssetPermissions($user, 'asset.create', 'asset.update');

        $this->actingAs($user)
            ->post(route('transaction.asset-management.incidents.store'), [
                'asset_id' => $asset->id,
                'incident_type' => AssetIncident::TYPE_DAMAGED,
                'severity' => 'NORMAL',
                'occurred_at' => now()->subHour()->format('Y-m-d\TH:i'),
                'reported_condition' => 'DAMAGED',
                'description' => 'Laporan kerusakan yang kemudian tidak terbukti.',
            ])
            ->assertSessionHasNoErrors();
        $incident = AssetIncident::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('transaction.asset-management.incidents.dismiss', $incident), [
                'dismissal_reason' => 'Pemeriksaan membuktikan perangkat berfungsi normal.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(AssetIncident::STATUS_DISMISSED, $incident->fresh()->status);
        $this->assertSame('GOOD', $asset->fresh()->condition);
        $this->assertSame(Asset::STATUS_ACTIVE, $asset->fresh()->status);
        $this->assertDatabaseHas('asset_incident_events', [
            'asset_incident_id' => $incident->id,
            'event_type' => 'DISMISSED',
            'condition_from' => 'GOOD',
            'condition_to' => 'GOOD',
            'asset_status_from' => Asset::STATUS_ACTIVE,
            'asset_status_to' => Asset::STATUS_ACTIVE,
        ]);
    }

    public function test_users_without_asset_permissions_cannot_open_or_create_incident_reports(): void
    {
        [$user, $asset] = $this->createIncidentAsset('unauthorized');

        $this->actingAs($user)
            ->get(route('transaction.asset-management.incidents.index'))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('transaction.asset-management.incidents.store'), [
                'asset_id' => $asset->id,
                'incident_type' => AssetIncident::TYPE_OTHER,
                'severity' => 'NORMAL',
                'occurred_at' => now()->subHour()->format('Y-m-d\TH:i'),
                'description' => 'Laporan uji tanpa permission.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('asset_incidents', 0);
    }

    public function test_incident_index_displays_persisted_incidents(): void
    {
        [$user, $asset] = $this->createIncidentAsset('index');
        $this->grantAssetPermissions($user, 'asset.view');
        $incident = AssetIncident::query()->create([
            'number' => 'INC-TEST-001',
            'asset_id' => $asset->id,
            'incident_type' => AssetIncident::TYPE_OTHER,
            'severity' => 'NORMAL',
            'status' => AssetIncident::STATUS_REPORTED,
            'occurred_at' => now()->subHour(),
            'description' => 'Keterangan insiden test.',
            'condition_before' => 'GOOD',
            'asset_status_before' => Asset::STATUS_ACTIVE,
            'reported_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('transaction.asset-management.incidents.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transaction/AssetManagement/Incident/Index')
                ->where('incidents.total', 1)
                ->where('incidents.data.0.number', $incident->number));
    }

    public function test_incident_detail_loads_the_event_timeline(): void
    {
        [$user, $asset] = $this->createIncidentAsset('show');
        $this->grantAssetPermissions($user, 'asset.view');
        $incident = AssetIncident::query()->create([
            'number' => 'INC-TEST-SHOW',
            'asset_id' => $asset->id,
            'incident_type' => AssetIncident::TYPE_OTHER,
            'severity' => 'NORMAL',
            'status' => AssetIncident::STATUS_REPORTED,
            'occurred_at' => now()->subHour(),
            'description' => 'Keterangan insiden untuk halaman detail.',
            'condition_before' => 'GOOD',
            'asset_status_before' => Asset::STATUS_ACTIVE,
            'reported_by' => $user->id,
        ]);
        $incident->events()->create([
            'event_type' => 'REPORTED',
            'status_to' => AssetIncident::STATUS_REPORTED,
            'condition_from' => 'GOOD',
            'condition_to' => 'GOOD',
            'asset_status_from' => Asset::STATUS_ACTIVE,
            'asset_status_to' => Asset::STATUS_ACTIVE,
            'notes' => $incident->description,
            'performed_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('transaction.asset-management.incidents.show', $incident))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transaction/AssetManagement/Incident/Show')
                ->where('incident.id', $incident->id)
                ->has('incident.events', 1)
                ->where('incident.events.0.performed_by.id', $user->id));
    }

    /** @return array{User, Asset} */
    private function createIncidentAsset(string $prefix): array
    {
        $user = User::query()->create([
            'name' => 'Incident User',
            'email' => "incident-{$prefix}@example.test",
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
            'number' => "ASSET-INCIDENT-{$prefix}",
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
