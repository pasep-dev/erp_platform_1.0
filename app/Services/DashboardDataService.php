<?php

namespace App\Services;

use App\Enums\Administrator\Workflow\ApprovalStatus;
use App\Models\ActivityLog;
use App\Models\Administrator\Workflow\ApprovalRequest;
use App\Models\Administrator\Workflow\ApprovalRequestLevel;
use App\Models\Master\Organization\Department;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetIncident;
use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use App\Models\Transaction\Inventory\InventoryStockMovement;
use App\Models\Transaction\Inventory\InventoryStockThreshold;
use App\Models\Transaction\Procurement\GoodsReceipt;
use App\Models\Transaction\Procurement\PurchaseOrder;
use App\Models\Transaction\Procurement\PurchaseRequest;
use App\Models\User;
use App\Services\Administrator\Workflow\ApprovalService;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DashboardDataService
{
    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly DepartmentAccessService $departmentAccess,
    ) {}

    /**
     * @return array{
     *     stats: array{
     *         pendingApprovals: int,
     *         openPurchaseOrders: int,
     *         totalAssets: int,
     *         inMaintenance: int,
     *         lowStock: int,
     *         openIncidents: int
     *     },
     *     approvals: array<int, array{id: string, type: string, title: string, requester: string, age: string, routeName: string}>,
     *     alerts: array<int, array{module: string, severity: string, text: string, routeName: string}>,
     *     activities: array<int, array{text: string, by: string, time: string}>,
     *     assetStatus: array<int, array{label: string, value: int, color: string}>,
     *     pipeline: array<int, array{label: string, value: int}>
     * }
     */
    public function forUser(User $user, ?string $requestedDepartmentId = null): array
    {
        $departments = Department::query()
            ->whereIn('id', $this->departmentAccess->departmentIds($user))
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
        $selectedDepartmentId = $departments->contains('id', $requestedDepartmentId)
            ? $requestedDepartmentId
            : $departments->first()?->id;
        $approvalInbox = $selectedDepartmentId === null
            ? collect()
            : $this->approvalService->inbox($user, $selectedDepartmentId);
        $canViewAssets = $user->can('asset.view');
        $canViewPurchaseOrders = $user->can('purchase-order.view');
        $canViewPurchaseRequests = $user->can('purchase-request.view');
        $canViewGoodsReceipts = $user->can('good-receipt.view');
        $canViewInventory = $user->can('consumable.view');

        $openPurchaseOrders = $canViewPurchaseOrders && $selectedDepartmentId !== null
            ? PurchaseOrder::query()
                ->where('department_id', $selectedDepartmentId)
                ->whereIn('status', ['SUBMITTED', 'PARTIALLY_RECEIVED'])
                ->count()
            : 0;
        $totalAssets = $canViewAssets && $selectedDepartmentId !== null
            ? Asset::query()->where('department_id', $selectedDepartmentId)->count()
            : 0;
        $inMaintenance = $canViewAssets && $selectedDepartmentId !== null
            ? Asset::query()
                ->where('department_id', $selectedDepartmentId)
                ->where('status', Asset::STATUS_ACTIVE)
                ->whereHas('maintenanceRecords', fn ($query) => $query
                    ->where('status', AssetMaintenanceRecord::STATUS_IN_PROGRESS))
                ->count()
            : 0;
        $openIncidents = $canViewAssets && $selectedDepartmentId !== null
            ? AssetIncident::query()
                ->whereHas('asset', fn ($query) => $query->where('department_id', $selectedDepartmentId))
                ->whereIn('status', [
                    AssetIncident::STATUS_REPORTED,
                    AssetIncident::STATUS_INVESTIGATING,
                ])
                ->count()
            : 0;
        $lowStock = $canViewInventory && $selectedDepartmentId !== null
            ? $this->lowStockCount($selectedDepartmentId)
            : 0;

        return [
            'stats' => [
                'pendingApprovals' => $approvalInbox->count(),
                'openPurchaseOrders' => $openPurchaseOrders,
                'totalAssets' => $totalAssets,
                'inMaintenance' => $inMaintenance,
                'lowStock' => $lowStock,
                'openIncidents' => $openIncidents,
            ],
            'access' => [
                'approval' => true,
                'procurement' => $canViewPurchaseRequests
                    || $canViewPurchaseOrders
                    || $canViewGoodsReceipts,
                'asset' => $canViewAssets,
                'inventory' => $canViewInventory,
                'admin' => $user->can('approval.workflow.view'),
            ],
            'departments' => $departments->map(fn (Department $department): array => [
                'id' => (string) $department->id,
                'code' => $department->code,
                'name' => $department->name,
            ])->all(),
            'selectedDepartmentId' => $selectedDepartmentId !== null
                ? (string) $selectedDepartmentId
                : null,
            'approvals' => $this->approvalRows($approvalInbox),
            'alerts' => $this->alerts(
                $canViewAssets,
                $canViewPurchaseOrders,
                $canViewInventory,
                $selectedDepartmentId,
                $openPurchaseOrders,
                $lowStock,
                $openIncidents,
            ),
            'activities' => $this->recentActivity($user),
            'assetStatus' => $canViewAssets && $selectedDepartmentId !== null
                ? $this->assetStatus($selectedDepartmentId)
                : $this->emptyAssetStatus(),
            'pipeline' => $this->procurementPipeline(
                $canViewPurchaseRequests,
                $canViewPurchaseOrders,
                $canViewGoodsReceipts,
                $selectedDepartmentId,
                $openPurchaseOrders,
            ),
        ];
    }

    /**
     * @param  Collection<int, ApprovalRequestLevel>  $approvalInbox
     * @return array<int, array{id: string, type: string, title: string, requester: string, age: string, routeName: string}>
     */
    private function approvalRows(Collection $approvalInbox): array
    {
        return $approvalInbox
            ->take(5)
            ->map(function (ApprovalRequestLevel $level): array {
                $request = $level->request;

                return [
                    'id' => (string) $request->id,
                    'type' => Str::headline($request->document_type),
                    'title' => $request->workflow?->name ?? Str::headline($request->module).' approval',
                    'requester' => $request->submitter?->name ?? 'Unknown requester',
                    'age' => $request->submitted_at?->diffForHumans() ?? 'Recently submitted',
                    'routeName' => 'approval.inbox.index',
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<int, array{label: string, value: int, color: string}> */
    private function assetStatus(string $departmentId): array
    {
        $activeAssets = Asset::query()
            ->where('department_id', $departmentId)
            ->where('status', Asset::STATUS_ACTIVE);
        $inMaintenance = (clone $activeAssets)
            ->whereHas('maintenanceRecords', fn ($query) => $query
                ->where('status', AssetMaintenanceRecord::STATUS_IN_PROGRESS))
            ->count();
        $inUse = (clone $activeAssets)
            ->whereNotNull('custodian_id')
            ->whereDoesntHave('maintenanceRecords', fn ($query) => $query
                ->where('status', AssetMaintenanceRecord::STATUS_IN_PROGRESS))
            ->count();
        $available = (clone $activeAssets)
            ->whereNull('custodian_id')
            ->whereDoesntHave('maintenanceRecords', fn ($query) => $query
                ->where('status', AssetMaintenanceRecord::STATUS_IN_PROGRESS))
            ->count();
        $other = Asset::query()
            ->where('department_id', $departmentId)
            ->count() - $inMaintenance - $inUse - $available;

        return [
            ['label' => 'In use', 'value' => $inUse, 'color' => 'bg-brand-700'],
            ['label' => 'Available', 'value' => $available, 'color' => 'bg-emerald-500'],
            ['label' => 'In maintenance', 'value' => $inMaintenance, 'color' => 'bg-amber-500'],
            ['label' => 'Other', 'value' => max(0, $other), 'color' => 'bg-gray-400'],
        ];
    }

    /**
     * @return array<int, array{label: string, value: int, color: string}>
     */
    private function emptyAssetStatus(): array
    {
        return [
            ['label' => 'In use', 'value' => 0, 'color' => 'bg-brand-700'],
            ['label' => 'Available', 'value' => 0, 'color' => 'bg-emerald-500'],
            ['label' => 'In maintenance', 'value' => 0, 'color' => 'bg-amber-500'],
            ['label' => 'Other', 'value' => 0, 'color' => 'bg-gray-400'],
        ];
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function procurementPipeline(
        bool $canViewPurchaseRequests,
        bool $canViewPurchaseOrders,
        bool $canViewGoodsReceipts,
        ?string $departmentId,
        int $openPurchaseOrders,
    ): array {
        $requestsAwaitingApproval = $canViewPurchaseRequests && $departmentId !== null
            ? ApprovalRequest::query()
                ->where('module', 'PURCHASE_REQUEST')
                ->where('status', ApprovalStatus::PENDING)
                ->whereIn('document_id', PurchaseRequest::query()
                    ->where('department_id', $departmentId)
                    ->select('id'))
                ->count()
            : 0;

        return [
            ['label' => 'Purchase requests awaiting approval', 'value' => $requestsAwaitingApproval],
            ['label' => 'Purchase orders in progress', 'value' => $canViewPurchaseOrders ? $openPurchaseOrders : 0],
            [
                'label' => 'Goods receipts awaiting posting',
                'value' => $canViewGoodsReceipts && $departmentId !== null
                    ? GoodsReceipt::query()
                        ->where('department_id', $departmentId)
                        ->where('status', GoodsReceipt::STATUS_DRAFT)
                        ->count()
                    : 0,
            ],
        ];
    }

    /**
     * @return array<int, array{module: string, severity: string, text: string, routeName: string}>
     */
    private function alerts(
        bool $canViewAssets,
        bool $canViewPurchaseOrders,
        bool $canViewInventory,
        ?string $departmentId,
        int $openPurchaseOrders,
        int $lowStock,
        int $openIncidents,
    ): array {
        $alerts = [];

        if ($canViewInventory && $lowStock > 0) {
            $alerts[] = [
                'module' => 'inventory',
                'severity' => 'critical',
                'text' => "{$lowStock} stock items are below their minimum level",
                'routeName' => 'transaction.inventory.stocks.index',
            ];
        }

        if ($canViewAssets && $departmentId !== null) {
            $dueMaintenance = AssetMaintenanceRecord::query()
                ->whereHas('asset', fn ($query) => $query->where('department_id', $departmentId))
                ->whereIn('status', [
                    AssetMaintenanceRecord::STATUS_READY_TO_START,
                    AssetMaintenanceRecord::STATUS_IN_PROGRESS,
                    AssetMaintenanceRecord::STATUS_WAITING_PARTS,
                ])
                ->whereBetween('scheduled_at', [today(), today()->addDays(7)])
                ->count();

            if ($dueMaintenance > 0) {
                $alerts[] = [
                    'module' => 'asset',
                    'severity' => 'warning',
                    'text' => "{$dueMaintenance} maintenance schedules are due within 7 days",
                    'routeName' => 'transaction.asset-management.maintenance.index',
                ];
            }

            if ($openIncidents > 0) {
                $alerts[] = [
                    'module' => 'asset',
                    'severity' => 'info',
                    'text' => "{$openIncidents} asset incidents are still open",
                    'routeName' => 'transaction.asset-management.incidents.index',
                ];
            }
        }

        if ($canViewPurchaseOrders && $openPurchaseOrders > 0) {
            $alerts[] = [
                'module' => 'procurement',
                'severity' => 'info',
                'text' => "{$openPurchaseOrders} purchase orders are awaiting receipt",
                'routeName' => 'transaction.procurement.purchase-orders.index',
            ];
        }

        return $alerts;
    }

    /**
     * @return array<int, array{text: string, by: string, time: string}>
     */
    private function recentActivity(User $user): array
    {
        return ActivityLog::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit(5)
            ->get(['description', 'action', 'module', 'created_at'])
            ->map(fn (ActivityLog $activity): array => [
                'text' => $activity->description
                    ?? Str::headline($activity->action).' in '.Str::headline($activity->module),
                'by' => $user->name,
                'time' => $activity->created_at?->diffForHumans() ?? '',
            ])
            ->all();
    }

    private function lowStockCount(string $departmentId): int
    {
        $departmentIds = [$departmentId];

        $balances = InventoryStockMovement::query()
            ->select(['department_id', 'location_id', 'product_id'])
            ->selectRaw("SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END) AS balance")
            ->whereIn('department_id', $departmentIds)
            ->groupBy('department_id', 'location_id', 'product_id');

        return InventoryStockThreshold::query()
            ->whereIn('trx_inventory_stock_thresholds.department_id', $departmentIds)
            ->whereHas('product', fn ($query) => $query
                ->where('group', 'CONSUMABLE')
                ->where('is_active', true))
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('trx_inventory_department_warehouses as warehouses')
                ->whereColumn('warehouses.department_id', 'trx_inventory_stock_thresholds.department_id')
                ->whereColumn('warehouses.location_id', 'trx_inventory_stock_thresholds.location_id'))
            ->leftJoinSub($balances, 'stock_balances', function (JoinClause $join): void {
                $join->on('stock_balances.department_id', '=', 'trx_inventory_stock_thresholds.department_id')
                    ->on('stock_balances.location_id', '=', 'trx_inventory_stock_thresholds.location_id')
                    ->on('stock_balances.product_id', '=', 'trx_inventory_stock_thresholds.product_id');
            })
            ->whereRaw('COALESCE(stock_balances.balance, 0) < trx_inventory_stock_thresholds.minimum_quantity')
            ->count();
    }
}
