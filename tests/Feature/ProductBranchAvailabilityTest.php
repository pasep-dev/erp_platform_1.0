<?php

namespace Tests\Feature;

use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Product\Brand;
use App\Models\Master\Product\Category;
use App\Models\Master\Product\Model as ProductModel;
use App\Models\Master\Product\Product;
use App\Models\Master\Product\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBranchAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_user_can_access_product_without_branch_assignment(): void
    {
        $company = Company::query()->create([
            'code' => 'PRODUCT-BRANCH-COMPANY',
            'name' => 'Product Branch Company',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'PRODUCT-BRANCH-ONE',
            'name' => 'Product Branch One',
            'is_active' => true,
        ]);
        $department = Department::query()->create([
            'company_id' => $company->id,
            'code' => 'PRODUCT-BRANCH-IT',
            'name' => 'IT',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);
        $category = Category::query()->create([
            'code' => 'PRODUCT-BRANCH-CATEGORY',
            'name' => 'Hardware',
            'is_active' => true,
        ]);
        $type = Type::query()->create([
            'category_id' => $category->id,
            'code' => 'PRODUCT-BRANCH-TYPE',
            'name' => 'Computer',
            'is_active' => true,
        ]);
        $brand = Brand::query()->create([
            'code' => 'PRODUCT-BRANCH-BRAND',
            'name' => 'Example Brand',
            'is_active' => true,
        ]);
        $model = ProductModel::query()->create([
            'brand_id' => $brand->id,
            'type_id' => $type->id,
            'code' => 'PRODUCT-BRANCH-MODEL',
            'name' => 'Example Model',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'group' => 'CONSUMABLE',
            'inventory_tracking_mode' => 'QUANTITY',
            'code' => 'PRODUCT-BRANCH-PRODUCT',
            'name' => 'Shared Product',
            'is_active' => true,
        ]);
        $product->departments()->attach($department->id);

        $this->actingAs($user);

        $this->assertTrue(Product::query()->whereKey($product->id)->exists());
        $this->assertDatabaseMissing('mst_product_branches', [
            'product_id' => $product->id,
            'branch_id' => $branch->id,
        ]);
    }
}
