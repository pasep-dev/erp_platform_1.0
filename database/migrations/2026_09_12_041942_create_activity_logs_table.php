<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('action', 50);
            $table->string('module', 100);

            $table->string('subject_type', 255)
                ->nullable();

            $table->string('subject_id', 100)
                ->nullable();

            $table->string('description', 500)
                ->nullable();

            $table->jsonb('old_values')
                ->nullable();

            $table->jsonb('new_values')
                ->nullable();

            $table->ipAddress('ip_address')
                ->nullable();

            $table->text('user_agent')
                ->nullable();

            $table->string('request_id', 100)
                ->nullable();

            $table->timestampTz('created_at')
                ->useCurrent();

            $table->index('user_id');
            $table->index(['module', 'action']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('request_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
