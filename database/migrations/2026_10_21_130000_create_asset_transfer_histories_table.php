<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_transfer_histories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number', 100)->unique();
            $table->foreignUlid('asset_id')->constrained('assets')->restrictOnDelete();
            $table->json('from_snapshot');
            $table->json('to_snapshot');
            $table->date('effective_at');
            $table->text('reason');
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'effective_at'], 'asset_transfer_asset_effective_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_transfer_histories');
    }
};
