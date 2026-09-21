<?php

namespace Tests\Feature\Master\Organization;

use App\Models\Master\Organization\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_company_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('master.organization.companies.index'));

        $response->assertOk();
    }

    public function test_authenticated_user_can_create_company(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('master.organization.companies.store'), [
                'code' => 'CAA',
                'name' => 'PT Contoh Abadi',
                'short_name' => 'CAA',
                'is_active' => true,
            ]);

        $response
            ->assertRedirect(route('master.organization.companies.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('mst_org_companies', [
            'code' => 'CAA',
            'name' => 'PT Contoh Abadi',
        ]);
    }

    public function test_company_code_must_be_unique(): void
    {
        $user = User::factory()->create();

        Company::create([
            'code' => 'CAA',
            'name' => 'PT Contoh Abadi',
            'short_name' => 'CAA',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->from(route('master.organization.companies.create'))
            ->post(route('master.organization.companies.store'), [
                'code' => 'CAA',
                'name' => 'Perusahaan Kedua',
                'short_name' => 'CAA-2',
                'is_active' => true,
            ]);

        $response
            ->assertRedirect(route('master.organization.companies.create'))
            ->assertSessionHasErrors('code');
    }

    public function test_company_can_be_updated(): void
    {
        $user = User::factory()->create();

        $company = Company::create([
            'code' => 'CAA',
            'name' => 'PT Contoh Abadi',
            'short_name' => 'CAA',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->put(route('master.organization.companies.update', $company), [
                'code' => 'CAA',
                'name' => 'PT Contoh Abadi Updated',
                'short_name' => 'CAA',
                'is_active' => false,
            ]);

        $response
            ->assertRedirect(route('master.organization.companies.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('mst_org_companies', [
            'id' => $company->id,
            'name' => 'PT Contoh Abadi Updated',
            'is_active' => false,
        ]);
    }

    public function test_company_is_soft_deleted(): void
    {
        $user = User::factory()->create();

        $company = Company::create([
            'code' => 'CAA',
            'name' => 'PT Contoh Abadi',
            'short_name' => 'CAA',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->delete(route('master.organization.companies.destroy', $company));

        $response
            ->assertRedirect(route('master.organization.companies.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('mst_org_companies', [
            'id' => $company->id,
        ]);
    }
}
