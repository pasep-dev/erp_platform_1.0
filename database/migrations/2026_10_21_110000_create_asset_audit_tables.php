<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_audit_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number', 100)->unique();
            $table->foreignUlid('company_id')->nullable()->constrained('mst_org_companies')->nullOnDelete();
            $table->foreignUlid('department_id')->nullable()->constrained('mst_org_departments')->nullOnDelete();
            $table->foreignUlid('location_id')->nullable()->constrained('mst_org_locations')->nullOnDelete();
            $table->foreignUlid('category_id')->nullable()->constrained('mst_asset_categories')->nullOnDelete();
            $table->json('scope_snapshot');
            $table->string('status', 20)->default('IN_PROGRESS');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'started_at'], 'asset_audit_status_started_index');
        });

        Schema::create('asset_audit_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('asset_audit_session_id')->constrained('asset_audit_sessions')->cascadeOnDelete();
            $table->foreignUlid('asset_id')->constrained('assets')->restrictOnDelete();
            $table->json('expected_snapshot');
            $table->string('result', 20)->default('PENDING');
            $table->foreignUlid('observed_location_id')->nullable()->constrained('mst_org_locations')->nullOnDelete();
            $table->string('observed_condition', 30)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->text('resolution_remarks')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['asset_audit_session_id', 'asset_id'], 'asset_audit_item_session_asset_unique');
            $table->index(['asset_audit_session_id', 'result'], 'asset_audit_item_session_result_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_audit_items');
        Schema::dropIfExists('asset_audit_sessions');
    }
};
