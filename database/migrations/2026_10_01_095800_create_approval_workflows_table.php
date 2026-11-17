<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('module', 100);

            $table->foreignUlid('company_id')
                ->constrained('mst_org_companies')
                ->restrictOnDelete();

            $table->foreignUlid('department_id')
                ->constrained('mst_org_departments')
                ->restrictOnDelete();

            $table->string('name', 150);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['module', 'company_id', 'department_id'],
                'approval_workflows_scope_index'
            );

            $table->index(
                ['module', 'is_active'],
                'approval_workflows_module_active_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflows');
    }
};
