<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (
            ! Schema::hasTable('mst_products')
            || Schema::hasColumn('mst_products', 'base_uom_id')
        ) {
            return;
        }

        Schema::table('mst_products', function (Blueprint $table): void {
            $table->foreignUlid('base_uom_id')
                ->nullable()
                ->constrained('mst_units_of_measure')
                ->restrictOnDelete();
        });

        if (Schema::hasColumn('mst_products', 'uom_id')) {
            DB::table('mst_products')
                ->where('group', 'CONSUMABLE')
                ->whereNotNull('uom_id')
                ->update(['base_uom_id' => DB::raw('uom_id')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The column may have been created by the earlier product migration.
    }
};
