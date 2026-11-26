<?php

namespace Tests\Feature\Master;

use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LocationHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_cannot_use_a_parent_from_another_branch(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('master.organization.create', 'web'));

        $company = Company::query()->create([
            'code' => 'LOC-COMPANY',
            'name' => 'Location Company',
            'is_active' => true,
        ]);
        $parentBranch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'LOC-BRANCH-A',
            'name' => 'Branch A',
            'is_active' => true,
        ]);
        $targetBranch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'LOC-BRANCH-B',
            'name' => 'Branch B',
            'is_active' => true,
        ]);
        $parent = Location::query()->create([
            'company_id' => $company->id,
            'branch_id' => $parentBranch->id,
            'code' => 'PARENT-A',
            'name' => 'Ruang lantai 1',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->from(route('master.organization.locations.create'))
            ->post(route('master.organization.locations.store'), [
                'company_id' => $company->id,
                'branch_id' => $targetBranch->id,
                'code' => 'ROOM-B-01',
                'name' => 'Ruang rapat',
                'parent_id' => $parent->id,
                'location_type' => 'ROOM',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('mst_org_locations', [
            'company_id' => $company->id,
            'code' => 'ROOM-B-01',
        ]);
    }

    public function test_location_rejects_a_type_that_is_not_in_the_dropdown_options(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('master.organization.create', 'web'));
        $company = Company::query()->create([
            'code' => 'LOC-TYPE-COMPANY',
            'name' => 'Location Type Company',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->from(route('master.organization.locations.create'))
            ->post(route('master.organization.locations.store'), [
                'company_id' => $company->id,
                'branch_id' => null,
                'code' => 'LOC-INVALID-TYPE',
                'name' => 'Custom Location Type',
                'parent_id' => null,
                'location_type' => 'MEETING_AREA',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('location_type');

        $this->assertDatabaseMissing('mst_org_locations', [
            'company_id' => $company->id,
            'code' => 'LOC-INVALID-TYPE',
        ]);
    }
}
