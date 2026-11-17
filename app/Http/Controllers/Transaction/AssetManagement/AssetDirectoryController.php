<?php

namespace App\Http\Controllers\Transaction\AssetManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\AssetManagement\ImportAssetDirectoryRequest;
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
            'goodsReceiptItem.goodsReceipt:id,number,source_type,purchase_order_id,purchase_request_id',
            'goodsReceiptItem.goodsReceipt.purchaseOrder:id,number',
            'goodsReceiptItem.goodsReceipt.purchaseRequest:id,number',
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

        return Inertia::render('Transaction/AssetManagement/Directory/Show', [
            'asset' => $asset,
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
            'per_page' => ['nullable', 'integer', 'in:5,15,25,50,100'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 15);
        $assetQuery = $service->query($request);

        $assets = (clone $assetQuery)
            ->with($this->assetRelationships($service))
            ->orderBy('number')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Transaction/AssetManagement/Directory/Index', [
            'assets' => $assets,
            'filters' => [
                'search' => trim((string) ($validated['search'] ?? '')),
                'company_id' => $validated['company_id'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'location_id' => $validated['location_id'] ?? null,
                'status' => $validated['status'] ?? 'ALL',
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
            'can_import' => $request->user()?->can('asset.create') === true,
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
        abort_unless($request->user()?->can('asset.create'), 403);

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

        return to_route('transaction.asset-management.directory.index')
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
                'ids.*' => ['required', 'ulid', 'distinct', 'exists:assets,id'],
            ]);
            abort_if($scope === 'specific' && count($validated['ids']) !== 1, 422);
            $assets = Asset::query()
                ->whereIn('id', $validated['ids'])
                ->orderBy('number')
                ->with(['company:id,code', 'department:id,code'])
                ->get(['id', 'number', 'company_id', 'department_id']);
        } else {
            abort_unless(in_array($scope, ['all', 'page'], true), 422);
            $query = $service->query($request);

            if ($scope === 'page') {
                $validated = $request->validate([
                    'ids' => ['required', 'array', 'min:1'],
                    'ids.*' => ['required', 'ulid', 'distinct', 'exists:assets,id'],
                ]);
                $query->whereIn('id', $validated['ids']);
            }

            $assets = $query->with(['company:id,code', 'department:id,code'])
                ->orderBy('number')
                ->get(['id', 'number', 'company_id', 'department_id']);
        }

        return Inertia::render('Transaction/AssetManagement/Directory/PrintQrCodes', [
            'assets' => $assets->map(fn (Asset $asset): array => [
                'id' => $asset->id,
                'number' => $asset->number,
                'company_code' => $asset->company?->code ?? '-',
                'department_code' => $asset->department?->code ?? '-',
            ])->values(),
        ]);
    }

    /** @return array<int|string, string|\Closure> */
    private function assetRelationships(AssetDirectoryService $service): array
    {
        return array_merge($service->relationships(), [
            'latestMaintenanceRecord' => fn (HasOne $query) => $query->select([
                'asset_maintenance_records.id',
                'asset_maintenance_records.asset_id',
                'asset_maintenance_records.next_service_date',
            ]),
        ]);
    }

    /** @return array{service_due: Collection<int, Asset>, warranty_due: Collection<int, Asset>} */
    private function reminders(User $user, DepartmentAccessService $departmentAccess): array
    {
        $reminderCutoff = today()->addDays(30)->toDateString();

        $serviceDue = $departmentAccess->scopeByDepartment(Asset::query(), $user, 'assets.department_id')
            ->where('status', Asset::STATUS_ACTIVE)
            ->whereHas('latestMaintenanceRecord', fn (Builder $query) => $query
                ->where('asset_maintenance_records.status', AssetMaintenanceRecord::STATUS_COMPLETED)
                ->whereNotNull('asset_maintenance_records.next_service_date')
                ->whereDate('asset_maintenance_records.next_service_date', '<=', $reminderCutoff))
            ->with([
                'latestMaintenanceRecord' => fn (HasOne $query) => $query->select([
                    'asset_maintenance_records.id',
                    'asset_maintenance_records.asset_id',
                    'asset_maintenance_records.next_service_date',
                ]),
                'product:id,code,name',
            ])
            ->orderBy(AssetMaintenanceRecord::query()
                ->select('next_service_date')
                ->whereColumn('asset_maintenance_records.asset_id', 'assets.id')
                ->where('asset_maintenance_records.status', AssetMaintenanceRecord::STATUS_COMPLETED)
                ->whereNotNull('asset_maintenance_records.next_service_date')
                ->orderByDesc('asset_maintenance_records.id')
                ->limit(1))
            ->orderBy('number')
            ->limit(10)
            ->get(['id', 'number', 'product_id']);

        $warrantyDue = $departmentAccess->scopeByDepartment(Asset::query(), $user, 'assets.department_id')
            ->where('status', Asset::STATUS_ACTIVE)
            ->whereNotNull('warranty_end_date')
            ->whereDate('warranty_end_date', '<=', $reminderCutoff)
            ->with('product:id,code,name')
            ->orderBy('warranty_end_date')
            ->limit(10)
            ->get(['id', 'number', 'product_id', 'warranty_end_date']);

        return [
            'service_due' => $serviceDue,
            'warranty_due' => $warrantyDue,
        ];
    }
}
