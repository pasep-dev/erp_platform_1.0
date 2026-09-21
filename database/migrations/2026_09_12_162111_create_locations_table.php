<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mst_org_locations', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('company_id')
                ->constrained('mst_org_companies')
                ->restrictOnDelete();

            $table->foreignUlid('branch_id')
                ->nullable()
                ->constrained('mst_org_branches')
                ->nullOnDelete();

            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('parent_id', 26)->nullable();
            $table->string('location_type', 50)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
            $table->index('branch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_org_locations');
    }
};
