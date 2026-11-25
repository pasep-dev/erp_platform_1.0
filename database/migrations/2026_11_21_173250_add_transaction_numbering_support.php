<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table('numbering_modules')->insertOrIgnore(array_map(
            static fn (array $module): array => [
                ...$module,
                'default_pattern' => null,
                'sequence_length' => null,
                'reset_period' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                ['code' => 'INVENTORY_ISSUE', 'name' => 'Inventory Issue', 'category' => 'Inventory Management'],
                ['code' => 'INVENTORY_RETURN', 'name' => 'Inventory Return', 'category' => 'Inventory Management'],
                ['code' => 'INVENTORY_ADJUSTMENT', 'name' => 'Inventory Adjustment', 'category' => 'Inventory Management'],
                ['code' => 'INVENTORY_REQUEST', 'name' => 'Inventory Request', 'category' => 'Inventory Management'],
            ],
        ));

        DB::table('numbering_modules')
            ->where('code', 'INVENTORY_RECEIPT')
            ->update([
                'name' => 'Inventory Receipt',
                'category' => 'Inventory Management',
                'updated_at' => $now,
            ]);

        DB::table('numbering_documents')->insertOrIgnore(array_map(
            static fn (array $document): array => [
                'id' => (string) Str::ulid(),
                ...$document,
                'sequence_length' => 4,
                'sequence_start' => 1,
                'reset_period' => 'yearly',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                ['code' => 'ASSET_ASSIGNMENT', 'name' => 'Asset Assignment', 'pattern' => 'ASG-{YYYY}-{SEQ}'],
                ['code' => 'ASSET_AUDIT', 'name' => 'Asset Audit', 'pattern' => 'AUD-{YYYY}-{SEQ}'],
                ['code' => 'ASSET_INCIDENT', 'name' => 'Asset Incident', 'pattern' => 'INC-{YYYY}-{SEQ}'],
                ['code' => 'ASSET_TRANSFER', 'name' => 'Asset Transfer', 'pattern' => 'TRF-{YYYY}-{SEQ}'],
                ['code' => 'ASSET_DISPOSAL', 'name' => 'Asset Disposal', 'pattern' => 'DSP-{YYYY}-{SEQ}'],
                ['code' => 'INVENTORY_RECEIPT', 'name' => 'Inventory Receipt', 'pattern' => 'IMR-{YYYY}-{SEQ}'],
                ['code' => 'INVENTORY_ISSUE', 'name' => 'Inventory Issue', 'pattern' => 'GI-{YYYY}-{SEQ}'],
                ['code' => 'INVENTORY_RETURN', 'name' => 'Inventory Return', 'pattern' => 'RT-{YYYY}-{SEQ}'],
                ['code' => 'INVENTORY_ADJUSTMENT', 'name' => 'Inventory Adjustment', 'pattern' => 'ADJ-{YYYY}-{SEQ}'],
                ['code' => 'INVENTORY_REQUEST', 'name' => 'Inventory Request', 'pattern' => 'IR-{YYYY}-{SEQ}'],
                ['code' => 'INVENTORY_STOCKTAKE', 'name' => 'Inventory Stocktake', 'pattern' => 'SO-{YYYY}-{SEQ}'],
            ],
        ));

        Schema::table('asset_assignment_histories', function (Blueprint $table): void {
            $table->string('number', 100)->nullable()->unique();
        });

        DB::table('asset_assignment_histories')
            ->select(['id'])
            ->orderBy('id')
            ->chunk(500, function ($histories): void {
                foreach ($histories as $history) {
                    DB::table('asset_assignment_histories')
                        ->where('id', $history->id)
                        ->update([
                            'number' => 'LEGACY-ASG-'.Str::upper((string) $history->id),
                        ]);
                }
            });

        Schema::table('asset_assignment_histories', function (Blueprint $table): void {
            $table->string('number', 100)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_assignment_histories', function (Blueprint $table): void {
            $table->dropUnique('asset_assignment_histories_number_unique');
            $table->dropColumn('number');
        });

    }
};
