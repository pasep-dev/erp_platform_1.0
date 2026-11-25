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
                'code' => NumberingDocument::CODE_ASSET,
                'name' => 'Asset Number',
                'pattern' => '{COMPANY}-{ASSET_TYPE}-{DEPARTMENT}{SEQ}{BRANCH}',
                'sequence_length' => 3,
                'reset_period' => 'never',
                'is_active' => true,
            ],
            [
                'code' => NumberingDocument::CODE_PURCHASE_REQUEST,
                'name' => 'Purchase Request',
                'pattern' => '{COMPANY}/{DOCUMENT_TYPE}/{DEPARTMENT}/{YYYY}/{SEQ}',
                'sequence_length' => 3,
                'reset_period' => 'yearly',
                'is_active' => true,
            ],
            [
                'code' => NumberingDocument::CODE_PURCHASE_ORDER,
                'name' => 'Purchase Order',
                'pattern' => '{COMPANY}/{DOCUMENT_TYPE}/{DEPARTMENT}/{YYYY}/{SEQ}',
                'sequence_length' => 3,
                'reset_period' => 'yearly',
                'is_active' => true,
            ],
            [
                'code' => NumberingDocument::CODE_GOODS_RECEIPT,
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
