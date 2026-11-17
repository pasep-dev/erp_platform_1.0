<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_thresholds', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('department_id')->constrained('mst_org_departments')->restrictOnDelete();
            $table->foreignUlid('location_id')->constrained('mst_org_locations')->restrictOnDelete();
            $table->foreignUlid('product_id')->constrained('mst_products')->restrictOnDelete();
            $table->decimal('minimum_quantity', 15, 3);
            $table->foreignId('configured_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['department_id', 'location_id', 'product_id'], 'inventory_threshold_department_location_product_unique');
            $table->index(['department_id', 'location_id']);
        });

        Schema::create('inventory_stocktakes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number', 50)->unique();
            $table->foreignUlid('department_id')->constrained('mst_org_departments')->restrictOnDelete();
            $table->foreignUlid('location_id')->constrained('mst_org_locations')->restrictOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('DRAFT');
            $table->timestamp('counted_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['department_id', 'status', 'created_at'], 'inventory_stocktakes_department_status_index');
        });

        Schema::create('inventory_stocktake_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('inventory_stocktake_id')->constrained('inventory_stocktakes')->restrictOnDelete();
            $table->foreignUlid('product_id')->constrained('mst_products')->restrictOnDelete();
            $table->decimal('snapshot_quantity', 15, 3);
            $table->jsonb('snapshot_serial_numbers')->nullable();
            $table->decimal('counted_quantity', 15, 3)->nullable();
            $table->jsonb('counted_serial_numbers')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['inventory_stocktake_id', 'product_id'], 'inventory_stocktake_item_product_unique');
        });

        Schema::table('inventory_document_items', function (Blueprint $table): void {
            $table->foreignUlid('inventory_stocktake_item_id')
                ->nullable()
                ->constrained('inventory_stocktake_items')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_document_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('inventory_stocktake_item_id');
        });

        Schema::dropIfExists('inventory_stocktake_items');
        Schema::dropIfExists('inventory_stocktakes');
        Schema::dropIfExists('inventory_stock_thresholds');
    }
};
