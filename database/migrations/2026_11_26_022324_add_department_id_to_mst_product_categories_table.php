<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mst_product_categories', function (Blueprint $table): void {
            $table->foreignUlid('department_id')
                ->nullable()
                ->after('id')
                ->constrained('mst_org_departments')
                ->restrictOnDelete();
            $table->index(['department_id', 'is_active']);
        });

        $departmentIdsByCategory = DB::table('mst_products')
            ->join('mst_product_departments', 'mst_product_departments.product_id', '=', 'mst_products.id')
            ->select('mst_products.category_id', 'mst_product_departments.department_id')
            ->whereNull('mst_products.deleted_at')
            ->distinct()
            ->get()
            ->groupBy('category_id')
            ->map(fn ($rows) => $rows->pluck('department_id')->unique()->values());

        $typeDepartments = DB::table('mst_product_types')
            ->join('mst_products', 'mst_products.type_id', '=', 'mst_product_types.id')
            ->join('mst_product_departments', 'mst_product_departments.product_id', '=', 'mst_products.id')
            ->select('mst_product_types.category_id', 'mst_product_departments.department_id')
            ->whereNull('mst_product_types.deleted_at')
            ->whereNull('mst_products.deleted_at')
            ->distinct()
            ->get()
            ->groupBy('category_id');

        foreach ($typeDepartments as $categoryId => $rows) {
            $departmentIdsByCategory->put(
                $categoryId,
                $departmentIdsByCategory->get($categoryId, collect())
                    ->merge($rows->pluck('department_id'))
                    ->unique()
                    ->values(),
            );
        }

        $categoryParents = DB::table('mst_product_categories')
            ->whereNotNull('parent_id')
            ->get(['id', 'parent_id']);

        foreach ([1, 2] as $iteration) {
            foreach ($categoryParents as $category) {
                $childDepartments = $departmentIdsByCategory->get($category->id, collect());
                $parentDepartments = $departmentIdsByCategory->get($category->parent_id, collect());
                $combined = $childDepartments->merge($parentDepartments)->unique()->values();

                $departmentIdsByCategory->put($category->id, $combined);
                $departmentIdsByCategory->put($category->parent_id, $combined);
            }
        }

        foreach ($departmentIdsByCategory as $categoryId => $departmentIds) {
            if ($departmentIds->count() === 1) {
                DB::table('mst_product_categories')
                    ->where('id', $categoryId)
                    ->update(['department_id' => $departmentIds->first()]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mst_product_categories', function (Blueprint $table): void {
            $table->dropForeign(['department_id']);
            $table->dropIndex(['department_id', 'is_active']);
            $table->dropColumn('department_id');
        });
    }
};
