<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table('numbering_modules')->insertOrIgnore(array_map(
            static fn (array $module): array => [
                ...$module,
                'default_pattern' => null,
                'sequence_length' => null,
                'reset_period' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                ['code' => 'PRODUCT_BRAND', 'name' => 'Product Brand', 'category' => 'Master Data'],
                ['code' => 'PRODUCT_MODEL', 'name' => 'Product Model', 'category' => 'Master Data'],
                ['code' => 'ASSET_ASSIGNMENT', 'name' => 'Asset Assignment', 'category' => 'Asset Management'],
                ['code' => 'ASSET_INCIDENT', 'name' => 'Asset Incident', 'category' => 'Asset Management'],
                ['code' => 'ASSET_AUDIT', 'name' => 'Asset Audit / Stocktake', 'category' => 'Asset Management'],
                ['code' => 'ASSET_TRANSFER', 'name' => 'Asset Transfer', 'category' => 'Asset Management'],
                ['code' => 'ASSET_DISPOSAL', 'name' => 'Asset Disposal', 'category' => 'Asset Management'],
                ['code' => 'INVENTORY_ITEM', 'name' => 'Inventory Item', 'category' => 'Inventory Management'],
                ['code' => 'INVENTORY_RECEIPT', 'name' => 'Inventory Receipt From GR', 'category' => 'Inventory Management'],
                ['code' => 'INVENTORY_STOCK_TRANSACTION', 'name' => 'Inventory Stock Transaction', 'category' => 'Inventory Management'],
                ['code' => 'INVENTORY_STOCKTAKE', 'name' => 'Inventory Stocktake', 'category' => 'Inventory Management'],
            ],
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
