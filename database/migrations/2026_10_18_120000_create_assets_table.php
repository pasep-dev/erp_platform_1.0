<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number', 100)->unique();
            $table->string('registration_method', 20);
            $table->string('status', 20)->default('ACTIVE');
            $table->foreignUlid('product_id')->constrained('mst_products')->restrictOnDelete();
            $table->foreignUlid('category_id')->constrained('mst_asset_categories')->restrictOnDelete();
            $table->foreignUlid('type_id')->constrained('mst_asset_types')->restrictOnDelete();
            $table->foreignUlid('brand_id')->constrained('mst_asset_brands')->restrictOnDelete();
            $table->foreignUlid('model_id')->constrained('mst_asset_models')->restrictOnDelete();
            $table->foreignUlid('company_id')->constrained('mst_org_companies')->restrictOnDelete();
            $table->foreignUlid('department_id')->constrained('mst_org_departments')->restrictOnDelete();
            $table->foreignUlid('location_id')->constrained('mst_org_locations')->restrictOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained('mst_org_branches')->nullOnDelete();
            $table->foreignId('custodian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('goods_receipt_item_id')->nullable()->constrained('goods_receipt_items')->restrictOnDelete();
            $table->unsignedInteger('source_unit_number')->nullable();
            $table->string('serial_number', 150)->nullable()->unique();
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 18, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['goods_receipt_item_id', 'source_unit_number'], 'assets_source_unit_unique');
            $table->index(['company_id', 'department_id', 'status'], 'assets_org_status_index');
            $table->index(['product_id', 'status'], 'assets_product_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
