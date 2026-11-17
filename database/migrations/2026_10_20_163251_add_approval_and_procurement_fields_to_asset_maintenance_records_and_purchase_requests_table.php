<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_maintenance_records', function (Blueprint $table): void {
            $table->string('execution_mode', 20)->default('INTERNAL')->after('maintenance_type');
            $table->boolean('requires_spare_parts')->default(false)->after('execution_mode');
            $table->timestamp('submitted_at')->nullable()->after('requested_at');
        });

        DB::table('asset_maintenance_records')
            ->whereNotNull('vendor_id')
            ->update(['execution_mode' => 'VENDOR']);

        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->foreignUlid('asset_maintenance_record_id')
                ->nullable()
                ->after('requester_id')
                ->constrained('asset_maintenance_records')
                ->nullOnDelete();
            $table->index('asset_maintenance_record_id', 'purchase_requests_maintenance_index');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->dropIndex('purchase_requests_maintenance_index');
            $table->dropConstrainedForeignId('asset_maintenance_record_id');
        });

        Schema::table('asset_maintenance_records', function (Blueprint $table): void {
            $table->dropColumn(['execution_mode', 'requires_spare_parts', 'submitted_at']);
        });
    }
};
