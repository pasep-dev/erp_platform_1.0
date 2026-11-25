<?php

namespace App\Http\Controllers\Transaction\AssetManagement;

use App\Enums\Administrator\Workflow\ApprovalAction as ApprovalActionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\AssetManagement\ImportAssetDirectoryRequest;
use App\Models\Administrator\Workflow\ApprovalRequest;
use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use App\Models\User;
use App\Services\AssetManagement\AssetDirectoryService;
use App\Services\DepartmentAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetDirectoryController extends Controller
{
    public function show(Request $request, Asset $asset, AssetDirectoryService $service): Response
    {
        abort_unless($request->user()?->can('asset.view'), 403);

        $asset->load([
            ...$this->assetRelationships($service),
            'goodsReceiptItem.goodsReceipt:id,number,source_type,purchase_order_id,purchase_request_id,received_at,created_at,created_by,posted_at,posted_by',
            'goodsReceiptItem.goodsReceipt.createdBy:id,name',
            'goodsReceiptItem.goodsReceipt.postedBy:id,name',
            'goodsReceiptItem.goodsReceipt.purchaseOrder:id,number,purchase_request_id,created_at,submitted_at,submitted_by',
            'goodsReceiptItem.goodsReceipt.purchaseOrder.submittedBy:id,name',
            'goodsReceiptItem.goodsReceipt.purchaseOrder.purchaseRequest:id,number,request_date,requester_id,created_at,approved_at',
            'goodsReceiptItem.goodsReceipt.purchaseOrder.purchaseRequest.requester:id,name',
            'goodsReceiptItem.goodsReceipt.purchaseRequest:id,number,request_date,requester_id,created_at,approved_at',
            'goodsReceiptItem.goodsReceipt.purchaseRequest.requester:id,name',
            'assignmentHistories' => fn (HasMany $query): HasMany => $query
                ->with(['fromCustodian:id,name', 'toCustodian:id,name', 'performedBy:id,name'])
                ->orderByDesc('effective_at')->orderByDesc('created_at'),
            'maintenanceRecords' => fn (HasMany $query): HasMany => $query
                ->with(['requestedBy:id,name', 'completedBy:id,name', 'vendor:id,name'])
                ->orderByDesc('requested_at')->orderByDesc('id'),
            'auditItems' => fn (HasMany $query): HasMany => $query
                ->with(['session:id,number,status,started_at,completed_at', 'observedLocation:id,name', 'checkedBy:id,name', 'resolvedBy:id,name'])
                ->orderByDesc('checked_at')->orderByDesc('created_at'),
            'incidents' => fn (HasMany $query): HasMany => $query
                ->with(['reportedBy:id,name', 'resolvedBy:id,name', 'events.performedBy:id,name'])
                ->orderByDesc('occurred_at')->orderByDesc('created_at'),
            'transferHistories' => fn (HasMany $query): HasMany => $query
                ->with('performedBy:id,name')->orderByDesc('effective_at')->orderByDesc('created_at'),
            'disposals' => fn (HasMany $query): HasMany => $query
                ->with(['requestedBy:id,name', 'decidedBy:id,name'])->orderByDesc('created_at'),
        ]);

        $purchaseRequest = $asset->goodsReceiptItem?->goodsReceipt?->purchaseOrder?->purchaseRequest
            ?? $asset->goodsReceiptItem?->goodsReceipt?->purchaseRequest;
        $approvalRequest = $purchaseRequest === null
            ? null
            : ApprovalRequest::query()
                ->with(['workflow:id,name', 'manualApprover:id,name', 'actions.user:id,name'])
                ->where('module', 'PURCHASE_REQUEST')
                ->where('document_type', 'PURCHASE_REQUEST')
                ->where('document_id', $purchaseRequest->id)
                ->latest('created_at')
                ->first();
        $approvedBy = $approvalRequest?->manualApprover?->name;
        if ($approvedBy === null && $approvalRequest !== null) {
            $approvedBy = $approvalRequest->actions
                ->filter(fn ($action): bool => $action->action === ApprovalActionEnum::APPROVED)
                ->pluck('user.name')
                ->filter()
                ->unique()
                ->implode(', ');
        }

        return Inertia::render('Transaction/AssetManagement/Directory/Show', [
            'asset' => $asset,
            'purchase_request_approval' => $approvalRequest === null ? null : [
                'method' => $approvalRequest->manual_approved_by !== null
                    ? 'Manual'
                    : ($approvalRequest->workflow?->name ?? 'Workflow'),
                'approved_by' => $approvedBy !== '' ? $approvedBy : null,
            ],
            'can_create_maintenance' => $request->user()?->can('asset.create') === true,
            'can_dispose' => $request->user()?->can('asset.dispose') === true,
        ]);
    }

    public function index(Request $request, AssetDirectoryService $service, DepartmentAccessService $departmentAccess): Response
    {
        $user = $request->user();
        abort_unless($user?->can('asset.view'), 403);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'company_id' => ['nullable', 'ulid', 'exists:mst_org_companies,id'],
            'department_id' => ['nullable', 'ulid', 'exists:mst_org_departments,id'],
            'location_id' => ['nullable', 'ulid', 'exists:mst_org_locations,id'],
            'status' => ['nullable', 'in:ALL,ACTIVE,MISSING,DISPOSAL_PENDING,DISPOSED'],
            'sort_field' => ['nullable', 'in:asset,company,branch,assignment,status,warranty'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:5,15,25,50,100'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 15);
        $sortField = $validated['sort_field'] ?? 'asset';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $assetQuery = $service->query($request);

        $sortedQuery = (clone $assetQuery)->with($this->assetRelationships($service));
        match ($sortField) {
            'company' => $sortedQuery
                ->orderBy(Company::query()->select('name')->whereColumn('mst_org_companies.id', 'trx_assets.company_id'), $sortDirection)
                ->orderBy(Department::query()->select('name')->whereColumn('mst_org_departments.id', 'trx_assets.department_id'), $sortDirection),
            'branch' => $sortedQuery
                ->orderBy(Branch::query()->select('name')->whereColumn('mst_org_branches.id', 'trx_assets.branch_id'), $sortDirection)
                ->orderBy(Location::query()->select('name')->whereColumn('mst_org_locations.id', 'trx_assets.location_id'), $sortDirection),
            'assignment' => $sortedQuery->orderBy(User::query()->select('name')->whereColumn('users.id', 'trx_assets.custodian_id'), $sortDirection),
            'status' => $sortedQuery->orderBy('trx_assets.status', $sortDirection),
            'warranty' => $sortedQuery->orderBy('trx_assets.warranty_end_date', $sortDirection),
            default => $sortedQuery->orderBy('trx_assets.number', $sortDirection),
        };
        $assets = $sortedQuery->orderBy('trx_assets.id')->paginate($perPage)->withQueryString();

        return Inertia::render('Transaction/AssetManagement/Directory/Index', [
            'assets' => $assets,
            'filters' => [
                'search' => trim((string) ($validated['search'] ?? '')),
                'company_id' => $validated['company_id'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'location_id' => $validated['location_id'] ?? null,
                'status' => $validated['status'] ?? 'ALL',
                'sort_field' => $sortField,
                'sort_direction' => $sortDirection,
                'per_page' => $perPage,
            ],
            'counts' => [
                'total' => (clone $assetQuery)->count(),
                'active' => (clone $assetQuery)->where('status', Asset::STATUS_ACTIVE)->count(),
                'assigned' => (clone $assetQuery)->whereNotNull('custodian_id')->count(),
                'disposed' => (clone $assetQuery)->where('status', Asset::STATUS_DISPOSED)->count(),
            ],
            'companies' => Company::query()->whereIn('id', Department::query()->whereIn('id', $departmentAccess->departmentIds($user))->select('company_id'))->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'departments' => $departmentAccess->scopeDepartments(Department::query(), $user)->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'locations' => Location::query()->whereIn('company_id', Department::query()->whereIn('id', $departmentAccess->departmentIds($user))->select('company_id'))->where('is_active', true)->orderBy('name')->get(['id', 'company_id', 'branch_id', 'code', 'name']),
            'can_create_maintenance' => $request->user()?->can('asset.create') === true,
            'can_dispose' => $request->user()?->can('asset.dispose') === true,
            'reminders' => $this->reminders($user, $departmentAccess),
        ]);
    }

    public function reportIndex(Request $request): RedirectResponse
    {
        return to_route('transaction.asset-management.directory.index', $request->query());
    }

    public function template(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('registration.create'), 403);

        $headers = AssetDirectoryService::IMPORT_HEADERS;

        return response()->streamDownload(function () use ($headers): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            fclose($output);
        }, 'asset-directory-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(ImportAssetDirectoryRequest $request, AssetDirectoryService $service): RedirectResponse
    {
        $count = $service->import($request->file('file'), $request->user());

        return to_route('transaction.asset-management.registrations.index')
            ->with('success', $count.' aset existing berhasil diimpor.');
    }

    public function export(Request $request, AssetDirectoryService $service): StreamedResponse
    {
        abort_unless($request->user()?->can('asset.view'), 403);

        $assets = $service->query($request)->with($this->assetRelationships($service))->lazyById(200);

        return response()->streamDownload(function () use ($assets, $service): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'Kode Aset', 'Produk', 'Serial Number', 'Perusahaan', 'Departemen',
                'Cabang', 'Lokasi', 'Penanggung Jawab', 'Status Penugasan', 'Status Aset',
                'Tanggal Perolehan', 'Nilai Perolehan', 'Garansi Berakhir', 'Service Berikutnya',
            ]);

            foreach ($assets as $asset) {
                $assignmentStatus = $asset->status === Asset::STATUS_DISPOSED
                    ? 'Disposed'
                    : ($asset->status === Asset::STATUS_DISPOSAL_PENDING
                        ? 'Menunggu Pelepasan'
                        : ($asset->status === Asset::STATUS_MISSING
                            ? 'Tidak Ditemukan'
                            : ($asset->custodian_id !== null ? 'Assigned' : 'Available')));

                fputcsv($output, array_map($service->escapeCsvCell(...), [
                    $asset->number,
                    $asset->product?->name,
                    $asset->serial_number,
                    $asset->company?->name,
                    $asset->department?->name,
                    $asset->branch?->name,
                    $asset->location?->name,
                    $asset->custodian?->name,
                    $assignmentStatus,
                    $asset->status,
                    $asset->acquisition_date?->toDateString(),
                    $asset->acquisition_cost,
                    $asset->warranty_end_date?->toDateString(),
                    $asset->latestMaintenanceRecord?->next_service_date?->toDateString(),
                ]));
            }

            fclose($output);
        }, 'asset-report-'.today()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function printQrCodes(Request $request, AssetDirectoryService $service): Response
    {
        abort_unless($request->user()?->can('asset.view'), 403);

        $scope = $request->string('scope', 'selected')->toString();
        if (in_array($scope, ['selected', 'specific'], true)) {
            $validated = $request->validate([
                'ids' => ['required', 'array', 'min:1'],
                'ids.*' => ['required', 'ulid', 'distinct', 'exists:trx_assets,id'],
            ]);
            abort_if($scope === 'specific' && count($validated['ids']) !== 1, 422);
            $assets = Asset::query()
                ->whereIn('id', $validated['ids'])
                ->orderBy('number')
                ->with(['company:id,code,name', 'department:id,code'])
                ->get(['id', 'number', 'company_id', 'department_id']);
        } else {
            abort_unless(in_array($scope, ['all', 'page'], true), 422);
            $query = $service->query($request);

            if ($scope === 'page') {
                $validated = $request->validate([
                    'ids' => ['required', 'array', 'min:1'],
                    'ids.*' => ['required', 'ulid', 'distinct', 'exists:trx_assets,id'],
                ]);
                $query->whereIn('id', $validated['ids']);
            }

            $assets = $query->with(['company:id,code,name', 'department:id,code'])
                ->orderBy('number')
                ->get(['id', 'number', 'company_id', 'department_id']);
        }

        return Inertia::render('Transaction/AssetManagement/Directory/PrintQrCodes', [
            'assets' => $assets->map(fn (Asset $asset): array => [
                'id' => $asset->id,
                'number' => $asset->number,
                'company_code' => $asset->company?->code ?? '-',
                'company_name' => $asset->company?->name ?? '-',
                'department_code' => $asset->department?->code ?? '-',
            ])->values(),
        ]);
    }

    /** @return array<int|string, string|\Closure> */
    private function assetRelationships(AssetDirectoryService $service): array
    {
        return array_merge($service->relationships(), [
            'latestMaintenanceRecord' => fn (HasOne $query) => $query->select([
                'trx_asset_maintenance_records.id',
                'trx_asset_maintenance_records.asset_id',
                'trx_asset_maintenance_records.next_service_date',
            ]),
        ]);
    }

    /** @return array{service_due: Collection<int, Asset>} */
    private function reminders(User $user, DepartmentAccessService $departmentAccess): array
    {
        $reminderCutoff = today()->addDays(30)->toDateString();

        $serviceDue = $departmentAccess->scopeByDepartment(Asset::query(), $user, 'trx_assets.department_id')
            ->where('status', Asset::STATUS_ACTIVE)
            ->whereHas('latestMaintenanceRecord', fn (Builder $query) => $query
                ->where('trx_asset_maintenance_records.status', AssetMaintenanceRecord::STATUS_COMPLETED)
                ->whereNotNull('trx_asset_maintenance_records.next_service_date')
                ->whereDate('trx_asset_maintenance_records.next_service_date', '<=', $reminderCutoff))
            ->with([
                'latestMaintenanceRecord' => fn (HasOne $query) => $query->select([
                    'trx_asset_maintenance_records.id',
                    'trx_asset_maintenance_records.asset_id',
                    'trx_asset_maintenance_records.next_service_date',
                ]),
                'product:id,code,name',
            ])
            ->orderBy(AssetMaintenanceRecord::query()
                ->select('next_service_date')
                ->whereColumn('trx_asset_maintenance_records.asset_id', 'trx_assets.id')
                ->where('trx_asset_maintenance_records.status', AssetMaintenanceRecord::STATUS_COMPLETED)
                ->whereNotNull('trx_asset_maintenance_records.next_service_date')
                ->orderByDesc('trx_asset_maintenance_records.id')
                ->limit(1))
            ->orderBy('number')
            ->limit(10)
            ->get(['id', 'number', 'product_id']);

        return [
            'service_due' => $serviceDue,
        ];
    }
}
