<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('numbering_modules', function (Blueprint $table): void {
            $table->string('default_pattern', 255)->nullable()->change();
            $table->unsignedSmallInteger('sequence_length')->nullable()->change();
            $table->string('reset_period', 20)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('numbering_modules')
            ->whereNull('default_pattern')
            ->update([
                'default_pattern' => '{SEQ}',
                'sequence_length' => 6,
                'reset_period' => 'never',
            ]);

        DB::table('numbering_modules')
            ->whereNull('sequence_length')
            ->update(['sequence_length' => 6]);

        DB::table('numbering_modules')
            ->whereNull('reset_period')
            ->update(['reset_period' => 'never']);

        Schema::table('numbering_modules', function (Blueprint $table): void {
            $table->string('default_pattern', 255)->nullable(false)->change();
            $table->unsignedSmallInteger('sequence_length')->nullable(false)->change();
            $table->string('reset_period', 20)->nullable(false)->change();
        });
    }
};
