<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('numbering_documents', function (Blueprint $table): void {
            $table->unsignedBigInteger('sequence_start')
                ->default(1)
                ->after('sequence_length');
        });
    }

    public function down(): void
    {
        Schema::table('numbering_documents', function (Blueprint $table): void {
            $table->dropColumn('sequence_start');
        });
    }
};
