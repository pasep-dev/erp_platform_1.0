<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DashboardDataService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardDataServiceTest extends TestCase
{
    private array $dashboardTables = [
        'approval_request_levels',
        'approval_requests',
        'approval_workflows',
        'trx_asset_maintenance_records',
        'trx_asset_incidents',
        'trx_assets',
        'activity_logs',
        'trx_purchase_orders',
        'trx_goods_receipts',
        'trx_inventory_stock_movements',
        'trx_inventory_stock_thresholds',
        'trx_inventory_department_warehouses',
        'mst_products',
        'mst_org_departments',
        'model_has_roles',
        'role_has_permissions',
        'model_has_permissions',
        'permissions',
        'roles',
        'users',
    ];

    protected function tearDown(): void
    {
        foreach ($this->dashboardTables as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_dashboard_data_uses_live_counts_and_respects_module_permissions(): void
    {
        $this->createDashboardTables();
        Gate::before(fn (User $user, string $ability): bool => true);

        $user = new User;
        $user->setAttribute('id', 1);
        $user->setAttribute('name', 'Dashboard User');
        $user->setAttribute('department_id', 'department-1');

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Dashboard User'],
            ['id' => 2, 'name' => 'Request Submitter'],
        ]);
        DB::table('mst_org_departments')->insert(['id' => 'department-1']);
        DB::table('roles')->insert(['id' => 1, 'name' => 'Administrator', 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert([
            'role_id' => 1,
            'model_type' => User::class,
            'model_id' => 1,
        ]);

        DB::table('trx_assets')->insert([
            ['id' => 'asset-assigned', 'status' => 'ACTIVE', 'custodian_id' => 1],
            ['id' => 'asset-available', 'status' => 'ACTIVE', 'custodian_id' => null],
            ['id' => 'asset-maintenance', 'status' => 'ACTIVE', 'custodian_id' => null],
            ['id' => 'asset-disposed', 'status' => 'DISPOSED', 'custodian_id' => null],
        ]);
        DB::table('trx_asset_maintenance_records')->insert([
            [
                'id' => 'maintenance-1',
                'asset_id' => 'asset-maintenance',
                'status' => 'IN_PROGRESS',
                'scheduled_at' => today()->addDays(3)->toDateString(),
            ],
        ]);
        DB::table('trx_asset_incidents')->insert([
            ['id' => 'incident-open', 'status' => 'REPORTED'],
            ['id' => 'incident-closed', 'status' => 'RESOLVED'],
        ]);
        DB::table('trx_purchase_orders')->insert([
            ['id' => 'po-submitted', 'status' => 'SUBMITTED'],
            ['id' => 'po-partial', 'status' => 'PARTIALLY_RECEIVED'],
            ['id' => 'po-draft', 'status' => 'DRAFT'],
        ]);
        DB::table('trx_goods_receipts')->insert([
            ['id' => 'receipt-draft', 'status' => 'DRAFT'],
            ['id' => 'receipt-posted', 'status' => 'POSTED'],
        ]);

        DB::table('approval_workflows')->insert(['id' => 'workflow-1', 'name' => 'Asset transfer approval']);
        DB::table('approval_requests')->insert([
            [
                'id' => 'approval-1',
                'module' => 'ASSET_TRANSFER',
                'document_type' => 'ASSET_TRANSFER',
                'document_id' => 'transfer-1',
                'approval_workflow_id' => 'workflow-1',
                'status' => 'PENDING',
                'submitted_by' => 2,
                'submitted_at' => now()->subHour(),
            ],
            [
                'id' => 'approval-pr-1',
                'module' => 'PURCHASE_REQUEST',
                'document_type' => 'PURCHASE_REQUEST',
                'document_id' => 'purchase-request-1',
                'approval_workflow_id' => 'workflow-1',
                'status' => 'PENDING',
                'submitted_by' => 2,
                'submitted_at' => now()->subHour(),
            ],
        ]);
        DB::table('approval_request_levels')->insert([
            [
                'id' => 'level-1',
                'approval_request_id' => 'approval-1',
                'level' => 1,
                'approver_user_id' => 1,
                'status' => 'PENDING',
                'created_at' => now(),
            ],
        ]);
        DB::table('activity_logs')->insert([
            ['id' => 'activity-own', 'user_id' => 1, 'action' => 'created', 'module' => 'asset', 'description' => 'Registered an asset', 'created_at' => now()],
            ['id' => 'activity-other', 'user_id' => 2, 'action' => 'created', 'module' => 'asset', 'description' => 'Another user activity', 'created_at' => now()],
        ]);

        DB::table('mst_products')->insert([
            ['id' => 'product-low', 'group' => 'CONSUMABLE', 'is_active' => true],
            ['id' => 'product-partial', 'group' => 'CONSUMABLE', 'is_active' => true],
        ]);
        DB::table('trx_inventory_department_warehouses')->insert([
            'id' => 'warehouse-1',
            'department_id' => 'department-1',
            'location_id' => 'location-1',
        ]);
        DB::table('trx_inventory_stock_thresholds')->insert([
            [
                'id' => 'threshold-low',
                'department_id' => 'department-1',
                'location_id' => 'location-1',
                'product_id' => 'product-low',
                'minimum_quantity' => 5,
            ],
            [
                'id' => 'threshold-partial',
                'department_id' => 'department-1',
                'location_id' => 'location-1',
                'product_id' => 'product-partial',
                'minimum_quantity' => 3,
            ],
        ]);
        DB::table('trx_inventory_stock_movements')->insert([
            [
                'id' => 'movement-partial',
                'department_id' => 'department-1',
                'location_id' => 'location-1',
                'product_id' => 'product-partial',
                'direction' => 'IN',
                'quantity' => 1,
            ],
        ]);

        $data = app(DashboardDataService::class)->forUser($user);

        $this->assertSame([
            'pendingApprovals' => 1,
            'openPurchaseOrders' => 2,
            'totalAssets' => 4,
            'inMaintenance' => 1,
            'lowStock' => 2,
            'openIncidents' => 1,
        ], $data['stats']);
        $this->assertSame([
            'approval' => true,
            'procurement' => true,
            'asset' => true,
            'inventory' => true,
            'admin' => true,
        ], $data['access']);
        $this->assertSame([
            ['label' => 'In use', 'value' => 1, 'color' => 'bg-brand-700'],
            ['label' => 'Available', 'value' => 1, 'color' => 'bg-emerald-500'],
            ['label' => 'In maintenance', 'value' => 1, 'color' => 'bg-amber-500'],
            ['label' => 'Other', 'value' => 1, 'color' => 'bg-gray-400'],
        ], $data['assetStatus']);
        $this->assertSame(1, count($data['approvals']));
        $this->assertSame('Asset transfer approval', $data['approvals'][0]['title']);
        $this->assertSame(1, count($data['activities']));
        $this->assertSame('Registered an asset', $data['activities'][0]['text']);
        $this->assertSame(1, $data['pipeline'][0]['value']);
        $this->assertSame(1, $data['pipeline'][2]['value']);
        $this->assertSame(4, count($data['alerts']));
    }

    public function test_dashboard_hides_module_counts_without_view_permissions(): void
    {
        $this->createDashboardTables();
        Gate::before(fn (User $user, string $ability): bool => false);

        $user = new User;
        $user->setAttribute('id', 1);
        $user->setAttribute('name', 'Dashboard User');

        $data = app(DashboardDataService::class)->forUser($user);

        $this->assertSame(0, $data['stats']['totalAssets']);
        $this->assertSame(0, $data['stats']['openPurchaseOrders']);
        $this->assertSame(0, $data['stats']['inMaintenance']);
        $this->assertSame(0, $data['stats']['lowStock']);
        $this->assertSame(0, $data['stats']['openIncidents']);
        $this->assertSame([], $data['alerts']);
        $this->assertSame([0, 0, 0], array_column($data['pipeline'], 'value'));
        $this->assertSame([
            'approval' => true,
            'procurement' => false,
            'asset' => false,
            'inventory' => false,
            'admin' => false,
        ], $data['access']);
    }

    private function createDashboardTables(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
        });
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
        });
        Schema::create('mst_org_departments', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('mst_products', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('group');
            $table->boolean('is_active');
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('trx_assets', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('status');
            $table->unsignedBigInteger('custodian_id')->nullable();
        });
        Schema::create('trx_asset_maintenance_records', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('asset_id');
            $table->string('status');
            $table->date('scheduled_at')->nullable();
        });
        Schema::create('trx_asset_incidents', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('status');
        });
        Schema::create('trx_purchase_orders', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('status');
        });
        Schema::create('trx_goods_receipts', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('status');
        });
        Schema::create('approval_workflows', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('module');
            $table->string('document_type');
            $table->string('document_id');
            $table->string('approval_workflow_id');
            $table->string('status');
            $table->unsignedBigInteger('submitted_by');
            $table->timestamp('submitted_at')->nullable();
        });
        Schema::create('approval_request_levels', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('approval_request_id');
            $table->unsignedInteger('level');
            $table->unsignedBigInteger('approver_user_id');
            $table->string('status');
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('module');
            $table->string('description')->nullable();
            $table->timestamp('created_at');
        });
        Schema::create('trx_inventory_department_warehouses', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('department_id');
            $table->string('location_id');
        });
        Schema::create('trx_inventory_stock_thresholds', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('department_id');
            $table->string('location_id');
            $table->string('product_id');
            $table->decimal('minimum_quantity', 15, 3);
        });
        Schema::create('trx_inventory_stock_movements', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('department_id');
            $table->string('location_id');
            $table->string('product_id');
            $table->string('direction');
            $table->decimal('quantity', 15, 3);
        });
    }
}
