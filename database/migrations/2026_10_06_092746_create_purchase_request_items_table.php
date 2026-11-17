<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('purchase_request_id')
                ->constrained('purchase_requests')
                ->cascadeOnDelete();

            $table->foreignUlid('product_id')
                ->constrained('mst_products')
                ->restrictOnDelete();

            $table->text('description')->nullable();

            $table->decimal('quantity', 15, 3);

            $table->foreignUlid('uom_id')
                ->constrained('mst_units_of_measure')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('purchase_request_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
    }
};
