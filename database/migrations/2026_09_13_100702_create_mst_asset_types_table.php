<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mst_asset_types', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('category_id')
                ->constrained('mst_asset_categories')
                ->restrictOnDelete();

            $table->string('code', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique([
                'category_id',
                'code',
            ]);

            $table->index([
                'category_id',
                'is_active',
            ]);

            $table->index([
                'name',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_asset_types');
    }
};
