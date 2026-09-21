<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mst_vendors', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('code', 50);
            $table->string('name', 150);

            $table->string('tax_number', 100)->nullable();

            $table->string('email', 150)->nullable();
            $table->string('phone', 50)->nullable();

            $table->text('address')->nullable();
            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');

            $table->index(['name', 'is_active']);
            $table->index(['tax_number', 'is_active']);
            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_vendors');
    }
};
