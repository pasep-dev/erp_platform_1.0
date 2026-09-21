<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opt_purchase_request', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('number', 50);
            $table->date('request_date');

            $table->foreignId('requested_by');

            $table->ulid('company_id');
            $table->ulid('branch_id')->nullable();
            $table->ulid('department_id')->nullable();
            $table->ulid('cost_center_id')->nullable();

            $table->date('required_date')->nullable();

            $table->text('purpose')->nullable();
            $table->text('description')->nullable();

            $table->string('document_status', 30)->default('DRAFT');
            $table->string('approval_status', 30)->default('NOT_REQUIRED');

            $table->timestamps();
            $table->softDeletes();

            $table->unique('number');

            $table->index([
                'company_id',
                'request_date',
            ]);

            $table->index([
                'requested_by',
                'document_status',
            ]);

            $table->index([
                'approval_status',
                'document_status',
            ]);

            $table->foreign('requested_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->foreign('company_id')
                ->references('id')
                ->on('mst_org_companies')
                ->restrictOnDelete();

            $table->foreign('branch_id')
                ->references('id')
                ->on('mst_org_companies')
                ->restrictOnDelete();

            $table->foreign('department_id')
                ->references('id')
                ->on('mst_org_companies')
                ->restrictOnDelete();

            $table->foreign('cost_center_id')
                ->references('id')
                ->on('mst_org_cost_centers')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opt_purchase_request');
    }
};
