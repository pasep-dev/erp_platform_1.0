<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_item_price_histories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('purchase_order_item_id')
                ->constrained('purchase_order_items')
                ->restrictOnDelete();
            $table->decimal('previous_unit_price', 18, 2)->nullable();
            $table->decimal('new_unit_price', 18, 2);
            $table->text('reason');
            $table->foreignId('changed_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(
                ['purchase_order_item_id', 'created_at'],
                'po_price_histories_item_created_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_item_price_histories');
    }
};
