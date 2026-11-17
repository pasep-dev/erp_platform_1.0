<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_maintenance_records', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number', 100)->unique();
            $table->foreignUlid('asset_id')->constrained('assets')->restrictOnDelete();
            $table->string('maintenance_type', 20);
            $table->string('priority', 20)->default('NORMAL');
            $table->string('status', 20)->default('REQUESTED');
            $table->date('requested_at');
            $table->text('issue_description');
            $table->string('reported_condition', 30);
            $table->text('inspection_result')->nullable();
            $table->foreignUlid('vendor_id')->nullable()->constrained('mst_vendors')->nullOnDelete();
            $table->decimal('estimated_cost', 18, 2)->nullable();
            $table->date('scheduled_at')->nullable();
            $table->date('started_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->text('work_summary')->nullable();
            $table->string('completed_condition', 30)->nullable();
            $table->decimal('actual_cost', 18, 2)->nullable();
            $table->date('next_service_date')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'status'], 'asset_maintenance_asset_status_index');
            $table->index(['status', 'requested_at'], 'asset_maintenance_status_requested_index');
            $table->index('next_service_date', 'asset_maintenance_next_service_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_maintenance_records');
    }
};
