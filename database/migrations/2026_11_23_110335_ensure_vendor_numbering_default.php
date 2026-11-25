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
                'code' => 'VENDOR',
                'name' => 'Vendor',
                'category' => 'Master Data',
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
                'code' => 'VENDOR',
                'name' => 'Vendor Code',
                'pattern' => 'VND-{SEQ}',
                'sequence_length' => 6,
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
