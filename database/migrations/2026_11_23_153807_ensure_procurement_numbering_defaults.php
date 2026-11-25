<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $modules = [
            ['code' => 'PURCHASE_REQUEST', 'name' => 'Purchase Request'],
            ['code' => 'PURCHASE_ORDER', 'name' => 'Purchase Order'],
            ['code' => 'GOODS_RECEIPT', 'name' => 'Goods Receipt'],
        ];

        DB::table('numbering_modules')->insertOrIgnore(array_map(
            static fn (array $module): array => [
                ...$module,
                'category' => 'Procurement',
                'default_pattern' => null,
                'sequence_length' => null,
                'reset_period' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $modules,
        ));

        $patterns = [
            'PURCHASE_REQUEST' => 'Purchase Request',
            'PURCHASE_ORDER' => 'Purchase Order',
            'GOODS_RECEIPT' => 'Goods Receipt',
        ];

        DB::table('numbering_documents')->insertOrIgnore(array_map(
            static fn (string $code, string $name): array => [
                'id' => (string) Str::ulid(),
                'code' => $code,
                'name' => $name,
                'pattern' => '{COMPANY}/{DOCUMENT_TYPE}/{DEPARTMENT}/{YYYY}/{SEQ}',
                'sequence_length' => 3,
                'sequence_start' => 1,
                'reset_period' => 'yearly',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            array_keys($patterns),
            array_values($patterns),
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep administrator-managed numbering settings when rolling back.
    }
};
