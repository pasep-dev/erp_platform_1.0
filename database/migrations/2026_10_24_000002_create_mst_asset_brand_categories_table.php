<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mst_asset_brand_categories', function (Blueprint $table): void {
            $table->foreignUlid('brand_id')
                ->constrained('mst_asset_brands')
                ->cascadeOnDelete();
            $table->foreignUlid('category_id')
                ->constrained('mst_asset_categories')
                ->cascadeOnDelete();

            $table->primary(['brand_id', 'category_id']);
        });

        $productBrandCategories = DB::table('mst_products')
            ->select('brand_id', 'category_id')
            ->whereNotNull('brand_id')
            ->whereNotNull('category_id')
            ->get();

        $modelBrandCategories = DB::table('mst_asset_models')
            ->join('mst_asset_types', 'mst_asset_types.id', '=', 'mst_asset_models.type_id')
            ->select('mst_asset_models.brand_id', 'mst_asset_types.category_id')
            ->get();

        foreach ($productBrandCategories->concat($modelBrandCategories) as $pair) {
            DB::table('mst_asset_brand_categories')->insertOrIgnore([
                'brand_id' => $pair->brand_id,
                'category_id' => $pair->category_id,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_asset_brand_categories');
    }
};
