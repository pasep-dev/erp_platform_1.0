<?php

namespace App\Http\Controllers\Transaction\AssetManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\AssetManagement\ResolveAssetAuditItemRequest;
use App\Http\Requests\Transaction\AssetManagement\ScanAssetAuditRequest;
use App\Http\Requests\Transaction\AssetManagement\StoreAssetAuditSessionRequest;
use App\Http\Requests\Transaction\AssetManagement\UpdateAssetAuditItemRequest;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Master\Product\Category;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetAuditItem;
use App\Models\Transaction\AssetManagement\AssetAuditSession;
use App\Models\User;
use App\Services\AssetManagement\AssetAuditService;
use App\Services\DepartmentAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetAuditController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('asset.view'), 403);

        $search = trim($request->string('search')->toString());
        $status = $request->string('status', 'ALL')->toString();
        $requestedPerPage = $request->integer('per_page', 15);
        $perPage = in_array($requestedPerPage, [5, 15, 25, 50, 100], true) ? $requestedPerPage : 15;

        $sessions = AssetAuditSession::query()
            ->with(['createdBy:id,name', 'completedBy:id,name'])
            ->withCount('items')
            ->withCount([
                'items as pending_count' => fn (Builder $query) => $query->where('result', AssetAuditItem::RESULT_PENDING),
                'items as discrepancy_count' => fn (Builder $query) => $query->whereNotIn('result', [AssetAuditItem::RESULT_FOUND, AssetAuditItem::RESULT_PENDING]),
            ])
            ->when($search !== '', fn (Builder $query) => $query->where('number', 'ilike', "%{$search}%"))
            ->when(in_array($status, [AssetAuditSession::STATUS_IN_PROGRESS, AssetAuditSession::STATUS_COMPLETED], true), fn (Builder $query) => $query->where('status', $status))
            ->orderByRaw("CASE status WHEN 'IN_PROGRESS' THEN 1 ELSE 2 END")
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Transaction/AssetManagement/Audit/Index', [
            'sessions' => $sessions,
            'filters' => [
                'search' => $search,
                'status' => in_array($status, [AssetAuditSession::STATUS_IN_PROGRESS, AssetAuditSession::STATUS_COMPLETED], true) ? $status : 'ALL',
            ],
            'can_create' => $request->user()?->can('asset.create') === true,
        ]);
    }

    public function create(Request $request, DepartmentAccessService $departmentAccess): Response
    {
        abort_unless($request->user()?->can('asset.create'), 403);

        return Inertia::render('Transaction/AssetManagement/Audit/Create', [
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'departments' => $departmentAccess->scopeDepartments(Department::query(), $request->user())->where('is_active', true)->orderBy('name')->get(['id', 'company_id', 'code', 'name']),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'company_id', 'code', 'name']),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(StoreAssetAuditSessionRequest $request, AssetAuditService $service): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);

        $session = $service->createSession($request->validated(), $user);

        return to_route('transaction.asset-management.audits.show', $session)
            ->with('success', 'Sesi audit berhasil dibuat. Scan QR aset atau catat hasil pemeriksaan setiap aset.');
    }

    public function show(Request $request, AssetAuditSession $audit): Response
    {
        abort_unless($request->user()?->can('asset.view'), 403);

        $audit->load(['createdBy:id,name', 'completedBy:id,name']);
        $items = $audit->items()
            ->with([
                'asset:id,number,serial_number,condition,company_id,department_id,location_id,custodian_id,product_id',
                'asset.product:id,code,name',
                'asset.location:id,code,name',
                'observedLocation:id,code,name',
                'checkedBy:id,name',
                'resolvedBy:id,name',
            ])
            ->orderByRaw("CASE result WHEN 'PENDING' THEN 1 WHEN 'MOVED' THEN 2 WHEN 'DAMAGED' THEN 3 WHEN 'MISSING' THEN 4 ELSE 5 END")
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $summary = [
            'total' => $audit->items()->count(),
            'pending' => $audit->items()->where('result', AssetAuditItem::RESULT_PENDING)->count(),
            'found' => $audit->items()->where('result', AssetAuditItem::RESULT_FOUND)->count(),
            'discrepancies' => $audit->items()->whereNotIn('result', [AssetAuditItem::RESULT_PENDING, AssetAuditItem::RESULT_FOUND])->count(),
            'unresolved' => $audit->items()->whereNotIn('result', [AssetAuditItem::RESULT_PENDING, AssetAuditItem::RESULT_FOUND])->whereNull('resolved_at')->count(),
        ];

        return Inertia::render('Transaction/AssetManagement/Audit/Show', [
            'audit' => $audit,
            'items' => $items,
            'summary' => $summary,
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'condition_options' => Asset::CONDITION_OPTIONS,
            'can_manage' => $request->user()?->can('asset.update') === true && $audit->status === AssetAuditSession::STATUS_IN_PROGRESS,
            'can_print' => true,
        ]);
    }

    public function scan(ScanAssetAuditRequest $request, AssetAuditSession $audit, AssetAuditService $service): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);

        $service->recordScan($audit, $request->validated('asset_number'), $user);

        return back()->with('success', 'Aset berhasil ditemukan dan dicatat dalam audit.');
    }

    public function updateItem(
        UpdateAssetAuditItemRequest $request,
        AssetAuditSession $audit,
        AssetAuditItem $item,
        AssetAuditService $service,
    ): RedirectResponse {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);

        $service->updateFinding($audit, $item, $request->validated(), $user);

        return back()->with('success', 'Hasil pemeriksaan aset berhasil disimpan.');
    }

    public function resolveItem(
        ResolveAssetAuditItemRequest $request,
        AssetAuditSession $audit,
        AssetAuditItem $item,
        AssetAuditService $service,
    ): RedirectResponse {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);

        $service->resolveFinding($audit, $item, $request->validated('resolution_remarks'), $user);

        return back()->with('success', 'Tinjauan selisih audit berhasil dicatat.');
    }

    public function complete(Request $request, AssetAuditSession $audit, AssetAuditService $service): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);
        abort_unless($user->can('asset.update'), 403);

        $service->complete($audit, $user);

        return back()->with('success', 'Audit selesai. Hasil pemeriksaan dan resolusi selisih tersimpan sebagai riwayat.');
    }

    public function print(Request $request, AssetAuditSession $audit): Response
    {
        abort_unless($request->user()?->can('asset.view'), 403);

        $audit->load(['createdBy:id,name', 'completedBy:id,name']);
        $items = $audit->items()
            ->with(['asset:id,number,serial_number,product_id', 'asset.product:id,code,name', 'observedLocation:id,code,name', 'checkedBy:id,name', 'resolvedBy:id,name'])
            ->orderBy('result')
            ->orderBy('id')
            ->get();

        return Inertia::render('Transaction/AssetManagement/Audit/Print', [
            'audit' => $audit,
            'items' => $items,
        ]);
    }
}
