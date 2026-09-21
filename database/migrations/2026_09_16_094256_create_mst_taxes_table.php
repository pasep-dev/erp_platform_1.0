<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mst_taxes', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('code', 50);
            $table->string('name', 150);
            $table->decimal('rate', 8, 4)->default(0);

            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');

            $table->index(['name', 'is_active']);
            $table->index(['rate', 'is_active']);
            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_taxes');
    }
};
