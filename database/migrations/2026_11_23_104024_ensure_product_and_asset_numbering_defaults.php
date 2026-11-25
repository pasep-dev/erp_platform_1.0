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

        DB::table('numbering_modules')->insertOrIgnore([
            [
                'code' => 'PRODUCT',
                'name' => 'Product Master',
                'category' => 'Master Data',
                'default_pattern' => null,
                'sequence_length' => null,
                'reset_period' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'ASSET',
                'name' => 'Asset Registration',
                'category' => 'Asset Management',
                'default_pattern' => null,
                'sequence_length' => null,
                'reset_period' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('numbering_documents')->insertOrIgnore([
            [
                'id' => (string) Str::ulid(),
                'code' => 'PRODUCT',
                'name' => 'Product Code',
                'pattern' => 'PRD-{SEQ}',
                'sequence_length' => 6,
                'sequence_start' => 1,
                'reset_period' => 'never',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'code' => 'ASSET',
                'name' => 'Asset Number',
                'pattern' => '{COMPANY}-{ASSET_TYPE}{SEQ}{BRANCH}',
                'sequence_length' => 3,
                'sequence_start' => 1,
                'reset_period' => 'never',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep administrator-managed numbering settings when rolling back.
    }
};
