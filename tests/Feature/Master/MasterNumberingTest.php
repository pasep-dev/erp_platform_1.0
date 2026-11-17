<?php

namespace Tests\Feature\Master;

use App\Models\Master\Organization\Company;
use App\Models\Master\Procurement\VendorType;
use App\Models\Master\Product\Brand;
use App\Models\Master\Product\Category;
use App\Models\Master\Product\Type;
use App\Models\NumberingDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MasterNumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_type_code_is_generated_from_its_active_rule(): void
    {
        $user = User::factory()->create();
        $this->grantMasterPermissions($user, 'master.procurement.create');
        $this->createNumberingDocument('VENDOR_TYPE', 'VTYPE-{SEQ}');

        $this->actingAs($user)
            ->post(route('master.procurement.vendor-types.store'), [
                'code' => '',
                'name' => 'Supplier',
                'description' => null,
                'is_active' => true,
            ])
            ->assertRedirect(route('master.procurement.vendor-types.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('mst_vendor_types', [
            'code' => 'VTYPE-000001',
            'name' => 'Supplier',
        ]);
    }

    public function test_vendor_code_is_generated_from_its_active_rule(): void
    {
        $user = User::factory()->create();
        $this->grantMasterPermissions($user, 'master.procurement.create');
        $vendorType = VendorType::query()->create([
            'code' => 'SUPPLIER',
            'name' => 'Supplier',
            'is_active' => true,
        ]);
        $this->createNumberingDocument('VENDOR', 'VND-{SEQ}');

        $this->actingAs($user)
            ->post(route('master.procurement.vendors.store'), [
                'vendor_type_id' => $vendorType->id,
                'code' => '',
                'name' => 'Test Vendor',
                'is_active' => true,
            ])
            ->assertRedirect(route('master.procurement.vendors.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('mst_vendors', [
            'vendor_type_id' => $vendorType->id,
            'code' => 'VND-000001',
            'name' => 'Test Vendor',
        ]);
    }

    public function test_product_code_is_generated_from_its_active_rule(): void
    {
        $user = User::factory()->create();
        $this->grantMasterPermissions($user, 'master.product.create');
        $category = Category::query()->create([
            'code' => 'CAT-TEST',
            'name' => 'Equipment',
            'is_active' => true,
        ]);
        $type = Type::query()->create([
            'category_id' => $category->id,
            'code' => 'TYPE-TEST',
            'name' => 'Laptop',
            'is_active' => true,
        ]);
        $brand = Brand::query()->create([
            'code' => 'BRAND-TEST',
            'name' => 'Example',
            'is_active' => true,
        ]);
        $brand->categories()->attach($category->id);
        $this->createNumberingDocument('PRODUCT', 'PRD-{SEQ}');

        $this->actingAs($user)
            ->post(route('master.product.products.store'), [
                'category_id' => $category->id,
                'type_id' => $type->id,
                'brand_id' => $brand->id,
                'model_id' => null,
                'group' => 'ASSET',
                'inventory_tracking_mode' => 'QUANTITY',
                'base_uom_id' => null,
                'code' => '',
                'name' => 'Test Laptop',
                'description' => null,
                'is_active' => true,
            ])
            ->assertRedirect(route('master.product.products.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('mst_products', [
            'category_id' => $category->id,
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'code' => 'PRD-000001',
            'name' => 'Test Laptop',
        ]);
    }

    public function test_blank_vendor_type_code_requires_an_active_numbering_rule(): void
    {
        $user = User::factory()->create();
        $this->grantMasterPermissions($user, 'master.procurement.create');

        $this->actingAs($user)
            ->from(route('master.procurement.vendor-types.create'))
            ->post(route('master.procurement.vendor-types.store'), [
                'code' => '',
                'name' => 'Supplier',
                'is_active' => true,
            ])
            ->assertSessionHasErrors([
                'code' => 'Enter a vendor type code or configure an active VENDOR_TYPE numbering rule.',
            ]);

        $this->assertDatabaseCount('mst_vendor_types', 0);
    }

    public function test_company_routes_enforce_view_and_delete_permissions(): void
    {
        $user = User::factory()->create();
        $this->grantMasterPermissions($user, 'master.organization.view');
        $company = Company::query()->create([
            'code' => 'AUDIT-TEST',
            'name' => 'Audit Test Company',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('master.organization.companies.index'))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('master.organization.companies.store'), [
                'code' => 'DENIED-CREATE',
                'name' => 'Denied Create Company',
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('master.organization.companies.destroy', $company))
            ->assertForbidden();

        $this->assertModelExists($company);
        $this->assertDatabaseMissing('mst_org_companies', ['code' => 'DENIED-CREATE']);
    }

    private function grantMasterPermissions(User $user, string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
    }

    private function createNumberingDocument(
        string $code,
        string $pattern,
    ): NumberingDocument {
        return NumberingDocument::query()->create([
            'code' => $code,
            'name' => $code,
            'pattern' => $pattern,
            'sequence_length' => 6,
            'sequence_start' => 1,
            'reset_period' => 'never',
            'is_active' => true,
        ]);
    }
}
