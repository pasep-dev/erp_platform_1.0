<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->foreignUlid('warehouse_location_id')
                ->nullable()
                ->after('department_id')
                ->constrained('mst_org_locations')
                ->restrictOnDelete();
            $table->foreignUlid('storage_location_id')
                ->nullable()
                ->after('warehouse_location_id')
                ->constrained('mst_org_locations')
                ->restrictOnDelete();
        });

        DB::table('inventory_receipt_integrations as integration')
            ->join('goods_receipt_items as receipt_item', 'receipt_item.id', '=', 'integration.goods_receipt_item_id')
            ->whereNotNull('integration.location_id')
            ->select('receipt_item.goods_receipt_id', 'integration.location_id')
            ->orderBy('receipt_item.goods_receipt_id')
            ->get()
            ->unique('goods_receipt_id')
            ->each(function (object $row): void {
                $location = DB::table('mst_org_locations')->where('id', $row->location_id)->first();

                if ($location === null) {
                    return;
                }

                $warehouseId = $location->parent_id ?: $location->id;
                $storageId = $location->parent_id ? $location->id : null;

                DB::table('goods_receipts')
                    ->where('id', $row->goods_receipt_id)
                    ->update([
                        'warehouse_location_id' => $warehouseId,
                        'storage_location_id' => $storageId,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('storage_location_id');
            $table->dropConstrainedForeignId('warehouse_location_id');
        });
    }
};
