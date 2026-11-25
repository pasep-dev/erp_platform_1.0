<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mst_prc_taxes', function (Blueprint $table): void {
            $table->string('category', 20)->default('ADDITIVE')->after('rate');
        });

        DB::table('mst_prc_taxes')
            ->where(function ($query): void {
                $query->whereRaw('LOWER(code) LIKE ?', ['%pph%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%pph%']);
            })
            ->update(['category' => 'WITHHOLDING']);

        Schema::table('trx_purchase_request_items', function (Blueprint $table): void {
            $table->foreignUlid('tax_id')->nullable()->after('tax_included')->constrained('mst_prc_taxes')->restrictOnDelete();
            $table->string('tax_code', 50)->nullable()->after('tax_id');
            $table->string('tax_name', 150)->nullable()->after('tax_code');
            $table->decimal('tax_rate', 8, 4)->nullable()->after('tax_name');
            $table->string('tax_category', 20)->nullable()->after('tax_rate');
        });

        Schema::table('trx_purchase_order_items', function (Blueprint $table): void {
            $table->foreignUlid('tax_id')->nullable()->after('tax_included')->constrained('mst_prc_taxes')->restrictOnDelete();
            $table->string('tax_code', 50)->nullable()->after('tax_id');
            $table->string('tax_name', 150)->nullable()->after('tax_code');
            $table->decimal('tax_rate', 8, 4)->nullable()->after('tax_name');
            $table->string('tax_category', 20)->nullable()->after('tax_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_purchase_order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tax_id');
            $table->dropColumn(['tax_code', 'tax_name', 'tax_rate', 'tax_category']);
        });

        Schema::table('trx_purchase_request_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tax_id');
            $table->dropColumn(['tax_code', 'tax_name', 'tax_rate', 'tax_category']);
        });

        Schema::table('mst_prc_taxes', function (Blueprint $table): void {
            $table->dropColumn('category');
        });
    }
};
