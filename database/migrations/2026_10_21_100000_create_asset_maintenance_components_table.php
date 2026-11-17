<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_maintenance_components', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('asset_maintenance_record_id')
                ->constrained('asset_maintenance_records')
                ->cascadeOnDelete();
            $table->string('component_name', 150);
            $table->decimal('quantity', 12, 3);
            $table->decimal('estimated_unit_cost', 18, 2);
            $table->date('needed_at');
            $table->timestamps();

            $table->index(['asset_maintenance_record_id', 'needed_at'], 'asset_maintenance_components_needed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_maintenance_components');
    }
};
