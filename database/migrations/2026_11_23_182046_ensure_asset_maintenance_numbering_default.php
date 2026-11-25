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
                'code' => 'ASSET_MAINTENANCE',
                'name' => 'Asset Maintenance',
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
                'code' => 'ASSET_MAINTENANCE',
                'name' => 'Asset Maintenance',
                'pattern' => 'MNT/{YYYY}/{SEQ}',
                'sequence_length' => 4,
                'sequence_start' => 1,
                'reset_period' => 'yearly',
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
