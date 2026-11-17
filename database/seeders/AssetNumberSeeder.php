<?php

namespace Database\Seeders;

use App\Models\NumberingDocument;
use Illuminate\Database\Seeder;

class AssetNumberSeeder extends Seeder
{
    public function run(): void
    {
        NumberingDocument::query()->firstOrCreate(
            ['code' => NumberingDocument::CODE_ASSET],
            [
                'name' => 'Asset Number',
                'pattern' => '{COMPANY}-{ASSET_TYPE}{SEQ}{BRANCH}',
                'sequence_length' => 3,
                'reset_period' => 'never',
                'is_active' => true,
            ],
        );

        NumberingDocument::query()->firstOrCreate(
            ['code' => NumberingDocument::CODE_ASSET_MAINTENANCE],
            [
                'name' => 'Asset Maintenance',
                'pattern' => 'MNT/{YYYY}/{SEQ}',
                'sequence_length' => 4,
                'reset_period' => 'yearly',
                'is_active' => true,
            ],
        );
    }
}
