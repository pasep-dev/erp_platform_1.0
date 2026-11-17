<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            /*
             * PRODUCT / SERVICE
             */
            $table->string('item_type', 20)
                ->default('PRODUCT')
                ->after('purchase_request_id');

            /*
             * PRODUCT hanya digunakan untuk PRODUCT item.
             */
            $table->foreignUlid('product_id')
                ->nullable()
                ->change();

            /*
             * SERVICE
             */
            $table->foreignUlid('service_type_id')
                ->nullable()
                ->after('product_id')
                ->constrained('mst_service_types')
                ->restrictOnDelete();

            /*
             * Asset yang menjadi objek service.
             *
             * Belum menggunakan FK karena tabel asset transaksional
             * belum tersedia.
             */
            $table->ulid('asset_id')
                ->nullable()
                ->after('service_type_id');

            /*
             * Deskripsi sudah tersedia dari migration awal.
             */

            /*
             * Procurement / Vendor
             */
            $table->foreignUlid('vendor_id')
                ->nullable()
                ->after('uom_id')
                ->constrained('mst_vendors')
                ->restrictOnDelete();

            $table->string('vendor_pic', 150)
                ->nullable()
                ->after('vendor_id');

            $table->string('vendor_contact', 100)
                ->nullable()
                ->after('vendor_pic');

            $table->string('vendor_url', 500)
                ->nullable()
                ->after('vendor_contact');

            /*
             * Procurement decision
             */
            $table->boolean('requires_po')
                ->default(false)
                ->after('vendor_url');

            $table->boolean('tax_included')
                ->default(false)
                ->after('requires_po');

            /*
             * Procurement details
             */
            $table->date('required_date')
                ->nullable()
                ->after('tax_included');

            $table->string('delivery_address', 500)
                ->nullable()
                ->after('required_date');

            $table->text('remarks')
                ->nullable()
                ->after('delivery_address');

            /*
             * Estimasi harga.
             *
             * Nullable karena draft boleh belum lengkap.
             */
            $table->decimal('estimated_unit_price', 18, 2)
                ->nullable()
                ->after('remarks');

            /*
             * Index
             */
            $table->index(
                ['item_type', 'service_type_id'],
                'purchase_request_items_type_service_index'
            );

            $table->index(
                'asset_id',
                'purchase_request_items_asset_index'
            );

            $table->index(
                'requires_po',
                'purchase_request_items_requires_po_index'
            );

            $table->index(
                'vendor_id',
                'purchase_request_items_vendor_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->dropIndex(
                'purchase_request_items_type_service_index'
            );

            $table->dropIndex(
                'purchase_request_items_asset_index'
            );

            $table->dropIndex(
                'purchase_request_items_requires_po_index'
            );

            $table->dropIndex(
                'purchase_request_items_vendor_index'
            );

            $table->dropForeign([
                'service_type_id',
            ]);

            $table->dropForeign([
                'vendor_id',
            ]);

            $table->dropColumn([
                'item_type',
                'service_type_id',
                'asset_id',
                'vendor_pic',
                'vendor_contact',
                'vendor_url',
                'requires_po',
                'tax_included',
                'required_date',
                'delivery_address',
                'remarks',
                'estimated_unit_price',
            ]);
        });
    }
};
