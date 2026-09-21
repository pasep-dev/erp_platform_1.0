<?php

namespace Database\Seeders;

use App\Models\NumberingDocument;
use Illuminate\Database\Seeder;

class NumberingSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            [
                'code' => 'ASSET',
                'name' => 'Asset Number',
                'pattern' => '{COMPANY}-{ASSET_TYPE}{SEQ}{BRANCH}',
                'sequence_length' => 3,
                'reset_period' => 'never',
                'is_active' => true,
            ],
            [
                'code' => 'PURCHASE_REQUEST',
                'name' => 'Purchase Request',
                'pattern' => '{COMPANY}/{DOCUMENT_TYPE}/{DEPARTMENT}/{YYYY}/{SEQ}',
                'sequence_length' => 3,
                'reset_period' => 'yearly',
                'is_active' => true,
            ],
            [
                'code' => 'PURCHASE_ORDER',
                'name' => 'Purchase Order',
                'pattern' => '{COMPANY}/{DOCUMENT_TYPE}/{DEPARTMENT}/{YYYY}/{SEQ}',
                'sequence_length' => 3,
                'reset_period' => 'yearly',
                'is_active' => true,
            ],
            [
                'code' => 'GOODS_RECEIPT',
                'name' => 'Goods Receipt',
                'pattern' => '{COMPANY}/{DOCUMENT_TYPE}/{DEPARTMENT}/{YYYY}/{SEQ}',
                'sequence_length' => 3,
                'reset_period' => 'yearly',
                'is_active' => true,
            ],
        ];

        foreach ($documents as $document) {
            NumberingDocument::updateOrCreate(
                ['code' => $document['code']],
                $document,
            );
        }
    }
}
