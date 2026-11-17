<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('number', 50)->unique();

            $table->date('request_date');

            $table->foreignId('requester_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignUlid('company_id')
                ->constrained('mst_org_companies')
                ->restrictOnDelete();

            $table->foreignUlid('department_id')
                ->constrained('mst_org_departments')
                ->restrictOnDelete();

            $table->string('purpose', 255);

            $table->text('remarks')->nullable();

            $table->string('status', 30)
                ->default('DRAFT');

            $table->timestamp('submitted_at')->nullable();

            $table->timestamp('approved_at')->nullable();

            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();

            $table->index(
                ['company_id', 'department_id'],
                'purchase_requests_organization_index'
            );

            $table->index('requester_id');
            $table->index('status');
            $table->index('request_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
