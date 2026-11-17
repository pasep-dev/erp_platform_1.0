<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflow_levels', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('approval_workflow_id')
                ->constrained('approval_workflows')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('level');

            $table->foreignId('approver_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(
                ['approval_workflow_id', 'level'],
                'approval_workflow_levels_workflow_level_unique'
            );

            $table->unique(
                ['approval_workflow_id', 'approver_user_id'],
                'approval_workflow_levels_workflow_user_unique'
            );

            $table->index(
                ['approver_user_id'],
                'approval_workflow_levels_approver_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflow_levels');
    }
};
