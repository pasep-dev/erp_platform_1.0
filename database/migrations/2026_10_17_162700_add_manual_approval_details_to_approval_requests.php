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
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->string('manual_approval_document_path', 500)->nullable();
            $table->text('manual_approval_remarks')->nullable();
            $table->foreignId('manual_approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('manual_approved_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manual_approved_by');
            $table->dropColumn([
                'manual_approval_document_path',
                'manual_approval_remarks',
                'manual_approved_at',
            ]);
        });
    }
};
