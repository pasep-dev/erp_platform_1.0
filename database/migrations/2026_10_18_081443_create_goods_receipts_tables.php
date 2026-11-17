<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number', 50)->unique();
            $table->string('source_type', 30);
            $table->foreignUlid('purchase_order_id')
                ->nullable()
                ->constrained('purchase_orders')
                ->restrictOnDelete();
            $table->foreignUlid('purchase_request_id')
                ->nullable()
                ->constrained('purchase_requests')
                ->restrictOnDelete();
            $table->foreignUlid('company_id')
                ->constrained('mst_org_companies')
                ->restrictOnDelete();
            $table->foreignUlid('department_id')
                ->constrained('mst_org_departments')
                ->restrictOnDelete();
            $table->foreignUlid('vendor_id')
                ->nullable()
                ->constrained('mst_vendors')
                ->restrictOnDelete();
            $table->date('received_at');
            $table->string('delivery_note_number', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'status'], 'goods_receipts_source_status_index');
            $table->index(['company_id', 'department_id'], 'goods_receipts_organization_index');
        });

        Schema::create('goods_receipt_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('goods_receipt_id')
                ->constrained('goods_receipts')
                ->cascadeOnDelete();
            $table->foreignUlid('purchase_order_item_id')
                ->nullable()
                ->constrained('purchase_order_items')
                ->restrictOnDelete();
            $table->foreignUlid('purchase_request_item_id')
                ->nullable()
                ->constrained('purchase_request_items')
                ->restrictOnDelete();
            $table->decimal('quantity_received', 15, 3);
            $table->decimal('quantity_accepted', 15, 3);
            $table->decimal('quantity_rejected', 15, 3)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_item_id', 'goods_receipt_id'], 'goods_receipt_items_po_source_index');
            $table->index(['purchase_request_item_id', 'goods_receipt_id'], 'goods_receipt_items_pr_source_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
    }
};
