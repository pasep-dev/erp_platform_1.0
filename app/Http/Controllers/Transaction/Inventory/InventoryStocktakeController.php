<?php

namespace App\Http\Controllers\Transaction\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Transaction\Inventory\InventoryDepartmentWarehouse;
use App\Models\Transaction\Inventory\InventoryStocktake;
use App\Models\User;
use App\Services\DepartmentAccessService;
use App\Services\Transaction\Inventory\InventoryStocktakeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventoryStocktakeController extends Controller
{
    public function __construct(
        private readonly InventoryStocktakeService $stocktakeService,
        private readonly DepartmentAccessService $departmentAccess,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.view'), 403);
        $departments = $this->departmentQuery($user)->orderBy('name')->get(['id', 'code', 'name']);
        $departmentId = $this->selectedDepartmentId($request, $user, $departments->pluck('id')->all());
        $requestedPerPage = $request->integer('per_page', 15);
        $perPage = in_array($requestedPerPage, [5, 15, 25, 50, 100], true) ? $requestedPerPage : 15;
        $stocktakes = $departmentId === null
            ? InventoryStocktake::query()->whereRaw('1 = 0')->paginate($perPage)->withQueryString()
            : InventoryStocktake::query()
                ->with(['location:id,code,name', 'initiatedBy:id,name'])
                ->where('department_id', $departmentId)
                ->latest()
                ->paginate($perPage)
                ->withQueryString();

        return Inertia::render('Transaction/Inventory/Stocktakes/Index', [
            'departments' => $departments,
            'selectedDepartmentId' => $departmentId,
            'canManage' => $user->can('consumable.update'),
            'stocktakes' => $stocktakes,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.update'), 403);
        $departments = $this->departmentQuery($user)->orderBy('name')->get(['id', 'code', 'name']);
        $departmentId = $this->selectedDepartmentId($request, $user, $departments->pluck('id')->all());
        $warehouses = $departmentId === null
            ? collect()
            : InventoryDepartmentWarehouse::query()
                ->with('location:id,code,name')
                ->where('department_id', $departmentId)
                ->orderBy('id')
                ->get()
                ->map(fn (InventoryDepartmentWarehouse $mapping): array => [
                    'id' => $mapping->location?->id,
                    'code' => $mapping->location?->code,
                    'name' => $mapping->location?->name,
                ]);

        return Inertia::render('Transaction/Inventory/Stocktakes/Create', [
            'departments' => $departments,
            'departmentId' => $departmentId,
            'warehouses' => $warehouses,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.update'), 403);
        $validated = $request->validate([
            'department_id' => ['required', 'string', 'size:26', Rule::exists('mst_org_departments', 'id')],
            'location_id' => ['required', 'string', 'size:26', Rule::exists('mst_org_locations', 'id')],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $department = $this->authorizedDepartment($user, $validated['department_id']);
        $location = Location::query()->findOrFail($validated['location_id']);
        abort_unless(InventoryDepartmentWarehouse::query()
            ->where('department_id', $department->id)
            ->where('location_id', $location->id)
            ->exists(), 404);

        $stocktake = $this->stocktakeService->createSnapshot($department, $location, $user, $validated['remarks'] ?? null);

        return to_route('transaction.inventory.stocktakes.show', $stocktake)
            ->with('success', "Sesi opname {$stocktake->number} dibuat dengan snapshot saldo saat ini.");
    }

    public function show(Request $request, InventoryStocktake $stocktake): Response
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.view'), 403);
        abort_unless($this->canAccessDepartment($user, $stocktake->department), 404);
        $stocktake->load(['department:id,code,name', 'location:id,code,name', 'initiatedBy:id,name', 'items.product:id,code,name,inventory_tracking_mode']);

        return Inertia::render('Transaction/Inventory/Stocktakes/Show', [
            'stocktake' => [
                'id' => $stocktake->id,
                'number' => $stocktake->number,
                'status' => $stocktake->status,
                'remarks' => $stocktake->remarks,
                'department' => $stocktake->department?->only(['id', 'code', 'name']),
                'location' => $stocktake->location?->only(['id', 'code', 'name']),
                'initiated_by' => $stocktake->initiatedBy?->name,
                'created_at' => $stocktake->created_at?->toIso8601String(),
                'posted_at' => $stocktake->posted_at?->toIso8601String(),
                'items' => $stocktake->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'product' => $item->product?->only(['id', 'code', 'name', 'inventory_tracking_mode']),
                    'snapshot_quantity' => (float) $item->snapshot_quantity,
                    'snapshot_serial_numbers' => $item->snapshot_serial_numbers ?? [],
                    'counted_quantity' => $item->counted_quantity === null ? null : (float) $item->counted_quantity,
                    'counted_serial_numbers' => $item->counted_serial_numbers ?? [],
                    'remarks' => $item->remarks,
                ])->values(),
            ],
            'canManage' => $user->can('consumable.update'),
        ]);
    }

    public function update(Request $request, InventoryStocktake $stocktake): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.update'), 403);
        abort_unless($this->canAccessDepartment($user, $stocktake->department), 404);
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'string', 'size:26'],
            'items.*.counted_quantity' => ['required', 'numeric', 'gte:0', 'decimal:0,3'],
            'items.*.serial_numbers' => ['nullable', 'array', 'max:500'],
            'items.*.serial_numbers.*' => ['required', 'string', 'max:150'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->stocktakeService->saveCounts($stocktake, $validated['items']);

        return back()->with('success', 'Hasil hitung tersimpan. Periksa selisih sebelum memposting opname.');
    }

    public function post(Request $request, InventoryStocktake $stocktake): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.update'), 403);
        abort_unless($this->canAccessDepartment($user, $stocktake->department), 404);

        $this->stocktakeService->post($stocktake, $user);

        return back()->with('success', 'Stock opname diposting. Selisih tercatat sebagai pergerakan koreksi append-only.');
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

        return in_array($user->department_id, $accessibleDepartmentIds, true)
            ? $user->department_id
            : ($accessibleDepartmentIds[0] ?? null);
    }

    private function canAccessDepartment(User $user, ?Department $department): bool
    {
        return $department !== null && $this->departmentAccess->canAccess($user, $department->id);
    }

    private function authorizedDepartment(User $user, string $departmentId): Department
    {
        $department = Department::query()->findOrFail($departmentId);
        abort_unless($this->canAccessDepartment($user, $department), 404);

        return $department;
    }
}
