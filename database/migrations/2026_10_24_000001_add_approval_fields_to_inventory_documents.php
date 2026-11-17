<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_documents', function (Blueprint $table): void {
            $table->foreignUlid('approval_request_id')
                ->nullable()
                ->unique()
                ->after('performed_by')
                ->constrained('approval_requests')
                ->restrictOnDelete();
            $table->foreignId('posted_by')
                ->nullable()
                ->after('approval_request_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('posted_by');
            $table->timestamp('posted_at')->nullable()->after('submitted_at');
            $table->string('approval_mode', 20)->default('WORKFLOW')->after('posted_at');
            $table->text('manual_approval_document_path')->nullable()->after('approval_mode');
            $table->text('manual_approval_remarks')->nullable()->after('manual_approval_document_path');
            $table->foreignId('manual_approved_by')
                ->nullable()
                ->after('manual_approval_remarks')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('manual_approved_at')->nullable()->after('manual_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('posted_by');
            $table->dropConstrainedForeignId('manual_approved_by');
            $table->dropConstrainedForeignId('approval_request_id');
            $table->dropColumn([
                'submitted_at',
                'posted_at',
                'approval_mode',
                'manual_approval_document_path',
                'manual_approval_remarks',
                'manual_approved_at',
            ]);
        });
    }
};
