<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mst_products', function (Blueprint $table) {
            $table->string('management_class', 30)
                ->nullable()
                ->change();

            $table->foreignUlid('uom_id')
                ->nullable()
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mst_products', function (Blueprint $table) {
            $table->string('management_class', 30)
                ->nullable(false)
                ->change();

            $table->foreignUlid('uom_id')
                ->nullable(false)
                ->change();
        });
    }
};
