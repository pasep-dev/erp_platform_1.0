<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mst_products', function (Blueprint $table): void {
            $table->foreignUlid('base_uom_id')
                ->nullable()
                ->after('inventory_tracking_mode')
                ->constrained('mst_units_of_measure')
                ->restrictOnDelete();
        });

        DB::table('mst_products')
            ->where('group', 'CONSUMABLE')
            ->whereNotNull('uom_id')
            ->update(['base_uom_id' => DB::raw('uom_id')]);
    }

    public function down(): void
    {
        Schema::table('mst_products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('base_uom_id');
        });
    }
};
