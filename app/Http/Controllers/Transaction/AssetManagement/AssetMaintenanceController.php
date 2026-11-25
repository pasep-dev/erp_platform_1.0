<?php

namespace App\Http\Controllers\Transaction\AssetManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\AssetManagement\CancelAssetMaintenanceRequest;
use App\Http\Requests\Transaction\AssetManagement\CompleteAssetMaintenanceRequest;
use App\Http\Requests\Transaction\AssetManagement\MarkAssetMaintenanceApprovedRequest;
use App\Http\Requests\Transaction\AssetManagement\MarkAssetMaintenanceRejectedRequest;
use App\Http\Requests\Transaction\AssetManagement\StoreAssetMaintenanceRequest;
use App\Http\Requests\Transaction\AssetManagement\UpdateAssetMaintenanceRequest;
use App\Models\Master\Procurement\Vendor;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use App\Services\AssetManagement\AssetMaintenanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AssetMaintenanceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('asset.view'), 403);

        $requestedPerPage = $request->integer('per_page', 15);
        $perPage = in_array($requestedPerPage, [5, 15, 25, 50, 100], true) ? $requestedPerPage : 15;
        $search = trim($request->string('search')->toString());
        $status = $request->string('status', 'ALL')->toString();

        $records = AssetMaintenanceRecord::query()
            ->with([
                'asset:id,number,product_id,company_id,department_id',
                'asset.product:id,code,name',
                'vendor:id,code,name',
                'requestedBy:id,name',
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('number', 'ilike', "%{$search}%")
                        ->orWhereHas('asset', fn (Builder $asset) => $asset->where('number', 'ilike', "%{$search}%")
                            ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'ilike', "%{$search}%")
                                ->orWhere('code', 'ilike', "%{$search}%")))
                        ->orWhereHas('vendor', fn (Builder $vendor) => $vendor->where('name', 'ilike', "%{$search}%"));
                });
            })
            ->when(in_array($status, [
                AssetMaintenanceRecord::STATUS_REQUESTED,
                AssetMaintenanceRecord::STATUS_WAITING_APPROVAL,
                AssetMaintenanceRecord::STATUS_IN_PROGRESS,
                AssetMaintenanceRecord::STATUS_WAITING_PARTS,
                AssetMaintenanceRecord::STATUS_READY_TO_START,
                AssetMaintenanceRecord::STATUS_COMPLETED,
                AssetMaintenanceRecord::STATUS_CANCELLED,
            ], true), fn (Builder $query) => $query->where('status', $status))
            ->orderByRaw("CASE status WHEN 'REQUESTED' THEN 1 WHEN 'WAITING_APPROVAL' THEN 2 WHEN 'IN_PROGRESS' THEN 3 WHEN 'WAITING_PARTS' THEN 4 WHEN 'READY_TO_START' THEN 4 WHEN 'COMPLETED' THEN 5 ELSE 6 END")
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Transaction/AssetManagement/Maintenance/Index', [
            'records' => $records,
            'filters' => [
                'search' => $search,
                'status' => in_array($status, [
                    AssetMaintenanceRecord::STATUS_REQUESTED,
                    AssetMaintenanceRecord::STATUS_WAITING_APPROVAL,
                    AssetMaintenanceRecord::STATUS_IN_PROGRESS,
                    AssetMaintenanceRecord::STATUS_WAITING_PARTS,
                    AssetMaintenanceRecord::STATUS_READY_TO_START,
                    AssetMaintenanceRecord::STATUS_COMPLETED,
                    AssetMaintenanceRecord::STATUS_CANCELLED,
                ], true) ? $status : 'ALL',
            ],
            'can_create' => $request->user()?->can('asset.create') === true,
            'can_manage' => $request->user()?->can('asset.update') === true,
            'condition_options' => Asset::CONDITION_OPTIONS,
        ]);
    }

    public function create(Request $request, ?Asset $asset = null): Response
    {
        abort_unless($request->user()?->can('asset.create'), 403);

        return Inertia::render('Transaction/AssetManagement/Maintenance/Form', [
            'maintenance' => null,
            'assets' => Asset::query()
                ->where('status', Asset::STATUS_ACTIVE)
                ->with('product:id,code,name')
                ->orderBy('number')
                ->get(['id', 'number', 'condition', 'product_id']),
            'vendors' => $this->activeVendors(),
            'initial_asset_id' => $asset?->id,
            'type_options' => [
                AssetMaintenanceRecord::TYPE_PREVENTIVE => 'Preventive Maintenance',
                AssetMaintenanceRecord::TYPE_CORRECTIVE => 'Perbaikan / Corrective',
            ],
            'priority_options' => AssetMaintenanceRecord::PRIORITY_OPTIONS,
            'condition_options' => Asset::CONDITION_OPTIONS,
        ]);
    }

    public function store(StoreAssetMaintenanceRequest $request, AssetMaintenanceService $service): RedirectResponse
    {
        $maintenance = $service->create($request->validated(), $request->user());

        return to_route('transaction.asset-management.maintenance.show', $maintenance)
            ->with('success', 'Request maintenance berhasil dibuat. Lengkapi pemeriksaan dan estimasi sebelum diajukan untuk approval.');
    }

    public function edit(Request $request, AssetMaintenanceRecord $maintenance): Response
    {
        abort_unless($request->user()?->can('asset.update'), 403);
        abort_unless($maintenance->status === AssetMaintenanceRecord::STATUS_REQUESTED, 422);

        $maintenance->load('asset:id,number,condition,product_id', 'asset.product:id,code,name', 'components');

        return Inertia::render('Transaction/AssetManagement/Maintenance/Form', [
            'maintenance' => $maintenance,
            'assets' => collect([$maintenance->asset]),
            'vendors' => $this->activeVendors(),
            'initial_asset_id' => $maintenance->asset_id,
            'type_options' => [
                AssetMaintenanceRecord::TYPE_PREVENTIVE => 'Preventive Maintenance',
                AssetMaintenanceRecord::TYPE_CORRECTIVE => 'Perbaikan / Corrective',
            ],
            'priority_options' => AssetMaintenanceRecord::PRIORITY_OPTIONS,
            'condition_options' => Asset::CONDITION_OPTIONS,
        ]);
    }

    public function update(UpdateAssetMaintenanceRequest $request, AssetMaintenanceRecord $maintenance, AssetMaintenanceService $service): RedirectResponse
    {
        $service->updateRequest($maintenance, $request->validated());

        return to_route('transaction.asset-management.maintenance.show', $maintenance)
            ->with('success', 'Informasi pemeriksaan dan estimasi berhasil diperbarui.');
    }

    public function show(Request $request, AssetMaintenanceRecord $maintenance): Response
    {
        abort_unless($request->user()?->can('asset.view'), 403);

        $maintenance->load([
            'asset:id,number,serial_number,condition,product_id,company_id,department_id,location_id,custodian_id',
            'asset.product:id,code,name',
            'asset.company:id,code,name',
            'asset.department:id,code,name',
            'asset.location:id,code,name',
            'asset.custodian:id,name,email',
            'asset.maintenanceRecords' => fn (HasMany $query) => $query
                ->with(['requestedBy:id,name', 'approvedBy:id,name', 'rejectedBy:id,name', 'cancelledBy:id,name', 'completedBy:id,name', 'components'])
                ->orderByDesc('requested_at')
                ->orderByDesc('id'),
            'vendor:id,code,name,phone,email,address',
            'components',
            'requestedBy:id,name',
            'approvedBy:id,name',
            'rejectedBy:id,name',
            'cancelledBy:id,name',
            'completedBy:id,name',
        ]);

        $canManage = $request->user()?->can('asset.update') === true;

        return Inertia::render('Transaction/AssetManagement/Maintenance/Show', [
            'maintenance' => $maintenance,
            'can_update' => $canManage && $maintenance->status === AssetMaintenanceRecord::STATUS_REQUESTED,
            'can_submit' => $canManage
                && $maintenance->status === AssetMaintenanceRecord::STATUS_REQUESTED
                && $maintenance->estimated_cost !== null
                && filled($maintenance->inspection_result),
            'can_mark_approved' => $canManage && $maintenance->status === AssetMaintenanceRecord::STATUS_WAITING_APPROVAL,
            'can_reject' => $canManage && $maintenance->status === AssetMaintenanceRecord::STATUS_WAITING_APPROVAL,
            'can_cancel' => $canManage && $maintenance->status === AssetMaintenanceRecord::STATUS_REQUESTED,
            'can_print' => true,
            'condition_options' => Asset::CONDITION_OPTIONS,
            'status_labels' => [
                AssetMaintenanceRecord::STATUS_REQUESTED => 'Pemeriksaan',
                AssetMaintenanceRecord::STATUS_WAITING_APPROVAL => 'Pengajuan',
                AssetMaintenanceRecord::STATUS_WAITING_PARTS => 'Dalam Pengerjaan',
                AssetMaintenanceRecord::STATUS_READY_TO_START => 'Dalam Pengerjaan',
                AssetMaintenanceRecord::STATUS_IN_PROGRESS => 'Dalam Pengerjaan',
                AssetMaintenanceRecord::STATUS_COMPLETED => 'Selesai',
                AssetMaintenanceRecord::STATUS_CANCELLED => $maintenance->rejected_at === null ? 'Dibatalkan' : 'Ditolak',
            ],
        ]);
    }

    public function submit(Request $request, AssetMaintenanceRecord $maintenance, AssetMaintenanceService $service): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);
        abort_unless($user->can('asset.update'), 403);

        try {
            $service->submitForApproval($maintenance);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pengajuan maintenance berhasil dikirim. Cetak dokumen untuk meminta persetujuan secara manual.');
    }

    public function markApproved(
        MarkAssetMaintenanceApprovedRequest $request,
        AssetMaintenanceRecord $maintenance,
        AssetMaintenanceService $service,
    ): RedirectResponse {
        $user = $request->user();
        abort_if($user === null, 401);

        $documentPath = null;
        if ($request->hasFile('signed_document')) {
            $documentPath = $request->file('signed_document')->store("asset-maintenance-approvals/{$maintenance->id}", 'local');

            if ($documentPath === false) {
                return back()->with('error', 'Dokumen persetujuan gagal disimpan. Silakan coba kembali.');
            }
        }

        try {
            $service->markApproved(
                $maintenance,
                $user,
                $documentPath,
                $request->validated('remarks'),
            );
        } catch (Throwable $exception) {
            if ($documentPath !== null) {
                Storage::disk('local')->delete($documentPath);
            }
            report($exception);

            return back()->with('error', 'Mark approval gagal diproses. Muat ulang halaman dan coba kembali.');
        }

        return back()->with('success', 'Pengajuan disetujui dan status maintenance berubah menjadi Dalam Pengerjaan.');
    }

    public function markRejected(
        MarkAssetMaintenanceRejectedRequest $request,
        AssetMaintenanceRecord $maintenance,
        AssetMaintenanceService $service,
    ): RedirectResponse {
        $user = $request->user();
        abort_if($user === null, 401);

        $service->markRejected($maintenance, $user, $request->validated('rejection_reason'));

        return back()->with('success', 'Pengajuan ditolak dan status maintenance berubah menjadi Ditolak.');
    }

    public function downloadApprovalDocument(Request $request, AssetMaintenanceRecord $maintenance): StreamedResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);
        abort_unless($user->can('asset.view'), 403);
        abort_unless(filled($maintenance->approval_document_path), 404);

        return Storage::disk('local')->download($maintenance->approval_document_path);
    }

    public function print(Request $request, AssetMaintenanceRecord $maintenance): Response
    {
        abort_unless($request->user()?->can('asset.view'), 403);

        $maintenance->load([
            'asset:id,number,serial_number,product_id,company_id,department_id,location_id,branch_id',
            'asset.product:id,code,name',
            'asset.company:id,code,name',
            'asset.department:id,code,name',
            'asset.location:id,code,name',
            'asset.branch:id,code,name',
            'vendor:id,code,name,phone,email,address',
            'requestedBy:id,name',
            'components',
        ]);

        $latestService = $maintenance->asset->maintenanceRecords()
            ->where('id', '!=', $maintenance->getKey())
            ->where('status', AssetMaintenanceRecord::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first(['id', 'number', 'maintenance_type', 'completed_at', 'work_summary']);

        return Inertia::render('Transaction/AssetManagement/Maintenance/Print', [
            'maintenance' => $maintenance,
            'latest_service' => $latestService,
            'auto_print' => $request->boolean('print'),
            'close_after_print' => $request->boolean('closeAfterPrint'),
        ]);
    }

    public function complete(CompleteAssetMaintenanceRequest $request, AssetMaintenanceRecord $maintenance, AssetMaintenanceService $service): RedirectResponse
    {
        $service->complete($maintenance, $request->validated(), $request->user());

        return back()->with('success', 'Maintenance selesai. Kondisi aset dan jadwal service berikutnya telah diperbarui.');
    }

    public function cancel(CancelAssetMaintenanceRequest $request, AssetMaintenanceRecord $maintenance, AssetMaintenanceService $service): RedirectResponse
    {
        $service->cancel($maintenance, $request->validated('cancellation_reason'), $request->user());

        return back()->with('success', 'Permintaan maintenance dibatalkan.');
    }

    /**
     * @return Collection<int, Vendor>
     */
    private function activeVendors(): Collection
    {
        return Vendor::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'phone', 'email']);
    }
}
