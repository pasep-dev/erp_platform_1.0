<?php

namespace Tests\Feature;

use App\Models\Master\Product\Brand;
use App\Models\Master\Product\Category;
use App\Models\Master\Product\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MasterBrandCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_form_lists_only_root_categories_and_shows_active_child_count(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate([
            'name' => 'master.product.create',
            'guard_name' => 'web',
        ]));
        $rootCategory = Category::query()->create([
            'code' => 'HARDWARE',
            'name' => 'Hardware',
            'is_active' => true,
        ]);
        Category::query()->create([
            'parent_id' => $rootCategory->id,
            'code' => 'LAPTOP',
            'name' => 'Laptop',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('master.product.brands.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Master/Product/Brands/Create')
                ->has('categories', 1)
                ->where('categories.0.id', $rootCategory->id)
                ->where('categories.0.children_count', 1));
    }

    public function test_brand_assignment_to_root_category_applies_to_child_category_type(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate([
            'name' => 'master.product.create',
            'guard_name' => 'web',
        ]));
        $rootCategory = Category::query()->create([
            'code' => 'HARDWARE',
            'name' => 'Hardware',
            'is_active' => true,
        ]);
        $childCategory = Category::query()->create([
            'parent_id' => $rootCategory->id,
            'code' => 'LAPTOP',
            'name' => 'Laptop',
            'is_active' => true,
        ]);
        $brand = Brand::query()->create([
            'code' => 'ACME',
            'name' => 'Acme',
            'is_active' => true,
        ]);
        $brand->categories()->attach($rootCategory->id);
        $type = Type::query()->create([
            'category_id' => $childCategory->id,
            'code' => 'BUSINESS-LAPTOP',
            'name' => 'Business Laptop',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('master.product.models.store'), [
                'brand_id' => $brand->id,
                'type_id' => $type->id,
                'code' => 'ACME-BUSINESS',
                'name' => 'Acme Business',
                'description' => null,
                'is_active' => true,
            ])
            ->assertRedirect(route('master.product.models.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('mst_product_models', [
            'brand_id' => $brand->id,
            'type_id' => $type->id,
            'code' => 'ACME-BUSINESS',
        ]);
    }

    public function test_brand_cannot_be_assigned_directly_to_a_child_category(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate([
            'name' => 'master.product.create',
            'guard_name' => 'web',
        ]));
        $rootCategory = Category::query()->create([
            'code' => 'HARDWARE',
            'name' => 'Hardware',
            'is_active' => true,
        ]);
        $childCategory = Category::query()->create([
            'parent_id' => $rootCategory->id,
            'code' => 'LAPTOP',
            'name' => 'Laptop',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->from(route('master.product.brands.create'))
            ->post(route('master.product.brands.store'), [
                'code' => 'ACME',
                'name' => 'Acme',
                'category_ids' => [$childCategory->id],
                'description' => null,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('category_ids.0');

        $this->assertDatabaseMissing('mst_product_brands', [
            'code' => 'ACME',
        ]);
    }
}
