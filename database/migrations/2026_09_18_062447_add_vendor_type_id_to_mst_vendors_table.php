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
        Schema::table('mst_vendors', function (Blueprint $table) {
            $table->foreignUlid('vendor_type_id')
                ->nullable()
                ->after('id');

            $table->index([
                'vendor_type_id',
                'is_active',
            ]);
        });

        Schema::table('mst_vendors', function (Blueprint $table) {
            $table->foreign('vendor_type_id')
                ->references('id')
                ->on('mst_vendor_types')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mst_vendors', function (Blueprint $table) {
            $table->dropForeign([
                'vendor_type_id',
            ]);

            $table->dropIndex([
                'vendor_type_id',
                'is_active',
            ]);

            $table->dropColumn('vendor_type_id');
        });
    }
};
