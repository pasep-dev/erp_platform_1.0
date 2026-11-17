<?php

namespace Database\Seeders;

use App\Models\NumberingDocument;
use Illuminate\Database\Seeder;

class MasterNumberingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            [
                'code' => NumberingDocument::CODE_VENDOR,
                'name' => 'Vendor Code',
                'pattern' => 'VND-{SEQ}',
            ],
            [
                'code' => NumberingDocument::CODE_VENDOR_TYPE,
                'name' => 'Vendor Type Code',
                'pattern' => 'VTYPE-{SEQ}',
            ],
            [
                'code' => NumberingDocument::CODE_PRODUCT,
                'name' => 'Product Code',
                'pattern' => 'PRD-{SEQ}',
            ],
        ] as $document) {
            NumberingDocument::query()->firstOrCreate(
                ['code' => $document['code']],
                [
                    ...$document,
                    'sequence_length' => 6,
                    'sequence_start' => 1,
                    'reset_period' => 'never',
                    'is_active' => true,
                ],
            );
        }
    }
}
