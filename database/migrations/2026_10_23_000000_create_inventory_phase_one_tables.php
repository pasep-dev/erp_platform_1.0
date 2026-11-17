<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mst_products', function (Blueprint $table): void {
            $table->string('inventory_tracking_mode', 20)->default('QUANTITY');
        });

        Schema::create('inventory_department_warehouses', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('department_id')->constrained('mst_org_departments')->restrictOnDelete();
            $table->foreignUlid('location_id')->constrained('mst_org_locations')->restrictOnDelete();
            $table->foreignId('configured_by')->constrained('users')->restrictOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['department_id', 'location_id']);
            $table->index(['department_id', 'is_default']);

        });

        Schema::create('inventory_receipt_integrations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('goods_receipt_item_id')->unique()->constrained('goods_receipt_items')->restrictOnDelete();
            $table->foreignUlid('product_id')->constrained('mst_products')->restrictOnDelete();
            $table->foreignUlid('department_id')->constrained('mst_org_departments')->restrictOnDelete();
            $table->foreignUlid('location_id')->nullable()->constrained('mst_org_locations')->restrictOnDelete();
            $table->decimal('accepted_quantity', 15, 3);
            $table->string('status', 30)->default('PENDING_WAREHOUSE');
            $table->text('failure_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status']);
        });

        Schema::create('inventory_stock_movements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_id')->constrained('mst_products')->restrictOnDelete();
            $table->foreignUlid('location_id')->constrained('mst_org_locations')->restrictOnDelete();
            $table->foreignUlid('department_id')->constrained('mst_org_departments')->restrictOnDelete();
            $table->string('movement_type', 30);
            $table->string('direction', 3);
            $table->decimal('quantity', 15, 3);
            $table->string('serial_number', 150)->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->string('source_type', 50);
            $table->string('source_id', 26);
            $table->string('source_line_id', 26)->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->timestamp('occurred_at');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'location_id', 'occurred_at'], 'inventory_stock_card_index');
            $table->index(['department_id', 'created_at'], 'inventory_department_movements_index');
            $table->index(['source_type', 'source_id'], 'inventory_source_movements_index');
        });

        DB::statement('CREATE UNIQUE INDEX inventory_one_default_warehouse_per_department ON inventory_department_warehouses (department_id) WHERE is_default = TRUE');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION reject_inventory_stock_movement_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'inventory_stock_movements is append-only';
                END;
                $$ LANGUAGE plpgsql;
                SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER inventory_stock_movements_append_only
                    BEFORE UPDATE OR DELETE ON inventory_stock_movements
                    FOR EACH ROW EXECUTE FUNCTION reject_inventory_stock_movement_mutation();
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_stock_movements_append_only ON inventory_stock_movements');
            DB::unprepared('DROP FUNCTION IF EXISTS reject_inventory_stock_movement_mutation()');
        }

        Schema::dropIfExists('inventory_stock_movements');
        Schema::dropIfExists('inventory_receipt_integrations');
        Schema::dropIfExists('inventory_department_warehouses');

        DB::statement('DROP INDEX IF EXISTS inventory_one_default_warehouse_per_department');

        Schema::table('mst_products', function (Blueprint $table): void {
            $table->dropColumn('inventory_tracking_mode');
        });
    }
};
