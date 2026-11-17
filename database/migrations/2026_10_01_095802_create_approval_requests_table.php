<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('module', 100);
            $table->string('document_type', 100);
            $table->string('document_id', 26);

            $table->foreignUlid('approval_workflow_id')
                ->constrained('approval_workflows')
                ->restrictOnDelete();

            $table->string('status', 30)->default('PENDING');

            $table->unsignedSmallInteger('current_level')->nullable();

            $table->foreignId('submitted_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(
                ['module', 'document_type', 'document_id'],
                'approval_requests_document_index'
            );

            $table->index(
                ['status', 'current_level'],
                'approval_requests_status_level_index'
            );

            $table->index(
                ['submitted_by'],
                'approval_requests_submitted_by_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
