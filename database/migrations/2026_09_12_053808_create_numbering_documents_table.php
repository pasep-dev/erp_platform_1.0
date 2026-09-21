<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numbering_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('code', 50)->unique();
            $table->string('name', 150);

            $table->string('pattern', 255);

            $table->unsignedSmallInteger('sequence_length')
                ->default(6);

            $table->string('reset_period', 20)
                ->default('yearly');

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numbering_documents');
    }
};
