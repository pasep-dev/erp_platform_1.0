<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mst_asset_models', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('brand_id')
                ->constrained('mst_asset_brands')
                ->restrictOnDelete();

            $table->foreignUlid('type_id')
                ->constrained('mst_asset_types')
                ->restrictOnDelete();

            $table->string('code', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');

            $table->index(['brand_id', 'is_active']);
            $table->index(['type_id', 'is_active']);
            $table->index(['name', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_asset_models');
    }
};
