<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numbering_modules', function (Blueprint $table) {
            $table->string('code', 50)->primary();
            $table->string('name', 150);
            $table->string('category', 100);
            $table->string('default_pattern', 255);
            $table->unsignedSmallInteger('sequence_length');
            $table->string('reset_period', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'category']);
        });

        DB::table('numbering_modules')->insert([
            [
                'code' => 'VENDOR',
                'name' => 'Vendor',
                'category' => 'Master Data',
                'default_pattern' => 'VND-{SEQ}',
                'sequence_length' => 6,
                'reset_period' => 'never',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'VENDOR_TYPE',
                'name' => 'Tipe Vendor',
                'category' => 'Master Data',
                'default_pattern' => 'VTYPE-{SEQ}',
                'sequence_length' => 6,
                'reset_period' => 'never',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'PRODUCT',
                'name' => 'Produk',
                'category' => 'Master Data',
                'default_pattern' => 'PRD-{SEQ}',
                'sequence_length' => 6,
                'reset_period' => 'never',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'ASSET',
                'name' => 'Registrasi Aset',
                'category' => 'Asset Management',
                'default_pattern' => '{COMPANY}-{ASSET_TYPE}-{DEPARTMENT}{SEQ}{BRANCH}',
                'sequence_length' => 3,
                'reset_period' => 'never',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'ASSET_MAINTENANCE',
                'name' => 'Pemeliharaan Aset',
                'category' => 'Asset Management',
                'default_pattern' => 'MNT/{YYYY}/{SEQ}',
                'sequence_length' => 4,
                'reset_period' => 'yearly',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'PURCHASE_REQUEST',
                'name' => 'Purchase Request',
                'category' => 'Procurement',
                'default_pattern' => '{COMPANY}/{DOCUMENT_TYPE}/{DEPARTMENT}/{YYYY}/{SEQ}',
                'sequence_length' => 3,
                'reset_period' => 'yearly',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'PURCHASE_ORDER',
                'name' => 'Purchase Order',
                'category' => 'Procurement',
                'default_pattern' => '{COMPANY}/{DOCUMENT_TYPE}/{DEPARTMENT}/{YYYY}/{SEQ}',
                'sequence_length' => 3,
                'reset_period' => 'yearly',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'GOODS_RECEIPT',
                'name' => 'Goods Receipt',
                'category' => 'Procurement',
                'default_pattern' => '{COMPANY}/{DOCUMENT_TYPE}/{DEPARTMENT}/{YYYY}/{SEQ}',
                'sequence_length' => 3,
                'reset_period' => 'yearly',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('numbering_modules');
    }
};
