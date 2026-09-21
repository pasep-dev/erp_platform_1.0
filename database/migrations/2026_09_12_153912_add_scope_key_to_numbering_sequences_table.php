<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('numbering_sequences', function (Blueprint $table): void {
            $table->string('scope_key', 150)
                ->default('global')
                ->after('numbering_document_id');
        });

        Schema::table('numbering_sequences', function (Blueprint $table): void {
            $table->dropUnique([
                'numbering_document_id',
                'period_key',
            ]);

            $table->unique([
                'numbering_document_id',
                'scope_key',
                'period_key',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('numbering_sequences', function (Blueprint $table): void {
            $table->dropUnique([
                'numbering_document_id',
                'scope_key',
                'period_key',
            ]);

            $table->unique([
                'numbering_document_id',
                'period_key',
            ]);

            $table->dropColumn('scope_key');
        });
    }
};
