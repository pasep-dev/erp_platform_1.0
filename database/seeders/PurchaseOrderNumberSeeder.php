<?php

namespace Database\Seeders;

use App\Models\NumberingDocument;
use Illuminate\Database\Seeder;

class PurchaseOrderNumberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        NumberingDocument::query()->firstOrCreate(
            ['code' => NumberingDocument::CODE_PURCHASE_ORDER],
            [
                'name' => 'Purchase Order',
                'pattern' => '{COMPANY}/{DOCUMENT_TYPE}/{DEPARTMENT}/{YYYY}/{SEQ}',
                'sequence_length' => 3,
                'reset_period' => 'yearly',
                'is_active' => true,
            ],
        );
    }
}
