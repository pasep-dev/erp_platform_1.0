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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUlid('company_id')
                ->nullable()
                ->after('id')
                ->constrained('mst_org_companies')
                ->nullOnDelete();

            $table->foreignUlid('branch_id')
                ->nullable()
                ->after('company_id')
                ->constrained('mst_org_branches')
                ->nullOnDelete();

            $table->foreignUlid('department_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('mst_org_departments')
                ->nullOnDelete();

            $table->foreignUlid('position_id')
                ->nullable()
                ->after('department_id')
                ->constrained('mst_org_positions')
                ->nullOnDelete();

            $table->index(
                ['company_id', 'branch_id', 'department_id'],
                'users_organization_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['position_id']);

            $table->dropIndex('users_organization_index');

            $table->dropColumn([
                'company_id',
                'branch_id',
                'department_id',
                'position_id',
            ]);
        });
    }
};
