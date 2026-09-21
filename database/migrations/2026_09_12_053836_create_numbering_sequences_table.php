<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numbering_sequences', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('numbering_document_id')
                ->constrained('numbering_documents')
                ->cascadeOnDelete();

            $table->string('period_key', 20);

            $table->unsignedBigInteger('current_value')
                ->default(0);

            $table->timestamps();

            $table->unique([
                'numbering_document_id',
                'period_key',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numbering_sequences');
    }
};
