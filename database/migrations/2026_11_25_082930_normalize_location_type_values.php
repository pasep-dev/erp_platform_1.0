<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('mst_org_locations')
            ->whereRaw('LOWER(location_type) = ?', ['floor'])
            ->update(['location_type' => 'FLOOR']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('mst_org_locations')
            ->where('location_type', 'FLOOR')
            ->update(['location_type' => 'Floor']);
    }
};
