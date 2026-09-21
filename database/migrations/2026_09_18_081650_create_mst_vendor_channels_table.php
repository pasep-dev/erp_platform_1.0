<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mst_vendor_channels', function (Blueprint $table) {
            $table->ulid('vendor_id');
            $table->ulid('procurement_channel_id');
            $table->timestamps();

            $table->primary([
                'vendor_id',
                'procurement_channel_id',
            ]);

            $table->foreign('vendor_id')
                ->references('id')
                ->on('mst_vendors')
                ->cascadeOnDelete();

            $table->foreign('procurement_channel_id')
                ->references('id')
                ->on('mst_procurement_channels')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_vendor_channels');
    }
};
