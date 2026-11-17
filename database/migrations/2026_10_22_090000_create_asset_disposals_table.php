<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_disposals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number', 100)->unique();
            $table->foreignUlid('asset_id')->constrained('assets')->restrictOnDelete();
            $table->string('disposal_type', 20);
            $table->string('status', 30)->default('PENDING_APPROVAL');
            $table->date('disposal_date');
            $table->decimal('amount', 18, 2)->nullable();
            $table->text('reason');
            $table->json('asset_snapshot');
            $table->string('evidence_path')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'disposal_date'], 'asset_disposal_status_date_index');
            $table->index(['asset_id', 'status'], 'asset_disposal_asset_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_disposals');
    }
};
