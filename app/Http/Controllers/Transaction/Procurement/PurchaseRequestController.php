<?php

namespace App\Http\Controllers\Transaction\Procurement;

use App\Enums\Administrator\Workflow\ApprovalStatus;
use App\Enums\NumberingModuleCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\Procurement\MarkPurchaseRequestApprovedRequest;
use App\Http\Requests\Transaction\Procurement\PurchaseRequestRequest;
use App\Models\Administrator\Workflow\ApprovalAction;
use App\Models\Administrator\Workflow\ApprovalRequest;
use App\Models\Administrator\Workflow\ApprovalRequestLevel;
use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Procurement\ServiceType;
use App\Models\Master\Procurement\Tax;
use App\Models\Master\Procurement\UnitOfMeasure;
use App\Models\Master\Procurement\Vendor;
use App\Models\Master\Product\Product;
use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use App\Models\Transaction\Procurement\PurchaseRequest;
use App\Services\Administrator\Workflow\ApprovalService;
use App\Services\BranchAccessService;
use App\Services\DepartmentAccessService;
use App\Services\NumberingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly NumberingService $numberingService,
        private readonly ApprovalService $approvalService,
        private readonly DepartmentAccessService $departmentAccess,
        private readonly BranchAccessService $branchAccess,
    ) {}

    public function index(Request $request): Response
    {
        $requestedPerPage = $request->integer('per_page', 15);
        $perPage = in_array($requestedPerPage, [5, 15, 25, 50, 100], true) ? $requestedPerPage : 15;

        $purchaseRequests = $this->departmentAccess->scopeByDepartment(
            PurchaseRequest::query(),
            $request->user(),
        )
            ->with([
                'requester',
                'company',
                'department',
            ])
            ->when(
                $request->string('search')->trim()->isNotEmpty(),
                function ($query) use ($request): void {
                    $search = $request->string('search')->trim()->toString();

                    $query->where(function ($query) use ($search): void {
                        $query
                            ->where('number', 'ilike', "%{$search}%")
                            ->orWhere('purpose', 'ilike', "%{$search}%")
                            ->orWhereHas('requester', function ($query) use ($search): void {
                                $query->where('name', 'ilike', "%{$search}%");
                            });
                    });
                },
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->string('status')->toString(),
                ),
            )
            ->latest('request_date')
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $user = $request->user();
        $isAdministrator = $user?->hasGlobalDepartmentAccess() ?? false;
        $approvalRequests = ApprovalRequest::query()
            ->where('module', 'PURCHASE_REQUEST')
            ->where('document_type', 'PURCHASE_REQUEST')
            ->whereIn('document_id', $purchaseRequests->getCollection()->modelKeys())
            ->withExists('actions')
            ->withExists([
                'levels as has_completed_level' => fn ($query) => $query
                    ->whereIn('status', [
                        ApprovalStatus::APPROVED->value,
                        ApprovalStatus::REJECTED->value,
                    ]),
            ])
            ->get()
            ->keyBy('document_id');

        $purchaseRequests->getCollection()->transform(function (PurchaseRequest $purchaseRequest) use (
            $approvalRequests,
            $user,
            $isAdministrator,
        ): PurchaseRequest {
            $approvalRequest = $approvalRequests->get($purchaseRequest->id);
            $isOwner = $user !== null && $purchaseRequest->requester_id === $user->id;
            $canManage = $isOwner || $isAdministrator;
            $isAwaitingApproval = $approvalRequest === null
                || $approvalRequest->status === ApprovalStatus::PENDING;
            $hasApprovalActivity = $approvalRequest !== null
                && ($approvalRequest->actions_exists || $approvalRequest->has_completed_level);

            $purchaseRequest->setAttribute(
                'can_edit',
                $purchaseRequest->status === 'DRAFT' && $isOwner,
            );
            $purchaseRequest->setAttribute(
                'can_delete',
                $purchaseRequest->status === 'DRAFT' && $canManage,
            );
            $purchaseRequest->setAttribute(
                'can_print',
                $purchaseRequest->status === 'SUBMITTED',
            );
            $purchaseRequest->setAttribute(
                'can_cancel',
                $purchaseRequest->status === 'SUBMITTED'
                    && $canManage
                    && $isAwaitingApproval
                    && ! $hasApprovalActivity,
            );
            $purchaseRequest->setAttribute(
                'is_locked',
                in_array($purchaseRequest->status, ['APPROVED', 'CANCELLED', 'REJECTED'], true),
            );

            return $purchaseRequest;
        });

        return Inertia::render('Transaction/Procurement/PurchaseRequest/Index', [
            'purchaseRequests' => $purchaseRequests,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString(),
            ],
        ]);
    }

    public function destroy(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);
        $this->departmentAccess->authorize($user, $purchaseRequest->department_id);
        abort_unless(
            $purchaseRequest->requester_id === $user->id
                || $user->hasGlobalDepartmentAccess(),
            403,
        );

        DB::transaction(function () use ($purchaseRequest): void {
            $lockedPurchaseRequest = PurchaseRequest::query()
                ->lockForUpdate()
                ->findOrFail($purchaseRequest->id);

            abort_unless($lockedPurchaseRequest->status === 'DRAFT', 403);

            $lockedPurchaseRequest->delete();
        });

        return redirect()
            ->route('transaction.procurement.purchase-requests.index')
            ->with('success', 'Draft Purchase Request berhasil dihapus.');
    }

    public function cancel(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);
        $this->departmentAccess->authorize($user, $purchaseRequest->department_id);
        abort_unless(
            $purchaseRequest->requester_id === $user->id
                || $user->hasGlobalDepartmentAccess(),
            403,
        );

        DB::transaction(function () use ($purchaseRequest): void {
            $lockedPurchaseRequest = PurchaseRequest::query()
                ->lockForUpdate()
                ->findOrFail($purchaseRequest->id);

            abort_unless($lockedPurchaseRequest->status === 'SUBMITTED', 403);

            $approvalRequest = ApprovalRequest::query()
                ->where('module', 'PURCHASE_REQUEST')
                ->where('document_type', 'PURCHASE_REQUEST')
                ->where('document_id', $lockedPurchaseRequest->id)
                ->lockForUpdate()
                ->first();

            if ($approvalRequest !== null) {
                abort_unless($approvalRequest->status === ApprovalStatus::PENDING, 403);

                $hasApprovalActions = ApprovalAction::query()
                    ->where('approval_request_id', $approvalRequest->id)
                    ->exists();
                $hasCompletedLevel = ApprovalRequestLevel::query()
                    ->where('approval_request_id', $approvalRequest->id)
                    ->whereIn('status', [
                        ApprovalStatus::APPROVED->value,
                        ApprovalStatus::REJECTED->value,
                    ])
                    ->exists();

                abort_unless(! $hasApprovalActions && ! $hasCompletedLevel, 403);

                ApprovalRequestLevel::query()
                    ->where('approval_request_id', $approvalRequest->id)
                    ->get()
                    ->each(fn (ApprovalRequestLevel $level): bool => $level->update([
                        'status' => ApprovalStatus::CANCELLED->value,
                    ]));

                $approvalRequest->update([
                    'status' => ApprovalStatus::CANCELLED,
                    'current_level' => null,
                    'completed_at' => now(),
                ]);
            }

            $lockedPurchaseRequest->update(['status' => 'CANCELLED']);
        });

        return redirect()
            ->route('transaction.procurement.purchase-requests.index')
            ->with('success', 'Purchase Request berhasil dibatalkan.');
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $user->loadMissing([
            'company:id,code,name',
            'department:id,company_id,code,name',
        ]);

        $maintenanceRecord = null;
        $prefillItem = null;
        $company = $user->company;
        $department = $user->department;

        if ($request->filled('maintenance_id')) {
            $maintenanceRecord = AssetMaintenanceRecord::query()
                ->with([
                    'asset:id,number,company_id,department_id,product_id',
                    'asset.company:id,code,name',
                    'asset.department:id,company_id,code,name',
                    'asset.product:id,code,name',
                ])
                ->findOrFail($request->string('maintenance_id')->toString());

            abort_unless(
                $maintenanceRecord->status === AssetMaintenanceRecord::STATUS_WAITING_PARTS
                    && ($maintenanceRecord->requested_by === $user->id || $user->hasGlobalDepartmentAccess()),
                403,
            );
            $this->departmentAccess->authorize($user, $maintenanceRecord->asset->department_id);

            $existingPurchaseRequest = $maintenanceRecord->purchaseRequests()
                ->whereNotIn('status', ['CANCELLED', 'REJECTED'])
                ->latest('created_at')
                ->first();

            if ($existingPurchaseRequest !== null) {
                return redirect()->route(
                    $existingPurchaseRequest->status === 'DRAFT'
                        ? 'transaction.procurement.purchase-requests.edit'
                        : 'transaction.procurement.purchase-requests.show',
                    $existingPurchaseRequest,
                );
            }

            $company = $maintenanceRecord->asset->company;
            $department = $maintenanceRecord->asset->department;
        }

        if ($request->filled('product_id')) {
            $prefill = $request->validate([
                'product_id' => ['required', 'string', 'size:26'],
                'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            ]);
            $product = Product::query()
                ->where('group', 'CONSUMABLE')
                ->where('is_active', true)
                ->findOrFail($prefill['product_id']);
            abort_unless(
                $this->departmentAccess->hasGlobalAccess($user)
                    || $product->departments()->whereIn('mst_org_departments.id', $this->departmentAccess->departmentIds($user))->exists(),
                404,
            );
            $prefillItem = [
                'product_id' => $product->id,
                'quantity' => (float) $prefill['quantity'],
                'uom_id' => $product->base_uom_id,
            ];
        }

        return Inertia::render('Transaction/Procurement/PurchaseRequest/Create', [
            ...$this->masterDataProps($request),
            'requester' => $user->only(['id', 'name']),
            'company' => $company?->only(['id', 'code', 'name']),
            'department' => $department?->only(['id', 'company_id', 'code', 'name']),
            'maintenanceRecord' => $maintenanceRecord === null ? null : [
                'id' => $maintenanceRecord->id,
                'number' => $maintenanceRecord->number,
                'issue_description' => $maintenanceRecord->issue_description,
                'asset' => [
                    'number' => $maintenanceRecord->asset->number,
                    'product' => $maintenanceRecord->asset->product?->only(['code', 'name']),
                ],
            ],
            'prefillItem' => $prefillItem,
        ]);
    }

    public function edit(Request $request, PurchaseRequest $purchaseRequest): Response
    {
        $this->ensureDraftOwner($request, $purchaseRequest);

        $purchaseRequest->load([
            'company',
            'department',
            'items.product',
            'items.serviceType',
            'items.uom',
            'items.vendor',
        ]);

        $branches = $this->branchAccess->scopeBranches(Branch::query(), $request->user())
            ->where('company_id', $purchaseRequest->company_id)
            ->get(['id', 'name', 'address']);
        $branchIdsByLocation = [];

        foreach ($branches as $branch) {
            if ($branch->address !== null) {
                $branchIdsByLocation[$branch->address] = $branch->id;
            }

            $branchIdsByLocation[$branch->name] ??= $branch->id;
        }

        return Inertia::render('Transaction/Procurement/PurchaseRequest/Create', [
            ...$this->masterDataProps($request),
            'requester' => $request->user()->only(['id', 'name']),
            'company' => $purchaseRequest->company?->only(['id', 'code', 'name']),
            'department' => $purchaseRequest->department?->only(['id', 'company_id', 'code', 'name']),
            'purchaseRequest' => [
                'id' => $purchaseRequest->id,
                'request_date' => $purchaseRequest->getRawOriginal('request_date'),
                'company_id' => $purchaseRequest->company_id,
                'department_id' => $purchaseRequest->department_id,
                'purpose' => $purchaseRequest->purpose,
                'remarks' => $purchaseRequest->remarks,
                'items' => $purchaseRequest->items->map(fn ($item): array => [
                    'item_type' => $item->item_type,
                    'product_id' => $item->product_id,
                    'service_type_id' => $item->service_type_id,
                    'asset_id' => $item->asset_id,
                    'description' => $item->description ?? '',
                    'quantity' => (float) $item->quantity,
                    'uom_id' => $item->uom_id,
                    'estimated_unit_price' => (float) ($item->estimated_unit_price ?? 0),
                    'vendor_id' => $item->vendor_id,
                    'vendor_pic' => $item->vendor_pic ?? '',
                    'vendor_contact' => $item->vendor_contact ?? '',
                    'vendor_url' => $item->vendor_url ?? '',
                    'required_date' => $item->required_date?->format('Y-m-d'),
                    'delivery_branch_id' => $item->branch_id ?? $branchIdsByLocation[$item->delivery_address] ?? null,
                    'remarks' => $item->remarks ?? '',
                    'requires_po' => $item->requires_po,
                    'tax_included' => $item->tax_included,
                    'tax_id' => $item->tax_id,
                ])->all(),
            ],
        ]);
    }

    public function store(
        PurchaseRequestRequest $request,
    ): RedirectResponse {
        $user = $request->user();
        $validated = $request->validated();

        $purchaseRequest = DB::transaction(function () use (
            $request,
            $user,
            $validated,
        ): PurchaseRequest {
            $maintenanceRecordId = $validated['asset_maintenance_record_id'] ?? null;
            if ($maintenanceRecordId !== null) {
                $maintenanceRecord = AssetMaintenanceRecord::query()
                    ->with('asset:id,company_id,department_id')
                    ->lockForUpdate()
                    ->findOrFail($maintenanceRecordId);

                abort_unless(
                    $maintenanceRecord->status === AssetMaintenanceRecord::STATUS_WAITING_PARTS
                        && ($maintenanceRecord->requested_by === $user->id || $user->hasGlobalDepartmentAccess()),
                    403,
                );
                $this->departmentAccess->authorize($user, $maintenanceRecord->asset->department_id);

                abort_unless(
                    $maintenanceRecord->asset->company_id === $validated['company_id']
                        && $maintenanceRecord->asset->department_id === $validated['department_id'],
                    403,
                );

                $hasOpenPurchaseRequest = $maintenanceRecord->purchaseRequests()
                    ->whereNotIn('status', ['CANCELLED', 'REJECTED'])
                    ->exists();

                if ($hasOpenPurchaseRequest) {
                    throw ValidationException::withMessages([
                        'asset_maintenance_record_id' => 'Maintenance ini sudah memiliki Purchase Request yang aktif.',
                    ]);
                }
            }

            $purchaseRequest = PurchaseRequest::create([
                'number' => 'DRAFT-'.now()->format('YmdHis').'-'.$user->id,
                'request_date' => $request->date('request_date'),
                'requester_id' => $user->id,
                'asset_maintenance_record_id' => $maintenanceRecordId,
                'company_id' => $validated['company_id'],
                'department_id' => $validated['department_id'],
                'purpose' => $request->string('purpose')->trim()->toString(),
                'remarks' => $request->input('remarks'),
                'status' => 'DRAFT',
            ]);

            $this->syncItems($purchaseRequest, $validated['items'], $validated['company_id']);

            return $purchaseRequest;
        });

        return redirect()
            ->route('transaction.procurement.purchase-requests.show', $purchaseRequest)
            ->with('success', 'Purchase Request berhasil disimpan sebagai draft.');
    }

    public function update(
        PurchaseRequestRequest $request,
        PurchaseRequest $purchaseRequest,
    ): RedirectResponse {
        $this->ensureDraftOwner($request, $purchaseRequest);
        $validated = $request->validated();

        if ($purchaseRequest->asset_maintenance_record_id !== null) {
            $maintenanceRecord = AssetMaintenanceRecord::query()
                ->with('asset:id,company_id,department_id')
                ->findOrFail($purchaseRequest->asset_maintenance_record_id);

            if (
                $maintenanceRecord->asset->company_id !== $validated['company_id']
                || $maintenanceRecord->asset->department_id !== $validated['department_id']
            ) {
                throw ValidationException::withMessages([
                    'company_id' => 'Company dan department PR harus mengikuti organisasi pada aset Maintenance.',
                ]);
            }
        }

        DB::transaction(function () use ($purchaseRequest, $validated): void {
            $purchaseRequest->update([
                'request_date' => $validated['request_date'],
                'company_id' => $validated['company_id'],
                'department_id' => $validated['department_id'],
                'purpose' => trim($validated['purpose']),
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $this->syncItems($purchaseRequest, $validated['items'], $validated['company_id']);
        });

        return redirect()
            ->route('transaction.procurement.purchase-requests.show', $purchaseRequest)
            ->with('success', 'Draft Purchase Request berhasil diperbarui.');
    }

    public function show(Request $request, PurchaseRequest $purchaseRequest): Response
    {
        $this->departmentAccess->authorize($request->user(), $purchaseRequest->department_id);
        $purchaseRequest->load([
            'requester.position:id,name',
            'company',
            'department',
            'items.product',
            'items.serviceType',
            'items.uom',
            'items.vendor',
        ]);

        $approvalRequest = ApprovalRequest::query()
            ->with([
                'workflow',
                'levels.approver:id,name,position_id',
                'levels.approver.position:id,name',
                'manualApprover:id,name',
            ])
            ->where('module', 'PURCHASE_REQUEST')
            ->where('document_type', 'PURCHASE_REQUEST')
            ->where('document_id', $purchaseRequest->id)
            ->latest('created_at')
            ->first();

        $user = $request->user();
        $canManageManualApproval = $user !== null
            && ($purchaseRequest->requester_id === $user->id
                || $user->hasGlobalDepartmentAccess());

        return Inertia::render(
            'Transaction/Procurement/PurchaseRequest/Show',
            [
                'purchaseRequest' => $purchaseRequest,
                'canMarkApproval' => $canManageManualApproval
                    && $purchaseRequest->status === 'SUBMITTED'
                    && $approvalRequest?->status === ApprovalStatus::PENDING,
                'approval' => $approvalRequest === null ? null : [
                    'workflow_name' => $approvalRequest->workflow?->name,
                    'status' => $approvalRequest->status->value,
                    'current_level' => $approvalRequest->current_level,
                    'levels' => $approvalRequest->levels->map(fn ($level): array => [
                        'level' => $level->level,
                        'status' => $level->status->value,
                        'approver' => $level->approver === null ? null : [
                            'id' => $level->approver->id,
                            'name' => $level->approver->name,
                            'position' => $level->approver->position?->only(['name']),
                        ],
                        'acted_at' => $level->acted_at?->toIso8601String(),
                        'remarks' => $level->remarks,
                    ])->all(),
                    'manual_approval_remarks' => $approvalRequest->manual_approval_remarks,
                    'manual_approved_at' => $approvalRequest->manual_approved_at?->toIso8601String(),
                    'manual_approver' => $approvalRequest->manualApprover?->only(['id', 'name']),
                    'has_manual_approval_document' => filled(
                        $approvalRequest->manual_approval_document_path,
                    ),
                ],
            ],
        );
    }

    public function markApproved(
        MarkPurchaseRequestApprovedRequest $request,
        PurchaseRequest $purchaseRequest,
    ): RedirectResponse {
        $this->departmentAccess->authorize($request->user(), $purchaseRequest->department_id);
        if ($purchaseRequest->status !== 'SUBMITTED') {
            return back()->with(
                'error',
                'Mark approval hanya tersedia untuk Purchase Request berstatus SUBMITTED.',
            );
        }

        $approvalRequest = ApprovalRequest::query()
            ->where('module', 'PURCHASE_REQUEST')
            ->where('document_type', 'PURCHASE_REQUEST')
            ->where('document_id', $purchaseRequest->id)
            ->latest('created_at')
            ->firstOrFail();

        $user = $request->user();
        abort_if($user === null, 401);

        $documentPath = $request->file('signed_document')->store(
            "purchase-request-approvals/{$purchaseRequest->id}",
            'local',
        );

        if ($documentPath === false) {
            return back()->with(
                'error',
                'Dokumen approval gagal disimpan. Silakan coba kembali.',
            );
        }

        try {
            $this->approvalService->manuallyApprovePurchaseRequest(
                $approvalRequest,
                $user,
                $documentPath,
                trim($request->validated('remarks')),
            );
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($documentPath);
            report($exception);

            return back()->with(
                'error',
                'Mark approval gagal diproses. Muat ulang halaman dan coba kembali.',
            );
        }

        return redirect()
            ->route('transaction.procurement.purchase-requests.show', $purchaseRequest)
            ->with(
                'success',
                'Purchase Request berhasil ditandai approved dan seluruh level workflow telah diselesaikan.',
            );
    }

    public function downloadApprovalDocument(
        Request $request,
        PurchaseRequest $purchaseRequest,
    ): StreamedResponse {
        $user = $request->user();
        abort_if($user === null, 401);
        $this->departmentAccess->authorize($user, $purchaseRequest->department_id);
        abort_unless(
            $purchaseRequest->requester_id === $user->id
                || $user->hasGlobalDepartmentAccess(),
            403,
        );

        $approvalRequest = ApprovalRequest::query()
            ->where('module', 'PURCHASE_REQUEST')
            ->where('document_type', 'PURCHASE_REQUEST')
            ->where('document_id', $purchaseRequest->id)
            ->latest('created_at')
            ->firstOrFail();

        abort_unless(
            filled($approvalRequest->manual_approval_document_path),
            404,
        );

        return Storage::disk('local')->download(
            $approvalRequest->manual_approval_document_path,
        );
    }

    public function submit(
        Request $request,
        PurchaseRequest $purchaseRequest,
    ): RedirectResponse {
        $user = $request->user();
        abort_if($user === null, 401);
        $this->departmentAccess->authorize($user, $purchaseRequest->department_id);

        if ($purchaseRequest->requester_id !== $user->id) {
            abort(403);
        }

        if ($purchaseRequest->status !== 'DRAFT') {
            return back()->with(
                'error',
                'Purchase Request hanya dapat disubmit dari status DRAFT.',
            );
        }

        $hasUnavailablePoVendor = $purchaseRequest->items()
            ->where('requires_po', true)
            ->where(function ($query): void {
                $query->whereNull('vendor_id')
                    ->orWhereDoesntHave('vendor', fn ($vendorQuery) => $vendorQuery->where('is_active', true));
            })
            ->exists();

        if ($hasUnavailablePoVendor) {
            throw ValidationException::withMessages([
                'items' => 'Pilih vendor aktif pada setiap item yang membutuhkan PO sebelum mengajukan Purchase Request.',
            ]);
        }

        if ($purchaseRequest->asset_maintenance_record_id !== null) {
            $maintenanceRecord = AssetMaintenanceRecord::query()->findOrFail($purchaseRequest->asset_maintenance_record_id);
            $maintenanceItems = $purchaseRequest->items()
                ->get(['item_type', 'quantity', 'estimated_unit_price', 'tax_included', 'tax_rate', 'tax_category']);

            if ($maintenanceItems->isEmpty() || $maintenanceItems->contains(fn ($item): bool => $item->item_type !== 'PRODUCT'
                || $item->estimated_unit_price === null
                || (float) $item->estimated_unit_price <= 0
            )) {
                throw ValidationException::withMessages([
                    'items' => 'PR yang tertaut ke Maintenance harus berisi produk sparepart dengan estimasi harga satuan.',
                ]);
            }

            $estimatedPartsCost = $maintenanceItems
                ->sum(function ($item): float {
                    $amount = (float) $item->quantity * (float) $item->estimated_unit_price;
                    $rate = (float) ($item->tax_rate ?? 0) / 100;

                    if ($item->tax_category === 'WITHHOLDING') {
                        return $amount - round($amount * $rate);
                    }

                    return $amount + (! $item->tax_included ? round($amount * $rate) : 0);
                });

            if ($estimatedPartsCost > (float) $maintenanceRecord->estimated_cost) {
                throw ValidationException::withMessages([
                    'items' => 'Total estimasi PR sparepart melebihi batas estimasi biaya yang telah disetujui pada Maintenance.',
                ]);
            }
        }

        try {
            DB::transaction(function () use (
                $purchaseRequest,
                $user,
            ): void {
                $purchaseRequest->load(['company:id,code', 'department:id,code']);
                $branchIds = $purchaseRequest->items()
                    ->whereNotNull('branch_id')
                    ->distinct()
                    ->pluck('branch_id');
                $branchCode = $branchIds->count() === 1
                    ? Branch::query()->whereKey($branchIds->first())->value('code')
                    : 'MULTI';

                $number = $this->numberingService->generate(
                    NumberingModuleCode::PurchaseRequest,
                    [
                        'company' => $purchaseRequest->company?->code,
                        'department' => $purchaseRequest->department->code,
                        'branch' => $branchCode,
                        'document_type' => 'PR',
                    ],
                );

                $purchaseRequest->update([
                    'number' => $number,
                    'status' => 'SUBMITTED',
                    'submitted_at' => now(),
                ]);

                $this->approvalService->submit(
                    'PURCHASE_REQUEST',
                    'PURCHASE_REQUEST',
                    $purchaseRequest->id,
                    $user,
                    $purchaseRequest->company_id,
                    $purchaseRequest->department_id,
                );
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                $exception->getMessage(),
            );
        }

        return redirect()
            ->route(
                'transaction.procurement.purchase-requests.show',
                $purchaseRequest,
            )
            ->with(
                'success',
                'Purchase Request berhasil disubmit dan masuk ke approval workflow.',
            );
    }

    private function masterDataProps(Request $request): array
    {
        $user = $request->user();
        abort_if($user === null, 401);
        $departmentIds = $this->departmentAccess->departmentIds($user);
        $departments = $this->departmentAccess->scopeDepartments(Department::query(), $user)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'company_id', 'code', 'name']);

        return [
            'products' => Product::query()
                ->where('is_active', true)
                ->whereHas('departments', fn ($query) => $query->whereIn('mst_org_departments.id', $departmentIds))
                ->orderBy('name')
                ->with('departments:id')
                ->get(['id', 'code', 'name', 'group'])
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'group' => $product->group,
                    'department_ids' => $product->departments->pluck('id')->all(),
                ]),
            'companies' => Company::query()
                ->where('is_active', true)
                ->whereIn('id', $departments->pluck('company_id')->unique())
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'departments' => $departments,
            'branches' => $this->branchAccess->scopeBranches(Branch::query(), $user)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'company_id', 'code', 'name', 'address']),
            'serviceTypes' => ServiceType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'requires_asset']),
            'vendors' => Vendor::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'is_active']),
            'taxes' => Tax::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'rate', 'category']),
            'unitsOfMeasure' => UnitOfMeasure::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ];
    }

    private function ensureDraftOwner(Request $request, PurchaseRequest $purchaseRequest): void
    {
        $user = $request->user();

        abort_if($user === null, 401);
        $this->departmentAccess->authorize($user, $purchaseRequest->department_id);
        abort_unless(
            $purchaseRequest->requester_id === $user->id
                && $purchaseRequest->status === 'DRAFT',
            403,
        );
    }

    private function syncItems(PurchaseRequest $purchaseRequest, array $items, string $companyId): void
    {
        $branchIds = collect($items)
            ->pluck('delivery_branch_id')
            ->filter()
            ->unique();
        $branches = $this->branchAccess->scopeBranches(Branch::query(), auth()->user())
            ->where('company_id', $companyId)
            ->whereIn('id', $branchIds)
            ->get(['id', 'name', 'address'])
            ->keyBy('id');

        $purchaseRequest->items()->get()->each->delete();

        foreach ($items as $item) {
            $branch = $branches->get($item['delivery_branch_id'] ?? null);
            $tax = isset($item['tax_id']) ? Tax::query()->where('is_active', true)->find($item['tax_id']) : null;

            $purchaseRequest->items()->create([
                'branch_id' => $branch?->id,
                'item_type' => $item['item_type'],
                'product_id' => $item['product_id'] ?? null,
                'service_type_id' => $item['service_type_id'] ?? null,
                'asset_id' => $item['asset_id'] ?? null,
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'],
                'uom_id' => $item['uom_id'],
                'estimated_unit_price' => $item['estimated_unit_price'] ?? null,
                'vendor_id' => $item['vendor_id'] ?? null,
                'vendor_pic' => $item['vendor_pic'] ?? null,
                'vendor_contact' => $item['vendor_contact'] ?? null,
                'vendor_url' => $item['vendor_url'] ?? null,
                'requires_po' => $item['requires_po'] ?? false,
                'tax_included' => $item['tax_included'] ?? false,
                'tax_id' => $item['tax_id'] ?? null,
                'tax_code' => $tax?->code,
                'tax_name' => $tax?->name,
                'tax_rate' => $tax?->rate,
                'tax_category' => $tax?->category,
                'required_date' => $item['required_date'] ?? null,
                'delivery_address' => $branch?->address ?? $branch?->name,
                'remarks' => $item['remarks'] ?? null,
            ]);
        }
    }
}
