<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_incidents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number', 100)->unique();
            $table->foreignUlid('asset_id')->constrained('assets')->restrictOnDelete();
            $table->string('incident_type', 20);
            $table->string('severity', 20)->default('NORMAL');
            $table->string('status', 20)->default('REPORTED');
            $table->timestamp('occurred_at');
            $table->string('reported_condition', 30)->nullable();
            $table->text('description');
            $table->text('involved_parties')->nullable();
            $table->text('investigation_findings')->nullable();
            $table->string('resolution_type', 30)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->string('condition_before', 30)->nullable();
            $table->string('condition_after', 30)->nullable();
            $table->string('asset_status_before', 20)->nullable();
            $table->string('asset_status_after', 20)->nullable();
            $table->string('evidence_path')->nullable();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'occurred_at'], 'asset_incident_status_occurred_index');
            $table->index(['asset_id', 'occurred_at'], 'asset_incident_asset_occurred_index');
        });

        Schema::create('asset_incident_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('asset_incident_id')->constrained('asset_incidents')->cascadeOnDelete();
            $table->string('event_type', 30);
            $table->string('status_from', 20)->nullable();
            $table->string('status_to', 20)->nullable();
            $table->string('condition_from', 30)->nullable();
            $table->string('condition_to', 30)->nullable();
            $table->string('asset_status_from', 20)->nullable();
            $table->string('asset_status_to', 20)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['asset_incident_id', 'created_at'], 'asset_incident_event_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_incident_events');
        Schema::dropIfExists('asset_incidents');
    }
};
