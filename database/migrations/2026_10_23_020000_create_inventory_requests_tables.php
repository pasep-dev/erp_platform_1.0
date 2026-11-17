<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number', 50)->unique();
            $table->foreignUlid('department_id')->constrained('mst_org_departments')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('approval_request_id')->nullable()->unique()->constrained('approval_requests')->restrictOnDelete();
            $table->string('status', 30)->default('DRAFT');
            $table->date('requested_at');
            $table->date('required_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status', 'created_at'], 'inventory_requests_department_status_index');
            $table->index(['requested_by', 'created_at'], 'inventory_requests_requester_index');
        });

        Schema::create('inventory_request_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('inventory_request_id')->constrained('inventory_requests')->restrictOnDelete();
            $table->foreignUlid('product_id')->constrained('mst_products')->restrictOnDelete();
            $table->decimal('requested_quantity', 15, 3);
            $table->decimal('fulfilled_quantity', 15, 3)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['inventory_request_id', 'product_id'], 'inventory_request_items_product_index');
        });

        Schema::table('inventory_document_items', function (Blueprint $table): void {
            $table->foreignUlid('inventory_request_item_id')
                ->nullable()
                ->constrained('inventory_request_items')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_document_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('inventory_request_item_id');
        });

        Schema::dropIfExists('inventory_request_items');
        Schema::dropIfExists('inventory_requests');
    }
};
