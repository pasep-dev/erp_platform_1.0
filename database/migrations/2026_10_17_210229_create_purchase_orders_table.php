<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number', 50)->unique();
            $table->foreignUlid('purchase_request_id')
                ->constrained('purchase_requests')
                ->restrictOnDelete();
            $table->foreignUlid('company_id')
                ->constrained('mst_org_companies')
                ->restrictOnDelete();
            $table->foreignUlid('department_id')
                ->constrained('mst_org_departments')
                ->restrictOnDelete();
            $table->foreignUlid('vendor_id')
                ->constrained('mst_vendors')
                ->restrictOnDelete();
            $table->string('status', 20)->default('DRAFT');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'purchase_orders_status_created_index');
            $table->index(['purchase_request_id', 'vendor_id'], 'purchase_orders_request_vendor_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
