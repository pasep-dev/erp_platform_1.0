<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mst_service_types', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('code', 50);
            $table->string('name', 150);

            $table->boolean('requires_asset')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique('code', 'mst_service_types_code_unique');
            $table->index(
                ['is_active', 'requires_asset'],
                'mst_service_types_active_asset_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_service_types');
    }
};
