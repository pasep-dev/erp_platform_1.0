<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opt_purchase_request_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('purchase_request_id');

            $table->unsignedInteger('line_number');

            $table->text('item_description');

            $table->string('management_class', 30);

            $table->decimal('quantity', 15, 4);

            $table->ulid('uom_id');

            $table->decimal('estimated_unit_price', 18, 2)->nullable();
            $table->decimal('estimated_total', 18, 2)->nullable();

            $table->text('specification')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique([
                'purchase_request_id',
                'line_number',
            ]);

            $table->index([
                'management_class',
            ]);

            $table->foreign('purchase_request_id')
                ->references('id')
                ->on('opt_purchase_request')
                ->cascadeOnDelete();

            $table->foreign('uom_id')
                ->references('id')
                ->on('mst_units_of_measure')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opt_purchase_request_items');
    }
};
