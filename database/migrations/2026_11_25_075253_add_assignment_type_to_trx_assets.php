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
        Schema::table('trx_assets', function (Blueprint $table): void {
            $table->string('assignment_type', 20)->default('LOCATION')->after('custodian_id');
        });

        DB::table('trx_assets')
            ->whereNotNull('custodian_id')
            ->update(['assignment_type' => 'USER']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_assets', function (Blueprint $table): void {
            $table->dropColumn('assignment_type');
        });
    }
};
