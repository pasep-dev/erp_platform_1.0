<?php

namespace App\Http\Controllers\Transaction\Inventory;

use App\Enums\NumberingModuleCode;
use App\Http\Controllers\Controller;
use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Master\Product\Product;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\Inventory\InventoryDepartmentWarehouse;
use App\Models\Transaction\Inventory\InventoryRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Administrator\Workflow\ApprovalService;
use App\Services\BranchAccessService;
use App\Services\DepartmentAccessService;
use App\Services\NumberingService;
use App\Services\Transaction\Inventory\InventoryOperationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class InventoryRequestController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly InventoryOperationService $inventoryOperationService,
        private readonly ActivityLogger $activityLogger,
        private readonly DepartmentAccessService $departmentAccess,
        private readonly BranchAccessService $branchAccess,
        private readonly NumberingService $numberingService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.view'), 403);

        $requestedPerPage = $request->integer('per_page', 15);
        $perPage = in_array($requestedPerPage, [5, 15, 25, 50, 100], true) ? $requestedPerPage : 15;

        $requests = $this->requestQuery($user)
            ->with(['department:id,code,name', 'requester:id,name', 'items.product:id,code,name'])
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Transaction/Inventory/Requests/Index', [
            'requests' => $requests,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.request'), 403);

        return Inertia::render('Transaction/Inventory/Requests/Create', [
            'branches' => $this->branchAccess->scopeBranches(Branch::query(), $user)->where('is_active', true)->orderBy('name')->get(['id', 'company_id', 'code', 'name']),
            'departments' => $this->departmentsFor($user),
            'products' => Product::query()
                ->where('group', 'CONSUMABLE')
                ->where('is_active', true)
                ->orderBy('name')
                ->with('departments:id')
                ->get(['id', 'code', 'name', 'inventory_tracking_mode'])
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'inventory_tracking_mode' => $product->inventory_tracking_mode,
                    'department_ids' => $product->departments->pluck('id')->all(),
                ]),
            'defaultBranchId' => $user->hasGlobalBranchAccess() ? null : $user->branch_id,
            'defaultDepartmentId' => $user->hasGlobalDepartmentAccess() ? null : $user->department_id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.request'), 403);

        $validated = $request->validate([
            'branch_id' => ['required', 'string', 'size:26', Rule::exists('mst_org_branches', 'id')->where('is_active', true)->whereNull('deleted_at'), Rule::in($this->branchAccess->branchIds($user))],
            'department_id' => ['required', 'string', 'size:26', Rule::exists('mst_org_departments', 'id')],
            'requested_at' => ['required', 'date'],
            'required_at' => ['nullable', 'date', 'after_or_equal:requested_at'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'string', 'size:26', Rule::exists('mst_products', 'id')->where('group', 'CONSUMABLE')->where('is_active', true)->whereNull('deleted_at'), Rule::exists('mst_product_departments', 'product_id')->where('department_id', $request->input('department_id'))],
            'items.*.requested_quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $department = $this->authorizedDepartment($user, $validated['department_id']);
        $branch = Branch::query()->findOrFail($validated['branch_id']);
        abort_unless($branch->company_id === $department->company_id, 422, 'Department and branch must belong to the same company.');
        $department->loadMissing('company:id,code');
        $products = Product::query()->whereIn('id', collect($validated['items'])->pluck('product_id'))->get()->keyBy('id');

        $id = (string) Str::ulid();
        $inventoryRequest = DB::transaction(function () use ($id, $validated, $department, $branch, $user, $products): InventoryRequest {
            $inventoryRequest = InventoryRequest::query()->create([
                'id' => $id,
                'number' => $this->numberingService->generate(
                    NumberingModuleCode::InventoryRequest,
                    [
                        'company' => $department->company?->code,
                        'department' => $department->code,
                        'branch' => $branch->code,
                        'document_type' => 'INVENTORY_REQUEST',
                    ],
                ),
                'department_id' => $department->id,
                'branch_id' => $branch->id,
                'requested_by' => $user->id,
                'status' => InventoryRequest::STATUS_DRAFT,
                'requested_at' => $validated['requested_at'],
                'required_at' => $validated['required_at'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $product = $products->get($item['product_id']);
                abort_unless($product?->group === 'CONSUMABLE' && $product->is_active, 422);
                $inventoryRequest->items()->create([
                    'product_id' => $product->id,
                    'requested_quantity' => $item['requested_quantity'],
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }

            return $inventoryRequest;
        });

        $this->activityLogger->log(
            action: 'CREATED',
            module: 'INVENTORY_REQUEST',
            description: "Permintaan barang {$inventoryRequest->number} dibuat.",
            subjectType: InventoryRequest::class,
            subjectId: $inventoryRequest->id,
            newValues: ['status' => $inventoryRequest->status, 'items_count' => count($validated['items'])],
        );

        return to_route('transaction.inventory.requests.show', $inventoryRequest)
            ->with('success', 'Permintaan barang berhasil disimpan sebagai draft.');
    }

    public function show(Request $request, InventoryRequest $inventoryRequest): Response
    {
        $user = $request->user();
        $approvalRequest = $inventoryRequest->approvalRequest;
        $isCurrentApprover = $approvalRequest !== null && $this->approvalService->canAct($approvalRequest, $user);
        abort_unless($user?->can('consumable.view') || $isCurrentApprover, 403);
        abort_unless(
            $this->canAccessDepartment($user, $inventoryRequest->department)
                || $inventoryRequest->requested_by === $user->id
                || $isCurrentApprover,
            404,
        );

        $inventoryRequest->load([
            'department:id,code,name,company_id',
            'branch:id,code,name',
            'requester:id,name',
            'items.product:id,code,name,inventory_tracking_mode',
            'approvalRequest.levels.approver:id,name',
            'approvalRequest.actions.user:id,name',
        ]);

        $mappings = InventoryDepartmentWarehouse::query()
            ->with('location:id,code,name')
            ->where('department_id', $inventoryRequest->department_id)
            ->whereHas('location', fn (Builder $query) => $query->where('branch_id', $inventoryRequest->branch_id))
            ->orderBy('id')
            ->get();
        $warehouses = $mappings->map(fn (InventoryDepartmentWarehouse $mapping): array => [
            'id' => $mapping->location?->id,
            'code' => $mapping->location?->code,
            'name' => $mapping->location?->name,
        ])->values();
        $users = User::query()
            ->where('department_id', $inventoryRequest->department_id)
            ->where(function (Builder $query) use ($inventoryRequest): void {
                $query->where('branch_id', $inventoryRequest->branch_id)
                    ->orWhereHas('additionalBranches', fn (Builder $branches) => $branches->whereKey($inventoryRequest->branch_id));
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $assets = Asset::query()
            ->where('department_id', $inventoryRequest->department_id)
            ->where('branch_id', $inventoryRequest->branch_id)
            ->where('status', Asset::STATUS_ACTIVE)
            ->orderBy('number')
            ->get(['id', 'number']);
        $locations = Location::query()
            ->where('company_id', $inventoryRequest->department->company_id)
            ->where('branch_id', $inventoryRequest->branch_id)
            ->where('is_active', true)
            ->whereRaw('LOWER(location_type) NOT IN (?, ?)', ['warehouse', 'gudang'])
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Transaction/Inventory/Requests/Show', [
            'inventoryRequest' => $inventoryRequest,
            'warehouses' => $warehouses,
            'users' => $users,
            'assets' => $assets,
            'locations' => $locations,
            'canSubmit' => $inventoryRequest->status === InventoryRequest::STATUS_DRAFT
                && ($inventoryRequest->requested_by === $user->id || $user->hasGlobalDepartmentAccess()),
            'canFulfill' => $user->can('consumable.issue')
                && $this->canAccessDepartment($user, $inventoryRequest->department)
                && $warehouses->isNotEmpty()
                && in_array($inventoryRequest->status, [InventoryRequest::STATUS_APPROVED, InventoryRequest::STATUS_PARTIALLY_FULFILLED], true),
        ]);
    }

    public function submit(Request $request, InventoryRequest $inventoryRequest): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.request'), 403);
        abort_unless($this->canAccessDepartment($user, $inventoryRequest->department), 404);

        try {
            DB::transaction(function () use ($inventoryRequest, $user): void {
                $lockedRequest = InventoryRequest::query()->lockForUpdate()->findOrFail($inventoryRequest->id);

                if (
                    $lockedRequest->status !== InventoryRequest::STATUS_DRAFT
                    || ($lockedRequest->requested_by !== $user->id && ! $user->hasGlobalDepartmentAccess())
                ) {
                    abort(403);
                }

                $department = Department::query()->findOrFail($lockedRequest->department_id);
                $approvalRequest = $this->approvalService->submit(
                    module: 'INVENTORY_REQUEST',
                    documentType: 'INVENTORY_REQUEST',
                    documentId: $lockedRequest->id,
                    submittedBy: $user,
                    companyId: $department->company_id,
                    departmentId: $department->id,
                );

                $lockedRequest->update([
                    'approval_request_id' => $approvalRequest->id,
                    'status' => InventoryRequest::STATUS_PENDING_APPROVAL,
                    'submitted_at' => now(),
                ]);
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        $this->activityLogger->log(
            action: 'SUBMITTED',
            module: 'INVENTORY_REQUEST',
            description: "Permintaan barang {$inventoryRequest->number} diajukan untuk persetujuan.",
            subjectType: InventoryRequest::class,
            subjectId: $inventoryRequest->id,
            newValues: ['status' => InventoryRequest::STATUS_PENDING_APPROVAL],
        );

        return back()->with('success', 'Permintaan barang diajukan ke atasan untuk disetujui.');
    }

    public function fulfill(Request $request, InventoryRequest $inventoryRequest): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.issue'), 403);
        abort_unless($this->canAccessDepartment($user, $inventoryRequest->department), 404);

        $validated = $request->validate([
            'source_location_id' => ['required', 'string', 'size:26', Rule::exists('trx_inventory_department_warehouses', 'location_id')->where('department_id', $inventoryRequest->department_id)],
            'occurred_at' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.inventory_request_item_id' => ['required', 'string', 'size:26', Rule::exists('trx_inventory_request_items', 'id')->where('inventory_request_id', $inventoryRequest->id)],
            'items.*.product_id' => ['required', 'string', 'size:26', Rule::exists('mst_products', 'id')->where('group', 'CONSUMABLE')->where('is_active', true)->whereNull('deleted_at'), Rule::exists('mst_product_departments', 'product_id')->where('department_id', $inventoryRequest->department_id)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'items.*.serial_numbers' => ['nullable', 'array', 'max:500'],
            'items.*.serial_numbers.*' => ['required', 'string', 'max:150'],
            'items.*.party_type' => ['required', Rule::in(['USER', 'ASSET', 'LOCATION'])],
            'items.*.party_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'items.*.party_asset_id' => ['nullable', 'string', 'size:26', Rule::exists('trx_assets', 'id')],
            'items.*.party_location_id' => ['nullable', 'string', 'size:26', Rule::exists('mst_org_locations', 'id')],
            'items.*.specification_changes' => ['nullable', 'array', 'max:30'],
            'items.*.specification_changes.*' => ['nullable', 'string', 'max:150'],
        ]);

        $department = Department::query()->findOrFail($inventoryRequest->department_id);
        $document = $this->inventoryOperationService->issue($department, $user, $validated, $inventoryRequest);

        $this->activityLogger->log(
            action: 'FULFILLED',
            module: 'INVENTORY_REQUEST',
            description: "Pemenuhan permintaan {$inventoryRequest->number} melalui dokumen {$document->number}.",
            subjectType: InventoryRequest::class,
            subjectId: $inventoryRequest->id,
            newValues: ['inventory_document_id' => $document->id, 'inventory_document_number' => $document->number],
        );

        return back()->with('success', "Pemenuhan barang {$document->number} berhasil diposting.");
    }

    /** @return Builder<InventoryRequest> */
    private function requestQuery(User $user): Builder
    {
        return InventoryRequest::query()
            ->when(! $user->hasGlobalDepartmentAccess(), fn (Builder $query): Builder => $query->whereIn('department_id', $this->departmentAccess->departmentIds($user)));
    }

    /** @return Collection<int, Department> */
    private function departmentsFor(User $user): Collection
    {
        return Department::query()
            ->when(! $user->hasGlobalDepartmentAccess(), fn (Builder $query): Builder => $query->whereIn('id', $this->departmentAccess->departmentIds($user)))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'company_id', 'code', 'name']);
    }

    private function authorizedDepartment(User $user, string $departmentId): Department
    {
        $department = Department::query()->findOrFail($departmentId);
        abort_unless($this->canAccessDepartment($user, $department), 404);

        return $department;
    }

    private function canAccessDepartment(User $user, ?Department $department): bool
    {
        return $department !== null && $this->departmentAccess->canAccess($user, $department->id);
    }
}
