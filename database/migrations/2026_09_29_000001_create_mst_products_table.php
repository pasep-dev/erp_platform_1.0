<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mst_products')) {
            Schema::table('mst_products', function (Blueprint $table) {
                if (! Schema::hasColumn('mst_products', 'category_id')) {
                    $table->foreignUlid('category_id')
                        ->nullable()
                        ->constrained('mst_asset_categories')
                        ->restrictOnDelete();
                }

                if (! Schema::hasColumn('mst_products', 'type_id')) {
                    $table->foreignUlid('type_id')
                        ->nullable()
                        ->constrained('mst_asset_types')
                        ->restrictOnDelete();
                }

                if (! Schema::hasColumn('mst_products', 'brand_id')) {
                    $table->foreignUlid('brand_id')
                        ->nullable()
                        ->constrained('mst_asset_brands')
                        ->restrictOnDelete();
                }

                if (! Schema::hasColumn('mst_products', 'model_id')) {
                    $table->foreignUlid('model_id')
                        ->nullable()
                        ->constrained('mst_asset_models')
                        ->restrictOnDelete();
                }

                if (! Schema::hasColumn('mst_products', 'group')) {
                    $table->string('group', 30)->nullable();
                }

                if (! Schema::hasColumn('mst_products', 'code')) {
                    $table->string('code', 50)->nullable();
                }

                if (! Schema::hasColumn('mst_products', 'name')) {
                    $table->string('name', 150)->nullable();
                }

                if (! Schema::hasColumn('mst_products', 'description')) {
                    $table->text('description')->nullable();
                }

                if (! Schema::hasColumn('mst_products', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
            });

            return;
        }

        Schema::create('mst_products', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('category_id')
                ->constrained('mst_asset_categories')
                ->restrictOnDelete();

            $table->foreignUlid('type_id')
                ->constrained('mst_asset_types')
                ->restrictOnDelete();

            $table->foreignUlid('brand_id')
                ->constrained('mst_asset_brands')
                ->restrictOnDelete();

            $table->foreignUlid('model_id')
                ->constrained('mst_asset_models')
                ->restrictOnDelete();

            $table->string('group', 30);
            $table->string('code', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');

            $table->index(['category_id', 'is_active']);
            $table->index(['type_id', 'is_active']);
            $table->index(['brand_id', 'is_active']);
            $table->index(['model_id', 'is_active']);
            $table->index(['group', 'is_active']);
            $table->index(['name', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_products');
    }
};
