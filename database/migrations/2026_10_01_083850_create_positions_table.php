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
        Schema::create('mst_org_positions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('company_id')
                ->constrained('mst_org_companies')
                ->restrictOnDelete();

            $table->string('code');
            $table->string('name');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['company_id', 'code'],
                'mst_org_positions_company_code_unique'
            );

            $table->index(
                ['company_id', 'is_active'],
                'mst_org_positions_company_active_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_org_positions');
    }
};
