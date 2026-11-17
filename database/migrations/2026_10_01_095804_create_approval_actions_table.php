<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_actions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('approval_request_id')
                ->constrained('approval_requests')
                ->cascadeOnDelete();

            $table->foreignUlid('approval_request_level_id')
                ->constrained('approval_request_levels')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('action', 30);

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index(
                ['approval_request_id', 'created_at'],
                'approval_actions_request_created_index'
            );

            $table->index(
                ['user_id', 'created_at'],
                'approval_actions_user_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
    }
};
