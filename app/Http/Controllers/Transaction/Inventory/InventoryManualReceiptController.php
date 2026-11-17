<?php

namespace App\Http\Controllers\Transaction\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\Inventory\MarkInventoryOpeningBalanceApprovedRequest;
use App\Models\Master\Organization\Department;
use App\Models\Master\Product\Product;
use App\Models\Transaction\Inventory\InventoryDepartmentWarehouse;
use App\Models\Transaction\Inventory\InventoryDocument;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Administrator\Workflow\ApprovalService;
use App\Services\DepartmentAccessService;
use App\Services\Transaction\Inventory\InventoryOpeningBalancePostingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class InventoryManualReceiptController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly InventoryOpeningBalancePostingService $openingBalancePostingService,
        private readonly ActivityLogger $activityLogger,
        private readonly DepartmentAccessService $departmentAccess,
    ) {}

    public function create(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.update'), 403);

        $departments = $this->departmentQuery($user)->with('company:id,name')->orderBy('name')->get();
        $departmentId = $this->selectedDepartmentId($request, $user, $departments->pluck('id')->all());
        $department = $departmentId === null ? null : $departments->firstWhere('id', $departmentId);

        return Inertia::render('Transaction/Inventory/Receipts/ManualCreate', [
            'departments' => $departments->map(fn (Department $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'company' => $item->company?->name,
            ]),
            'departmentId' => $departmentId,
            'warehouses' => $department === null
                ? []
                : InventoryDepartmentWarehouse::query()
                    ->with('location:id,code,name')
                    ->where('department_id', $department->id)
                    ->get()
                    ->map(fn (InventoryDepartmentWarehouse $mapping): ?array => $mapping->location?->only(['id', 'code', 'name']))
                    ->filter()
                    ->values(),
            'products' => Product::query()
                ->where('group', 'CONSUMABLE')
                ->where('is_active', true)
                ->whereHas('departments', fn (Builder $query) => $query->whereKey($department?->id ?? ''))
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'inventory_tracking_mode']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->can('consumable.update'), 403);

        $validated = $request->validate([
            'department_id' => ['required', 'string', 'size:26', Rule::exists('mst_org_departments', 'id')->whereNull('deleted_at')],
            'location_id' => ['required', 'string', 'size:26', Rule::exists('inventory_department_warehouses', 'location_id')],
            'occurred_at' => ['required', 'date'],
            'remarks' => ['required', 'string', 'max:2000'],
            'approval_mode' => ['required', Rule::in(['WORKFLOW', 'MANUAL'])],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'string', 'size:26', Rule::exists('mst_products', 'id')->where('group', 'CONSUMABLE')->where('is_active', true)->whereNull('deleted_at'), Rule::exists('product_department', 'product_id')->where('department_id', $request->input('department_id'))],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'items.*.serial_numbers' => ['nullable', 'array', 'max:500'],
            'items.*.serial_numbers.*' => ['required', 'string', 'max:150'],
            'items.*.reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $department = $this->authorizedDepartment($user, $validated['department_id']);
        if (! InventoryDepartmentWarehouse::query()
            ->where('department_id', $department->id)
            ->where('location_id', $validated['location_id'])
            ->exists()) {
            throw ValidationException::withMessages(['location_id' => 'Gudang tidak terdaftar untuk departemen ini.']);
        }

        $products = Product::query()
            ->whereIn('id', collect($validated['items'])->pluck('product_id')->unique())
            ->get()
            ->keyBy('id');
        $normalizedItems = $this->normalizeItems($validated['items'], $products);

        try {
            $document = DB::transaction(function () use ($validated, $department, $normalizedItems, $user): InventoryDocument {
                $id = (string) Str::ulid();
                $document = InventoryDocument::query()->create([
                    'id' => $id,
                    'number' => 'IMR-'.now()->format('Ymd').'-'.strtoupper(substr($id, -8)),
                    'department_id' => $department->id,
                    'document_type' => InventoryDocument::TYPE_OPENING_BALANCE,
                    'status' => InventoryDocument::STATUS_PENDING_APPROVAL,
                    'source_location_id' => $validated['location_id'],
                    'adjustment_direction' => 'IN',
                    'occurred_at' => $validated['occurred_at'],
                    'remarks' => $validated['remarks'],
                    'performed_by' => $user->id,
                    'submitted_at' => now(),
                    'approval_mode' => $validated['approval_mode'],
                ]);

                foreach ($normalizedItems as $item) {
                    $document->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'serial_numbers' => $item['serial_numbers'],
                        'reason' => $item['reason'],
                    ]);
                }

                if ($validated['approval_mode'] === 'WORKFLOW') {
                    $approvalRequest = $this->approvalService->submit(
                        module: 'INVENTORY_OPENING_BALANCE',
                        documentType: 'INVENTORY_OPENING_BALANCE',
                        documentId: $document->id,
                        submittedBy: $user,
                        companyId: $department->company_id,
                        departmentId: $department->id,
                    );

                    $document->update(['approval_request_id' => $approvalRequest->id]);
                }

                return $document;
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()])->withInput();
        }

        $this->activityLogger->log(
            action: 'SUBMITTED',
            module: 'INVENTORY_OPENING_BALANCE',
            description: $document->approval_mode === 'WORKFLOW'
                ? "Penerimaan manual {$document->number} diajukan melalui workflow approval."
                : "Penerimaan manual {$document->number} menunggu Mark Approval.",
            subjectType: InventoryDocument::class,
            subjectId: $document->id,
            newValues: ['status' => $document->status, 'approval_mode' => $document->approval_mode, 'items_count' => count($normalizedItems)],
        );

        return to_route('transaction.inventory.receipts.manual.show', $document)
            ->with('success', 'Penerimaan manual diajukan untuk persetujuan. Stok belum berubah.');
    }

    public function show(Request $request, InventoryDocument $inventoryDocument): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);
        abort_unless($inventoryDocument->document_type === InventoryDocument::TYPE_OPENING_BALANCE, 404);

        $approvalRequest = $inventoryDocument->approvalRequest;
        $isCurrentApprover = $approvalRequest !== null && $this->approvalService->canAct($approvalRequest, $user);
        abort_unless($user?->can('consumable.view') || $isCurrentApprover, 403);
        abort_unless(
            $this->canAccessDepartment($user, $inventoryDocument->department)
                || $inventoryDocument->performed_by === $user->id
                || $isCurrentApprover,
            404,
        );

        $inventoryDocument->load([
            'department:id,code,name',
            'sourceLocation:id,code,name',
            'performedBy:id,name',
            'postedBy:id,name',
            'manualApprover:id,name',
            'items.product:id,code,name,inventory_tracking_mode',
            'approvalRequest.workflow',
            'approvalRequest.submitter:id,name',
            'approvalRequest.manualApprover:id,name',
            'approvalRequest.levels.approver:id,name',
            'approvalRequest.actions.user:id,name',
        ]);

        return Inertia::render('Transaction/Inventory/Receipts/ManualShow', [
            'receipt' => [
                'id' => $inventoryDocument->id,
                'number' => $inventoryDocument->number,
                'status' => $inventoryDocument->status,
                'approval_mode' => $inventoryDocument->approval_mode,
                'occurred_at' => $inventoryDocument->occurred_at->toIso8601String(),
                'submitted_at' => $inventoryDocument->submitted_at?->toIso8601String(),
                'posted_at' => $inventoryDocument->posted_at?->toIso8601String(),
                'remarks' => $inventoryDocument->remarks,
                'department' => $inventoryDocument->department?->only(['id', 'code', 'name']),
                'location' => $inventoryDocument->sourceLocation?->only(['id', 'code', 'name']),
                'submitted_by' => $inventoryDocument->performedBy?->only(['id', 'name']),
                'posted_by' => $inventoryDocument->postedBy?->only(['id', 'name']),
                'manual_approval_remarks' => $inventoryDocument->manual_approval_remarks,
                'manual_approved_at' => $inventoryDocument->manual_approved_at?->toIso8601String(),
                'manual_approver' => $inventoryDocument->manualApprover?->only(['id', 'name']),
                'has_manual_approval_document' => filled($inventoryDocument->manual_approval_document_path),
                'items' => $inventoryDocument->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'product' => $item->product?->only(['id', 'code', 'name', 'inventory_tracking_mode']),
                    'quantity' => (float) $item->quantity,
                    'serial_numbers' => $item->serial_numbers ?? [],
                    'reason' => $item->reason,
                ])->values(),
            ],
            'approval' => $inventoryDocument->approvalRequest === null ? null : [
                'workflow_name' => $inventoryDocument->approvalRequest->workflow?->name,
                'status' => $inventoryDocument->approvalRequest->status->value,
                'current_level' => $inventoryDocument->approvalRequest->current_level,
                'levels' => $inventoryDocument->approvalRequest->levels->map(fn ($level): array => [
                    'level' => $level->level,
                    'status' => $level->status->value,
                    'approver' => $level->approver?->name,
                    'acted_at' => $level->acted_at?->toIso8601String(),
                    'remarks' => $level->remarks,
                ])->values(),
                'manual_approval_remarks' => null,
                'manual_approved_at' => null,
                'manual_approver' => null,
                'has_manual_approval_document' => false,
            ],
            'canManualApprove' => $inventoryDocument->approval_mode === 'MANUAL'
                && $inventoryDocument->status === InventoryDocument::STATUS_PENDING_APPROVAL
                && ($inventoryDocument->performed_by === $user->id || $user->hasGlobalDepartmentAccess()),
            'canActInWorkflow' => $isCurrentApprover,
        ]);
    }

    public function markApproved(
        MarkInventoryOpeningBalanceApprovedRequest $request,
        InventoryDocument $inventoryDocument,
    ): RedirectResponse {
        $user = $request->user();
        abort_if($user === null, 401);

        $documentPath = $request->file('signed_document')->store(
            "inventory-opening-balance-approvals/{$inventoryDocument->id}",
            'local',
        );

        if ($documentPath === false) {
            return back()->with('error', 'Dokumen persetujuan gagal disimpan. Silakan coba kembali.');
        }

        try {
            DB::transaction(function () use ($inventoryDocument, $user, $documentPath, $request): void {
                $document = InventoryDocument::query()->lockForUpdate()->findOrFail($inventoryDocument->id);

                if (
                    $document->approval_mode !== 'MANUAL'
                    || $document->document_type !== InventoryDocument::TYPE_OPENING_BALANCE
                    || $document->status !== InventoryDocument::STATUS_PENDING_APPROVAL
                    || ($document->performed_by !== $user->id && ! $user->hasGlobalDepartmentAccess())
                ) {
                    abort(403);
                }

                $document->update([
                    'manual_approval_document_path' => $documentPath,
                    'manual_approval_remarks' => trim($request->validated('remarks')),
                    'manual_approved_by' => $user->id,
                    'manual_approved_at' => now(),
                ]);

                $this->openingBalancePostingService->post($document, $user);
            });
        } catch (ValidationException $exception) {
            Storage::disk('local')->delete($documentPath);

            throw $exception;
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($documentPath);
            report($exception);

            return back()->with('error', 'Mark Approval gagal diproses. Muat ulang halaman dan coba kembali.');
        }

        return to_route('transaction.inventory.receipts.manual.show', $inventoryDocument)
            ->with('success', 'Persetujuan manual dicatat dan stok saldo awal diposting.');
    }

    public function downloadApprovalDocument(Request $request, InventoryDocument $inventoryDocument): StreamedResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);
        abort_unless($inventoryDocument->document_type === InventoryDocument::TYPE_OPENING_BALANCE, 404);
        abort_unless(
            $this->canAccessDepartment($user, $inventoryDocument->department)
                || $inventoryDocument->performed_by === $user->id
                || $user->hasGlobalDepartmentAccess(),
            403,
        );

        $path = $inventoryDocument->manual_approval_document_path;
        abort_unless(filled($path), 404);

        return Storage::disk('local')->download($path);
    }

    /** @param array<int, array<string, mixed>> $items
     * @param  Collection<string, Product>  $products
     * @return array<int, array{product_id: string, quantity: float, serial_numbers: array<int, string>, reason: ?string}>
     */
    private function normalizeItems(array $items, Collection $products): array
    {
        $normalized = [];
        $seenSerials = [];

        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            abort_unless($product !== null && $product->group === 'CONSUMABLE' && $product->is_active, 422);
            $serials = collect($item['serial_numbers'] ?? [])
                ->map(fn (string $serial): string => mb_strtoupper(trim($serial)))
                ->filter()
                ->values()
                ->all();
            $quantity = (float) $item['quantity'];

            if ($product->inventory_tracking_mode === 'SERIAL') {
                if (
                    $quantity !== (float) (int) $quantity
                    || count($serials) !== (int) $quantity
                    || count($serials) !== count(array_unique($serials))
                ) {
                    throw ValidationException::withMessages([
                        'items' => "Masukkan nomor seri unik sebanyak kuantitas produk {$product->code}.",
                    ]);
                }

                foreach ($serials as $serial) {
                    $serialKey = $product->id.':'.$serial;
                    if (isset($seenSerials[$serialKey])) {
                        throw ValidationException::withMessages(['items' => "Nomor seri {$serial} tercantum lebih dari satu kali."]);
                    }
                    $seenSerials[$serialKey] = true;
                }
            } elseif ($serials !== []) {
                throw ValidationException::withMessages(['items' => "Produk {$product->code} tidak menggunakan pelacakan nomor seri."]);
            }

            $normalized[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'serial_numbers' => $serials,
                'reason' => $item['reason'] ?? null,
            ];
        }

        return $normalized;
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

    private function authorizedDepartment(User $user, string $departmentId): Department
    {
        $department = Department::query()->findOrFail($departmentId);
        abort_unless($this->departmentAccess->canAccess($user, $department->id), 404);

        return $department;
    }

    private function canAccessDepartment(User $user, ?Department $department): bool
    {
        return $department !== null && $this->departmentAccess->canAccess($user, $department->id);
    }
}
