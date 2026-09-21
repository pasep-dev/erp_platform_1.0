<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mst_asset_categories', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('mst_asset_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mst_asset_categories', function (Blueprint $table) {
            $table->dropForeign([
                'parent_id',
            ]);
        });
    }
};
