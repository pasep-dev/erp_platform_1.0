<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $patterns = [
            'ASSET' => [
                '{COMPANY}-{ASSET_TYPE}{SEQ}{BRANCH}',
                '{COMPANY}-{ASSET_TYPE}-{DEPARTMENT}{SEQ}{BRANCH}',
            ],
            'INVENTORY_RECEIPT' => ['IMR-{YYYY}-{SEQ}', 'IMR-{DEPARTMENT}-{YYYY}-{SEQ}'],
            'INVENTORY_ISSUE' => ['GI-{YYYY}-{SEQ}', 'GI-{DEPARTMENT}-{YYYY}-{SEQ}'],
            'INVENTORY_RETURN' => ['RT-{YYYY}-{SEQ}', 'RT-{DEPARTMENT}-{YYYY}-{SEQ}'],
            'INVENTORY_ADJUSTMENT' => ['ADJ-{YYYY}-{SEQ}', 'ADJ-{DEPARTMENT}-{YYYY}-{SEQ}'],
            'INVENTORY_REQUEST' => ['IR-{YYYY}-{SEQ}', 'IR-{DEPARTMENT}-{YYYY}-{SEQ}'],
            'INVENTORY_STOCKTAKE' => ['SO-{YYYY}-{SEQ}', 'SO-{DEPARTMENT}-{YYYY}-{SEQ}'],
        ];

        foreach ($patterns as $code => [$oldPattern, $newPattern]) {
            DB::table('numbering_documents')
                ->where('code', $code)
                ->where('pattern', $oldPattern)
                ->update(['pattern' => $newPattern, 'updated_at' => now()]);
        }

        DB::table('numbering_modules')
            ->where('code', 'ASSET')
            ->where('default_pattern', '{COMPANY}-{ASSET_TYPE}{SEQ}{BRANCH}')
            ->update([
                'default_pattern' => '{COMPANY}-{ASSET_TYPE}-{DEPARTMENT}{SEQ}{BRANCH}',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $patterns = [
            'ASSET' => [
                '{COMPANY}-{ASSET_TYPE}-{DEPARTMENT}{SEQ}{BRANCH}',
                '{COMPANY}-{ASSET_TYPE}{SEQ}{BRANCH}',
            ],
            'INVENTORY_RECEIPT' => ['IMR-{DEPARTMENT}-{YYYY}-{SEQ}', 'IMR-{YYYY}-{SEQ}'],
            'INVENTORY_ISSUE' => ['GI-{DEPARTMENT}-{YYYY}-{SEQ}', 'GI-{YYYY}-{SEQ}'],
            'INVENTORY_RETURN' => ['RT-{DEPARTMENT}-{YYYY}-{SEQ}', 'RT-{YYYY}-{SEQ}'],
            'INVENTORY_ADJUSTMENT' => ['ADJ-{DEPARTMENT}-{YYYY}-{SEQ}', 'ADJ-{YYYY}-{SEQ}'],
            'INVENTORY_REQUEST' => ['IR-{DEPARTMENT}-{YYYY}-{SEQ}', 'IR-{YYYY}-{SEQ}'],
            'INVENTORY_STOCKTAKE' => ['SO-{DEPARTMENT}-{YYYY}-{SEQ}', 'SO-{YYYY}-{SEQ}'],
        ];

        foreach ($patterns as $code => [$newPattern, $oldPattern]) {
            DB::table('numbering_documents')
                ->where('code', $code)
                ->where('pattern', $newPattern)
                ->update(['pattern' => $oldPattern, 'updated_at' => now()]);
        }

        DB::table('numbering_modules')
            ->where('code', 'ASSET')
            ->where('default_pattern', '{COMPANY}-{ASSET_TYPE}-{DEPARTMENT}{SEQ}{BRANCH}')
            ->update([
                'default_pattern' => '{COMPANY}-{ASSET_TYPE}{SEQ}{BRANCH}',
                'updated_at' => now(),
            ]);
    }
};
