<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS inventory_one_default_warehouse_per_department');

        Schema::table('inventory_department_warehouses', function (Blueprint $table): void {
            $table->dropIndex(['department_id', 'is_default']);
            $table->dropColumn('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_department_warehouses', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false);
            $table->index(['department_id', 'is_default']);
        });

        DB::statement('CREATE UNIQUE INDEX inventory_one_default_warehouse_per_department ON inventory_department_warehouses (department_id) WHERE is_default = TRUE');
    }
};
