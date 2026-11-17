<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_department_access', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('department_id')->constrained('mst_org_departments')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'department_id']);
        });

        Schema::create('product_department', function (Blueprint $table): void {
            $table->foreignUlid('product_id')->constrained('mst_products')->cascadeOnDelete();
            $table->foreignUlid('department_id')->constrained('mst_org_departments')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['product_id', 'department_id']);
            $table->index(['department_id', 'product_id']);
        });

        $this->backfillProductDepartments();
    }

    public function down(): void
    {
        Schema::dropIfExists('product_department');
        Schema::dropIfExists('user_department_access');
    }

    private function backfillProductDepartments(): void
    {
        $now = now();
        $associations = [
            ['assets', 'product_id', 'department_id'],
            ['inventory_stock_movements', 'product_id', 'department_id'],
            ['inventory_stock_thresholds', 'product_id', 'department_id'],
            ['inventory_receipt_integrations', 'product_id', 'department_id'],
        ];

        foreach ($associations as [$table, $productColumn, $departmentColumn]) {
            if (! Schema::hasTable($table)
                || ! Schema::hasColumn($table, $productColumn)
                || ! Schema::hasColumn($table, $departmentColumn)) {
                continue;
            }

            DB::table($table)
                ->select([$productColumn, $departmentColumn])
                ->whereNotNull($productColumn)
                ->whereNotNull($departmentColumn)
                ->distinct()
                ->orderBy($productColumn)
                ->chunk(500, function ($rows) use ($now): void {
                    DB::table('product_department')->insertOrIgnore(
                        $rows->map(fn (object $row): array => [
                            'product_id' => $row->product_id,
                            'department_id' => $row->department_id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->all(),
                    );
                });
        }

        $parentAssociations = [
            ['purchase_request_items', 'purchase_request_id', 'purchase_requests'],
            ['purchase_order_items', 'purchase_order_id', 'purchase_orders'],
            ['goods_receipt_items', 'goods_receipt_id', 'goods_receipts'],
            ['inventory_document_items', 'inventory_document_id', 'inventory_documents'],
            ['inventory_request_items', 'inventory_request_id', 'inventory_requests'],
        ];

        foreach ($parentAssociations as [$itemsTable, $parentKey, $parentTable]) {
            if (! Schema::hasTable($itemsTable)
                || ! Schema::hasTable($parentTable)
                || ! Schema::hasColumn($itemsTable, 'product_id')
                || ! Schema::hasColumn($itemsTable, $parentKey)
                || ! Schema::hasColumn($parentTable, 'department_id')) {
                continue;
            }

            DB::table($itemsTable.' as item')
                ->join($parentTable.' as parent', 'parent.id', '=', 'item.'.$parentKey)
                ->whereNotNull('item.product_id')
                ->whereNotNull('parent.department_id')
                ->select(['item.product_id', 'parent.department_id'])
                ->distinct()
                ->orderBy('item.product_id')
                ->chunk(500, function ($rows) use ($now): void {
                    DB::table('product_department')->insertOrIgnore(
                        $rows->map(fn (object $row): array => [
                            'product_id' => $row->product_id,
                            'department_id' => $row->department_id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->all(),
                    );
                });
        }
    }
};
