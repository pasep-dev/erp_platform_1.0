<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mst_asset_brands', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('code', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');

            $table->index([
                'is_active',
                'name',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_asset_brands');
    }
};
