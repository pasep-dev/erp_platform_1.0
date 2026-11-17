<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_assignment_histories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('asset_id')->constrained('assets')->restrictOnDelete();
            $table->string('event_type', 20);
            $table->foreignId('from_custodian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_custodian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('from_snapshot')->nullable();
            $table->json('to_snapshot')->nullable();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->date('effective_at');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'effective_at'], 'asset_assignment_asset_effective_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignment_histories');
    }
};
