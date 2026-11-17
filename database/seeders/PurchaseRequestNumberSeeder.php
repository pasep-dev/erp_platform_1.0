<?php

namespace Database\Seeders;

use App\Models\NumberingDocument;
use Illuminate\Database\Seeder;

class PurchaseRequestNumberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        NumberingDocument::firstOrCreate(
            [
                'code' => NumberingDocument::CODE_PURCHASE_REQUEST,
            ],
            [
                'name' => 'Purchase Request Number',
                'pattern' => '{COMPANY}/PR/{YYYY}/{SEQ}',
                'sequence_length' => 6,
                'reset_period' => 'yearly',
                'is_active' => true,
            ],
        );
    }
}
