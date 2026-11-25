<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private const TABLE_RENAMES = [
        'asset_assignment_histories' => 'trx_asset_assignment_histories',
        'asset_audit_items' => 'trx_asset_audit_items',
        'asset_audit_sessions' => 'trx_asset_audit_sessions',
        'asset_disposals' => 'trx_asset_disposals',
        'asset_incident_events' => 'trx_asset_incident_events',
        'asset_incidents' => 'trx_asset_incidents',
        'asset_maintenance_components' => 'trx_asset_maintenance_components',
        'asset_maintenance_records' => 'trx_asset_maintenance_records',
        'asset_transfer_histories' => 'trx_asset_transfer_histories',
        'assets' => 'trx_assets',
        'inventory_department_warehouses' => 'trx_inventory_department_warehouses',
        'inventory_document_items' => 'trx_inventory_document_items',
        'inventory_documents' => 'trx_inventory_documents',
        'inventory_receipt_integrations' => 'trx_inventory_receipt_integrations',
        'inventory_request_items' => 'trx_inventory_request_items',
        'inventory_requests' => 'trx_inventory_requests',
        'inventory_stock_movements' => 'trx_inventory_stock_movements',
        'inventory_stock_thresholds' => 'trx_inventory_stock_thresholds',
        'inventory_stocktake_items' => 'trx_inventory_stocktake_items',
        'inventory_stocktakes' => 'trx_inventory_stocktakes',
        'goods_receipt_items' => 'trx_goods_receipt_items',
        'goods_receipts' => 'trx_goods_receipts',
        'purchase_order_item_price_histories' => 'trx_purchase_order_item_price_histories',
        'purchase_order_items' => 'trx_purchase_order_items',
        'purchase_orders' => 'trx_purchase_orders',
        'purchase_request_items' => 'trx_purchase_request_items',
        'purchase_requests' => 'trx_purchase_requests',
        'mst_asset_brand_categories' => 'mst_product_brand_categories',
        'mst_asset_brands' => 'mst_product_brands',
        'mst_asset_categories' => 'mst_product_categories',
        'mst_asset_license_types' => 'mst_product_license_types',
        'mst_asset_models' => 'mst_product_models',
        'mst_asset_types' => 'mst_product_types',
        'product_branch' => 'mst_product_branches',
        'product_department' => 'mst_product_departments',
        'mst_taxes' => 'mst_prc_taxes',
        'mst_units_of_measure' => 'mst_prc_uom',
        'mst_vendor_types' => 'mst_prc_vendor_types',
        'mst_vendors' => 'mst_prc_vendors',
        'mst_payment_terms' => 'mst_prc_payment_terms',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLE_RENAMES as $legacyName => $canonicalName) {
            $this->renameTable($legacyName, $canonicalName);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse(self::TABLE_RENAMES, true) as $legacyName => $canonicalName) {
            $this->renameTable($canonicalName, $legacyName);
        }
    }

    private function renameTable(string $currentName, string $targetName): void
    {
        $currentExists = Schema::hasTable($currentName);
        $targetExists = Schema::hasTable($targetName);

        if ($currentExists && $targetExists) {
            throw new RuntimeException(
                "Cannot rename [{$currentName}] to [{$targetName}] because both tables exist.",
            );
        }

        if (! $currentExists && ! $targetExists) {
            throw new RuntimeException(
                "Cannot rename [{$currentName}] to [{$targetName}] because neither table exists.",
            );
        }

        if ($currentExists) {
            Schema::rename($currentName, $targetName);
        }
    }
};
