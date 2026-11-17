<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_request_levels', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('approval_request_id')
                ->constrained('approval_requests')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('level');

            /*
             * Snapshot approver.
             *
             * Jangan mengambil approver dari approval_workflow_levels
             * ketika approval sedang berjalan. User dapat berubah di
             * konfigurasi workflow, tetapi approval yang sudah berjalan
             * harus tetap menggunakan approver awal.
             */
            $table->foreignId('approver_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('status', 30)->default('WAITING');

            $table->timestamp('acted_at')->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique(
                ['approval_request_id', 'level'],
                'approval_request_levels_request_level_unique'
            );

            $table->index(
                ['approver_user_id', 'status'],
                'approval_request_levels_approver_status_index'
            );

            $table->index(
                ['approval_request_id', 'status'],
                'approval_request_levels_request_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_request_levels');
    }
};
