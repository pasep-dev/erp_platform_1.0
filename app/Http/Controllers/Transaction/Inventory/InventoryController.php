<?php

namespace App\Http\Controllers\Transaction\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Master\Product\Category;
use App\Models\Master\Product\Product;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\Inventory\InventoryDepartmentWarehouse;
use App\Models\Transaction\Inventory\InventoryDocument;
use App\Models\Transaction\Inventory\InventoryReceiptIntegration;
use App\Models\Transaction\Inventory\InventoryRequest;
use App\Models\Transaction\Inventory\InventoryStockMovement;
use App\Models\Transaction\Inventory\InventoryStockThreshold;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\BranchAccessService;
use App\Services\DepartmentAccessService;
use App\Services\Transaction\Inventory\InventoryOperationService;
use App\Services\Transaction\Inventory\InventoryStockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryStockService $inventoryStockService,
        private readonly InventoryOperationService $inventoryOperationService,
        private readonly ActivityLogger $activityLogger,
        private readonly DepartmentAccessService $departmentAccess,
        private readonly BranchAccessService $branchAccess,
    ) {}

    public function index(Request $request, string $component = 'Index'): Response
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.view'), 403);

        $departments = $this->departmentQuery($user)->with('company:id,name')->orderBy('name')->get();
        $departmentId = $this->selectedDepartmentId($request, $user, $departments->pluck('id')->all());
        $department = $departmentId === null ? null : $departments->firstWhere('id', $departmentId);
        $search = $request->string('search')->trim()->toString();
        $categoryId = $request->string('category_id')->toString();
        $warehouseId = $request->string('warehouse_id')->toString();
        $mappings = $department === null
            ? collect()
            : InventoryDepartmentWarehouse::query()
                ->with(['location:id,code,name,location_type,parent_id', 'location.parent:id,code,name'])
                ->where('department_id', $department->id)
                ->orderBy('id')
                ->get();
        $warehouseIds = $mappings->pluck('location_id')->all();

        $thresholds = $department === null
            ? collect()
            : InventoryStockThreshold::query()
                ->with(['product:id,code,name', 'location:id,code,name,parent_id', 'location.parent:id,code,name'])
                ->where('department_id', $department->id)
                ->whereIn('location_id', $warehouseIds)
                ->get()
                ->keyBy(fn (InventoryStockThreshold $threshold): string => $threshold->product_id.'|'.$threshold->location_id);

        $requestedPerPage = $request->integer('per_page', 20);
        $perPage = in_array($requestedPerPage, [5, 15, 20, 25, 50, 100], true) ? $requestedPerPage : 20;

        $products = Product::query()
            ->with(['category:id,name', 'baseUnitOfMeasure:id,code,name'])
            ->where('group', 'CONSUMABLE')
            ->where('is_active', true)
            ->when($department !== null, fn (Builder $query) => $query->whereHas('departments', fn (Builder $departments) => $departments->whereKey($department->id)))
            ->when($department === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($categoryId !== '', fn (Builder $query) => $query->where('category_id', $categoryId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $balances = collect();
        if ($department !== null && $warehouseIds !== []) {
            $balances = InventoryStockMovement::query()
                ->select('product_id', 'location_id')
                ->selectRaw("SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END) AS balance")
                ->where('department_id', $department->id)
                ->whereIn('location_id', $warehouseIds)
                ->when($warehouseId !== '', fn (Builder $query) => $query->where('location_id', $warehouseId))
                ->whereIn('product_id', $products->getCollection()->pluck('id'))
                ->groupBy('product_id', 'location_id')
                ->get()
                ->keyBy(fn (InventoryStockMovement $movement): string => $movement->product_id.'|'.$movement->location_id);
        }

        $products->getCollection()->transform(function (Product $product) use ($balances, $mappings, $thresholds, $warehouseId): array {
            $productMappings = $mappings->when($warehouseId !== '', fn ($rows) => $rows->where('location_id', $warehouseId));

            return [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'category' => $product->category?->name,
                'unit' => $product->baseUnitOfMeasure?->code ?? $product->baseUnitOfMeasure?->name,
                'inventory_tracking_mode' => $product->inventory_tracking_mode,
                'warehouses' => $productMappings->map(fn (InventoryDepartmentWarehouse $mapping): array => [
                    'id' => $mapping->location?->id,
                    'code' => $mapping->location?->code,
                    'name' => $mapping->location?->parent === null
                        ? $mapping->location?->name
                        : $mapping->location?->parent?->name.' / '.$mapping->location?->name,
                    'balance' => (float) ($balances->get($product->id.'|'.$mapping->location_id)?->balance ?? 0),
                    'minimum_quantity' => $thresholds->get($product->id.'|'.$mapping->location_id)?->minimum_quantity === null
                        ? null
                        : (float) $thresholds->get($product->id.'|'.$mapping->location_id)->minimum_quantity,
                ])->values()->all(),
            ];
        });

        $thresholdBalances = $thresholds->isEmpty()
            ? collect()
            : InventoryStockMovement::query()
                ->select('product_id', 'location_id')
                ->selectRaw("SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END) AS balance")
                ->where('department_id', $department->id)
                ->whereIn('product_id', $thresholds->pluck('product_id')->unique())
                ->whereIn('location_id', $thresholds->pluck('location_id')->unique())
                ->groupBy('product_id', 'location_id')
                ->get()
                ->keyBy(fn (InventoryStockMovement $movement): string => $movement->product_id.'|'.$movement->location_id);

        $thresholdAlerts = $thresholds->map(function (InventoryStockThreshold $threshold) use ($thresholdBalances): array {
            $balance = (float) ($thresholdBalances->get($threshold->product_id.'|'.$threshold->location_id)?->balance ?? 0);

            return [
                'id' => $threshold->id,
                'product_id' => $threshold->product_id,
                'product' => $threshold->product?->only(['id', 'code', 'name']),
                'location' => [
                    'id' => $threshold->location?->id,
                    'name' => $threshold->location?->parent === null
                        ? $threshold->location?->name
                        : $threshold->location?->parent?->name.' / '.$threshold->location?->name,
                ],
                'balance' => $balance,
                'minimum_quantity' => (float) $threshold->minimum_quantity,
                'shortage_quantity' => max(0, (float) $threshold->minimum_quantity - $balance),
                'is_low' => $balance < (float) $threshold->minimum_quantity,
            ];
        })->filter(fn (array $row): bool => $row['is_low'])->values();

        $warehouseLocations = $mappings->map(fn (InventoryDepartmentWarehouse $mapping): array => [
            'id' => $mapping->location?->id,
            'code' => $mapping->location?->code,
            'name' => $mapping->location?->parent === null
                ? $mapping->location?->name
                : $mapping->location?->parent?->name.' / '.$mapping->location?->name,
        ])->values();

        $allBalances = $department === null || $warehouseIds === []
            ? collect()
            : InventoryStockMovement::query()
                ->select('product_id', 'location_id')
                ->selectRaw("SUM(CASE WHEN direction = 'IN' THEN quantity ELSE -quantity END) AS balance")
                ->where('department_id', $department->id)
                ->whereIn('location_id', $warehouseIds)
                ->whereIn('product_id', Product::query()->where('group', 'CONSUMABLE')->where('is_active', true)->select('id'))
                ->groupBy('product_id', 'location_id')
                ->get();
        $lowStockCount = $thresholdAlerts->count();
        $totalConsumableProducts = Product::query()->where('group', 'CONSUMABLE')->where('is_active', true)
            ->when($department !== null, fn (Builder $query) => $query->whereHas('departments', fn (Builder $departments) => $departments->whereKey($department->id)))
            ->when($department === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->count();
        $positiveStockRows = $allBalances->filter(fn (InventoryStockMovement $movement): bool => (float) $movement->balance > 0)->count();
        $outOfStockCount = max(0, ($totalConsumableProducts * count($warehouseIds)) - $positiveStockRows);
        $pendingRequestsCount = $department === null
            ? 0
            : InventoryRequest::query()
                ->where('department_id', $department->id)
                ->where('status', InventoryRequest::STATUS_PENDING_APPROVAL)
                ->count();

        return Inertia::render('Transaction/Inventory/'.$component, [
            'departments' => $departments->map(fn (Department $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'company' => $item->company?->name,
            ]),
            'selectedDepartmentId' => $department?->id,
            'warehouses' => $mappings->map(fn (InventoryDepartmentWarehouse $mapping): array => [
                'id' => $mapping->location?->id,
                'code' => $mapping->location?->code,
                'name' => $mapping->location?->name,
            ]),
            'warehouseLocations' => $warehouseLocations,
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => ['search' => $search, 'category_id' => $categoryId, 'warehouse_id' => $warehouseId, 'per_page' => $perPage],
            'stats' => [
                'products' => $totalConsumableProducts,
                'below_minimum' => $lowStockCount,
                'out_of_stock' => $outOfStockCount,
                'pending_requests' => $pendingRequestsCount,
            ],
            'lowStockAlerts' => $thresholdAlerts,
            'lowStockAlertCount' => $lowStockCount,
            'products' => $products,
        ]);
    }

    public function stocks(Request $request): Response
    {
        return $this->index($request, 'Stocks/Index');
    }

    public function settings(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.update'), 403);

        $departments = $this->departmentQuery($user)->with('company:id,name')->orderBy('name')->get();
        $departmentId = $this->selectedDepartmentId($request, $user, $departments->pluck('id')->all());
        $department = $departmentId === null ? null : $departments->firstWhere('id', $departmentId);
        $mappings = $department === null
            ? collect()
            : InventoryDepartmentWarehouse::query()
                ->with(['location:id,code,name,parent_id', 'location.parent:id,code,name'])
                ->where('department_id', $department->id)
                ->orderBy('id')
                ->get();
        $thresholds = $department === null
            ? collect()
            : InventoryStockThreshold::query()
                ->where('department_id', $department->id)
                ->get()
                ->keyBy(fn (InventoryStockThreshold $threshold): string => $threshold->product_id.'|'.$threshold->location_id);
        $products = Product::query()
            ->where('group', 'CONSUMABLE')
            ->where('is_active', true)
            ->when($department !== null, fn (Builder $query) => $query->whereHas('departments', fn (Builder $departments) => $departments->whereKey($department->id)))
            ->when($department === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'warehouses' => $mappings->map(fn (InventoryDepartmentWarehouse $mapping): array => [
                    'id' => $mapping->location?->id,
                    'name' => $mapping->location?->parent === null
                        ? $mapping->location?->name
                        : $mapping->location?->parent?->name.' / '.$mapping->location?->name,
                    'minimum_quantity' => $thresholds->get($product->id.'|'.$mapping->location_id)?->minimum_quantity === null
                        ? null
                        : (float) $thresholds->get($product->id.'|'.$mapping->location_id)->minimum_quantity,
                ])->values()->all(),
            ]);

        return Inertia::render('Transaction/Inventory/Settings/Index', [
            'departments' => $departments->map(fn (Department $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'company' => $item->company?->name,
            ]),
            'selectedDepartmentId' => $department?->id,
            'warehouses' => $mappings->map(fn (InventoryDepartmentWarehouse $mapping): array => [
                'id' => $mapping->location?->id,
                'code' => $mapping->location?->code,
                'name' => $mapping->location?->parent === null
                    ? $mapping->location?->name
                    : $mapping->location?->parent?->name.' / '.$mapping->location?->name,
            ])->values(),
            'products' => $products,
        ]);
    }

    public function transactions(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.view'), 403);

        $departments = $this->departmentQuery($user)->with('company:id,name')->orderBy('name')->get();
        $departmentId = $this->selectedDepartmentId($request, $user, $departments->pluck('id')->all());
        $search = trim($request->string('search')->toString());
        $type = $request->string('type', 'ALL')->toString();
        $types = [
            InventoryDocument::TYPE_ISSUE,
            InventoryDocument::TYPE_TRANSFER,
            InventoryDocument::TYPE_RETURN,
            InventoryDocument::TYPE_ADJUSTMENT,
            InventoryDocument::TYPE_OPENING_BALANCE,
        ];
        if (! in_array($type, $types, true)) {
            $type = 'ALL';
        }

        $requestedPerPage = $request->integer('per_page', 15);
        $perPage = in_array($requestedPerPage, [5, 15, 25, 50, 100], true) ? $requestedPerPage : 15;

        $documents = InventoryDocument::query()
            ->with([
                'department:id,code,name',
                'sourceLocation:id,code,name',
                'targetLocation:id,code,name',
                'performedBy:id,name',
                'items:id,inventory_document_id,product_id,quantity',
                'items.product:id,code,name',
            ])
            ->when($departmentId === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($departmentId !== null, fn (Builder $query) => $query->where('department_id', $departmentId))
            ->where('status', InventoryDocument::STATUS_POSTED)
            ->when($type !== 'ALL', fn (Builder $query) => $query->where('document_type', $type))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('number', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%")
                        ->orWhereHas('items.product', function (Builder $query) use ($search): void {
                            $query->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('occurred_at')
            ->paginate($perPage)
            ->withQueryString();

        $documents->getCollection()->transform(fn (InventoryDocument $document): array => [
            'id' => $document->id,
            'number' => $document->number,
            'type' => $document->document_type,
            'status' => $document->status,
            'occurred_at' => $document->occurred_at->toIso8601String(),
            'source' => $document->sourceLocation?->name,
            'target' => $document->targetLocation?->name,
            'department' => $document->department?->only(['id', 'code', 'name']),
            'performed_by' => $document->performedBy?->name,
            'items' => $document->items->map(fn ($item): array => [
                'product_code' => $item->product?->code,
                'product_name' => $item->product?->name,
                'quantity' => (float) $item->quantity,
            ])->values(),
        ]);

        return Inertia::render('Transaction/Inventory/Transactions/Index', [
            'departments' => $departments->map(fn (Department $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'company' => $item->company?->name,
            ]),
            'selectedDepartmentId' => $departmentId,
            'canManage' => $user?->can('consumable.update') === true,
            'documents' => $documents,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function receipts(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.view'), 403);

        $departments = $this->departmentQuery($user)->with('company:id,name')->orderBy('name')->get();
        $departmentId = $this->selectedDepartmentId($request, $user, $departments->pluck('id')->all());
        $requestedPerPage = $request->integer('per_page', 15);
        $perPage = in_array($requestedPerPage, [5, 15, 25, 50, 100], true) ? $requestedPerPage : 15;

        $branchIds = $this->branchAccess->branchIds($user);
        $goodsReceiptRows = DB::table('trx_inventory_receipt_integrations as integration')
            ->join('trx_goods_receipt_items as receipt_item', 'receipt_item.id', '=', 'integration.goods_receipt_item_id')
            ->join('trx_goods_receipts as goods_receipt', 'goods_receipt.id', '=', 'receipt_item.goods_receipt_id')
            ->join('mst_products as product', 'product.id', '=', 'integration.product_id')
            ->leftJoin('mst_org_locations as warehouse', 'warehouse.id', '=', 'integration.location_id')
            ->whereIn('warehouse.branch_id', $branchIds)
            ->where('goods_receipt.status', 'POSTED')
            ->when($departmentId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($departmentId !== null, fn ($query) => $query->where('integration.department_id', $departmentId))
            ->select([
                'integration.id',
                'goods_receipt.number as document_number',
                'integration.status',
                'integration.failure_message',
                'product.code as product_code',
                'product.name as product_name',
                'warehouse.name as warehouse_name',
                'integration.accepted_quantity as quantity',
                'goods_receipt.posted_at as occurred_at',
            ])
            ->selectRaw("'GOODS_RECEIPT' as record_type")
            ->selectRaw('1 as item_count')
            ->selectRaw('CAST(NULL AS VARCHAR) as approval_mode');

        $manualReceiptRows = DB::table('trx_inventory_documents as document')
            ->leftJoin('mst_org_locations as warehouse', 'warehouse.id', '=', 'document.source_location_id')
            ->whereIn('warehouse.branch_id', $branchIds)
            ->where('document.document_type', InventoryDocument::TYPE_OPENING_BALANCE)
            ->when($departmentId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($departmentId !== null, fn ($query) => $query->where('document.department_id', $departmentId))
            ->select([
                'document.id',
                'document.number as document_number',
                'document.status',
            ])
            ->selectRaw('CAST(NULL AS VARCHAR) as failure_message')
            ->selectRaw('CAST(NULL AS VARCHAR) as product_code')
            ->selectRaw('CAST(NULL AS VARCHAR) as product_name')
            ->addSelect('warehouse.name as warehouse_name')
            ->selectRaw('CAST(NULL AS DECIMAL(15, 3)) as quantity')
            ->addSelect('document.occurred_at as occurred_at')
            ->selectRaw("'MANUAL_RECEIPT' as record_type")
            ->selectSub(
                DB::table('trx_inventory_document_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('trx_inventory_document_items.inventory_document_id', 'document.id'),
                'item_count',
            )
            ->selectRaw('CAST(NULL AS VARCHAR) as approval_mode');

        $receipts = DB::query()
            ->fromSub($goodsReceiptRows->unionAll($manualReceiptRows), 'receipts')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $receipts->getCollection()->transform(fn (object $receipt): array => [
            'id' => $receipt->id,
            'record_type' => $receipt->record_type,
            'document_number' => $receipt->document_number,
            'status' => $receipt->status,
            'failure_message' => $receipt->failure_message,
            'product_code' => $receipt->product_code,
            'product_name' => $receipt->product_name,
            'warehouse' => $receipt->warehouse_name,
            'quantity' => $receipt->quantity === null ? null : (float) $receipt->quantity,
            'item_count' => (int) $receipt->item_count,
            'approval_mode' => $receipt->approval_mode,
            'occurred_at' => $receipt->occurred_at,
        ]);

        return Inertia::render('Transaction/Inventory/Receipts/Index', [
            'departments' => $departments->map(fn (Department $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'company' => $item->company?->name,
            ]),
            'selectedDepartmentId' => $departmentId,
            'canManage' => $user?->can('consumable.update') === true,
            'receipts' => $receipts,
        ]);
    }

    public function createIssue(Request $request): Response
    {
        return $this->operationForm($request, InventoryDocument::TYPE_ISSUE);
    }

    public function createTransfer(Request $request): Response
    {
        return $this->operationForm($request, InventoryDocument::TYPE_TRANSFER);
    }

    public function createReturn(Request $request): Response
    {
        return $this->operationForm($request, InventoryDocument::TYPE_RETURN);
    }

    public function createAdjustment(Request $request): Response
    {
        return $this->operationForm($request, InventoryDocument::TYPE_ADJUSTMENT);
    }

    public function storeIssue(Request $request): RedirectResponse
    {
        $validated = $this->validateOperation($request, InventoryDocument::TYPE_ISSUE);
        $department = $this->authorizedDepartment($request->user(), $validated['department_id']);
        $document = $this->inventoryOperationService->issue($department, $request->user(), $validated);

        return to_route('transaction.inventory.transactions.index', ['department_id' => $department->id, 'type' => InventoryDocument::TYPE_ISSUE])
            ->with('success', "Pengeluaran {$document->number} berhasil diposting.");
    }

    public function storeTransfer(Request $request): RedirectResponse
    {
        $validated = $this->validateOperation($request, InventoryDocument::TYPE_TRANSFER);
        $department = $this->authorizedDepartment($request->user(), $validated['department_id']);
        $document = $this->inventoryOperationService->transfer($department, $request->user(), $validated);

        return to_route('transaction.inventory.transactions.index', ['department_id' => $department->id, 'type' => InventoryDocument::TYPE_TRANSFER])
            ->with('success', "Transfer {$document->number} berhasil diposting.");
    }

    public function storeReturn(Request $request): RedirectResponse
    {
        $validated = $this->validateOperation($request, InventoryDocument::TYPE_RETURN);
        $department = $this->authorizedDepartment($request->user(), $validated['department_id']);
        $document = $this->inventoryOperationService->returnStock($department, $request->user(), $validated);

        return to_route('transaction.inventory.transactions.index', ['department_id' => $department->id, 'type' => InventoryDocument::TYPE_RETURN])
            ->with('success', "Retur {$document->number} berhasil diposting.");
    }

    public function storeAdjustment(Request $request): RedirectResponse
    {
        $validated = $this->validateOperation($request, InventoryDocument::TYPE_ADJUSTMENT);
        $department = $this->authorizedDepartment($request->user(), $validated['department_id']);
        $document = $this->inventoryOperationService->adjust($department, $request->user(), $validated);

        return to_route('transaction.inventory.transactions.index', ['department_id' => $department->id, 'type' => InventoryDocument::TYPE_ADJUSTMENT])
            ->with('success', "Koreksi {$document->number} berhasil diposting.");
    }

    public function saveMinimumStock(Request $request, Department $department, Location $location, Product $product): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.update'), 403);
        abort_unless($this->canAccessDepartment($user, $department), 404);
        abort_unless($product->group === 'CONSUMABLE', 404);
        abort_unless(InventoryDepartmentWarehouse::query()
            ->where('department_id', $department->id)
            ->where('location_id', $location->id)
            ->exists(), 404);

        $validated = $request->validate([
            'minimum_quantity' => ['nullable', 'numeric', 'gte:0', 'decimal:0,3'],
        ]);
        $threshold = InventoryStockThreshold::query()
            ->where('department_id', $department->id)
            ->where('location_id', $location->id)
            ->where('product_id', $product->id)
            ->first();
        $oldValue = $threshold?->minimum_quantity;

        if ($validated['minimum_quantity'] === null) {
            $threshold?->delete();
            $newValue = null;
        } else {
            $threshold = InventoryStockThreshold::query()->updateOrCreate(
                ['department_id' => $department->id, 'location_id' => $location->id, 'product_id' => $product->id],
                ['minimum_quantity' => $validated['minimum_quantity'], 'configured_by' => $user->id],
            );
            $newValue = (float) $threshold->minimum_quantity;
        }

        $this->activityLogger->log(
            $newValue === null ? 'DELETED' : ($oldValue === null ? 'CREATED' : 'UPDATED'),
            'INVENTORY_MINIMUM_STOCK',
            "Batas minimum {$product->code} di gudang {$location->name} diperbarui.",
            InventoryStockThreshold::class,
            $threshold?->id,
            ['minimum_quantity' => $oldValue],
            ['minimum_quantity' => $newValue, 'department_id' => $department->id, 'location_id' => $location->id, 'product_id' => $product->id],
        );

        return to_route('transaction.inventory.settings', ['department_id' => $department->id])
            ->with('success', 'Batas minimum stok berhasil disimpan.');
    }

    public function stockCard(Request $request, Product $product, Location $location): Response
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.view'), 403);
        abort_unless($product->group === 'CONSUMABLE', 404);
        $departments = $this->departmentQuery($user)->orderBy('name')->get();
        $departmentId = $this->selectedDepartmentId($request, $user, $departments->pluck('id')->all());
        abort_if($departmentId === null, 404);

        $department = $departments->firstWhere('id', $departmentId);
        abort_if($department === null, 404);
        abort_unless($product->departments()->whereKey($department->id)->exists(), 404);
        abort_unless(InventoryDepartmentWarehouse::query()
            ->where('department_id', $department->id)
            ->where('location_id', $location->id)
            ->exists(), 404);

        return Inertia::render('Transaction/Inventory/StockCard', [
            'stockCard' => $this->inventoryStockService->stockCard($product, $location, $department),
        ]);
    }

    public function receiveSerials(Request $request, InventoryReceiptIntegration $integration): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.update'), 403);
        abort_unless($this->canAccessDepartment($user, $integration->department), 404);

        $validated = $request->validate([
            'serial_numbers' => ['required', 'array', 'min:1', 'max:500'],
            'serial_numbers.*' => ['required', 'string', 'max:150'],
        ]);

        $this->inventoryStockService->receiveSerials($integration, $validated['serial_numbers'], $user);

        return back()->with('success', 'Nomor seri disimpan ke kartu stok sebagai unit masuk.');
    }

    public function retryReceipt(Request $request, InventoryReceiptIntegration $integration): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.update'), 403);
        abort_unless($this->canAccessDepartment($user, $integration->department), 404);

        $this->inventoryStockService->retryIntegration($integration);

        return back()->with('success', 'Sinkronisasi penerimaan Inventory telah dicoba ulang.');
    }

    /** @return Builder<Department> */
    private function departmentQuery(User $user): Builder
    {
        $query = Department::query();

        return $this->departmentAccess->scopeDepartments($query, $user);
    }

    /** @param array<int, string> $accessibleDepartmentIds */
    private function selectedDepartmentId(Request $request, User $user, array $accessibleDepartmentIds): ?string
    {
        $requestedId = $request->string('department_id')->toString();

        if ($requestedId !== '' && in_array($requestedId, $accessibleDepartmentIds, true)) {
            return $requestedId;
        }

        if ($user->department_id !== null && in_array($user->department_id, $accessibleDepartmentIds, true)) {
            return $user->department_id;
        }

        return $accessibleDepartmentIds[0] ?? null;
    }

    private function canAccessDepartment(User $user, ?Department $department): bool
    {
        return $department !== null && $this->departmentAccess->canAccess($user, $department->id);
    }

    private function canAccessWarehouse(User $user, Location $location): bool
    {
        if ($this->departmentAccess->hasGlobalAccess($user)) {
            return InventoryDepartmentWarehouse::query()->where('location_id', $location->id)->exists();
        }

        return InventoryDepartmentWarehouse::query()
            ->whereIn('department_id', $this->departmentAccess->departmentIds($user))
            ->where('location_id', $location->id)
            ->exists();
    }

    private function operationForm(Request $request, string $type): Response
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.issue'), 403);

        $departments = $this->departmentQuery($user)->with('company:id,name')->orderBy('name')->get();
        $departmentId = $this->selectedDepartmentId($request, $user, $departments->pluck('id')->all());
        $department = $departmentId === null ? null : $departments->firstWhere('id', $departmentId);
        $warehouseIds = $department === null
            ? []
            : InventoryDepartmentWarehouse::query()->where('department_id', $department->id)->pluck('location_id')->all();

        return Inertia::render('Transaction/Inventory/OperationForm', [
            'operationType' => $type,
            'departments' => $departments->map(fn (Department $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
            ]),
            'departmentId' => $department?->id,
            'warehouses' => $department === null
                ? []
                : Location::query()->whereIn('id', $warehouseIds)->orderBy('name')->get(['id', 'code', 'name']),
            'products' => Product::query()
                ->where('group', 'CONSUMABLE')
                ->where('is_active', true)
                ->when($department !== null, fn (Builder $query) => $query->whereHas('departments', fn (Builder $departments) => $departments->whereKey($department->id)))
                ->when($department === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'inventory_tracking_mode']),
            'users' => $department === null
                ? []
                : User::query()->where(function (Builder $query) use ($department): void {
                    $query->where('department_id', $department->id)
                        ->orWhereHas('additionalDepartments', fn (Builder $departments) => $departments->whereKey($department->id));
                })->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'assets' => $department === null
                ? []
                : Asset::query()
                    ->where('department_id', $department->id)
                    ->where('status', Asset::STATUS_ACTIVE)
                    ->orderBy('number')
                    ->get(['id', 'number', 'product_id', 'serial_number']),
            'locations' => $department === null
                ? []
                : Location::query()
                    ->where('company_id', $department->company_id)
                    ->where('is_active', true)
                    ->whereRaw('LOWER(location_type) NOT IN (?, ?)', ['warehouse', 'gudang'])
                    ->orderBy('name')
                    ->get(['id', 'code', 'name']),
        ]);
    }

    /** @return array<string, mixed> */
    private function validateOperation(Request $request, string $type): array
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.issue'), 403);

        $rules = [
            'department_id' => ['required', 'string', 'size:26', Rule::exists('mst_org_departments', 'id')],
            'occurred_at' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'string', 'size:26', Rule::exists('mst_products', 'id')->where('group', 'CONSUMABLE')->where('is_active', true)->whereNull('deleted_at'), Rule::exists('mst_product_departments', 'product_id')->where('department_id', $request->input('department_id'))],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'items.*.serial_numbers' => ['nullable', 'array', 'max:500'],
            'items.*.serial_numbers.*' => ['required', 'string', 'max:150'],
            'items.*.reason' => ['nullable', 'string', 'max:1000'],
        ];

        if ($type === InventoryDocument::TYPE_ISSUE) {
            $rules += [
                'source_location_id' => ['required', 'string', 'size:26'],
                'items.*.party_type' => ['required', Rule::in(['USER', 'ASSET', 'LOCATION'])],
                'items.*.party_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
                'items.*.party_asset_id' => ['nullable', 'string', 'size:26', Rule::exists('trx_assets', 'id')],
                'items.*.party_location_id' => ['nullable', 'string', 'size:26', Rule::exists('mst_org_locations', 'id')],
                'items.*.specification_changes' => ['nullable', 'array', 'max:30'],
                'items.*.specification_changes.*' => ['nullable', 'string', 'max:150'],
            ];
        } elseif ($type === InventoryDocument::TYPE_TRANSFER) {
            $rules += [
                'source_location_id' => ['required', 'string', 'size:26'],
                'target_location_id' => ['required', 'string', 'size:26'],
            ];
        } elseif ($type === InventoryDocument::TYPE_RETURN) {
            $rules += [
                'target_location_id' => ['required', 'string', 'size:26'],
                'items.*.party_type' => ['required', Rule::in(['USER', 'ASSET', 'LOCATION'])],
                'items.*.reason' => ['required', 'string', 'max:1000'],
                'items.*.party_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
                'items.*.party_asset_id' => ['nullable', 'string', 'size:26', Rule::exists('trx_assets', 'id')],
                'items.*.party_location_id' => ['nullable', 'string', 'size:26', Rule::exists('mst_org_locations', 'id')],
                'items.*.related_issue_item_id' => ['nullable', 'string', 'size:26', Rule::exists('trx_inventory_document_items', 'id')],
            ];
        } else {
            $rules += [
                'location_id' => ['required', 'string', 'size:26'],
                'direction' => ['required', Rule::in(['IN', 'OUT'])],
                'items.*.reason' => ['required', 'string', 'max:1000'],
            ];
        }

        return $request->validate($rules);
    }

    private function authorizedDepartment(User $user, string $departmentId): Department
    {
        $department = Department::query()->findOrFail($departmentId);
        abort_unless($this->canAccessDepartment($user, $department), 404);

        return $department;
    }
}
