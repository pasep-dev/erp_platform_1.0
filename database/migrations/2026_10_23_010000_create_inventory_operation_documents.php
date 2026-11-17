<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number', 50)->unique();
            $table->foreignUlid('department_id')->constrained('mst_org_departments')->restrictOnDelete();
            $table->string('document_type', 20);
            $table->string('status', 20)->default('POSTED');
            $table->foreignUlid('source_location_id')->nullable()->constrained('mst_org_locations')->restrictOnDelete();
            $table->foreignUlid('target_location_id')->nullable()->constrained('mst_org_locations')->restrictOnDelete();
            $table->string('adjustment_direction', 3)->nullable();
            $table->timestamp('occurred_at');
            $table->text('remarks')->nullable();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['department_id', 'document_type', 'occurred_at'], 'inventory_documents_department_type_date_index');
        });

        Schema::create('inventory_document_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('inventory_document_id')->constrained('inventory_documents')->restrictOnDelete();
            $table->foreignUlid('product_id')->constrained('mst_products')->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->json('serial_numbers')->nullable();
            $table->string('party_type', 20)->nullable();
            $table->foreignId('party_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignUlid('party_asset_id')->nullable()->constrained('assets')->restrictOnDelete();
            $table->foreignUlid('party_location_id')->nullable()->constrained('mst_org_locations')->restrictOnDelete();
            $table->ulid('related_issue_item_id')->nullable();
            $table->json('specification_changes')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['inventory_document_id', 'product_id'], 'inventory_document_items_product_index');
        });

        Schema::table('inventory_document_items', function (Blueprint $table): void {
            $table->foreign('related_issue_item_id')
                ->references('id')
                ->on('inventory_document_items')
                ->restrictOnDelete();
        });

        Schema::table('inventory_stock_movements', function (Blueprint $table): void {
            $table->foreignUlid('inventory_document_item_id')
                ->nullable()
                ->after('source_line_id')
                ->constrained('inventory_document_items')
                ->restrictOnDelete();
            $table->foreignUlid('related_issue_item_id')
                ->nullable()
                ->after('inventory_document_item_id')
                ->constrained('inventory_document_items')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('related_issue_item_id');
            $table->dropConstrainedForeignId('inventory_document_item_id');
        });

        Schema::dropIfExists('inventory_document_items');
        Schema::dropIfExists('inventory_documents');
    }
};
