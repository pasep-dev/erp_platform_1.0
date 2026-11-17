<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('purchase_order_id')
                ->constrained('purchase_orders')
                ->cascadeOnDelete();
            $table->foreignUlid('purchase_request_item_id')
                ->constrained('purchase_request_items')
                ->restrictOnDelete();
            $table->string('item_type', 20);
            $table->foreignUlid('product_id')
                ->nullable()
                ->constrained('mst_products')
                ->restrictOnDelete();
            $table->foreignUlid('service_type_id')
                ->nullable()
                ->constrained('mst_service_types')
                ->restrictOnDelete();
            $table->ulid('asset_id')->nullable();
            $table->text('description')->nullable();
            $table->decimal('quantity', 15, 3);
            $table->foreignUlid('uom_id')
                ->constrained('mst_units_of_measure')
                ->restrictOnDelete();
            $table->decimal('pr_estimated_unit_price', 18, 2)->nullable();
            $table->decimal('unit_price', 18, 2)->nullable();
            $table->boolean('tax_included')->default(false);
            $table->date('required_date')->nullable();
            $table->string('delivery_address', 500)->nullable();
            $table->string('vendor_pic', 150)->nullable();
            $table->string('vendor_contact', 100)->nullable();
            $table->string('vendor_url', 500)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique('purchase_request_item_id', 'purchase_order_items_request_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
