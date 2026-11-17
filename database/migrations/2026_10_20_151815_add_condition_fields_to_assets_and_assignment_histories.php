<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->string('condition', 30)->default('GOOD');
        });

        Schema::table('asset_assignment_histories', function (Blueprint $table): void {
            $table->string('from_condition', 30)->nullable();
            $table->string('to_condition', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('asset_assignment_histories', function (Blueprint $table): void {
            $table->dropColumn(['from_condition', 'to_condition']);
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn('condition');
        });
    }
};
