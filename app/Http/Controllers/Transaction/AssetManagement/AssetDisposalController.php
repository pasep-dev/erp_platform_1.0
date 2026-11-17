<?php

namespace App\Http\Controllers\Transaction\AssetManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\AssetManagement\ApproveAssetDisposalRequest;
use App\Http\Requests\Transaction\AssetManagement\RejectAssetDisposalRequest;
use App\Http\Requests\Transaction\AssetManagement\StoreAssetDisposalRequest;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetDisposal;
use App\Services\AssetManagement\AssetDisposalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AssetDisposalController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('asset.view'), 403);

        $search = trim($request->string('search')->toString());
        $status = $request->string('status', 'ALL')->toString();
        $requestedPerPage = $request->integer('per_page', 15);
        $perPage = in_array($requestedPerPage, [5, 15, 25, 50, 100], true) ? $requestedPerPage : 15;

        $disposals = AssetDisposal::query()
            ->with(['asset:id,number,product_id', 'asset.product:id,code,name', 'requestedBy:id,name', 'decidedBy:id,name'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('number', 'ilike', "%{$search}%")
                        ->orWhere('reason', 'ilike', "%{$search}%")
                        ->orWhereHas('asset', fn (Builder $asset) => $asset->where('number', 'ilike', "%{$search}%")
                            ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'ilike', "%{$search}%")
                                ->orWhere('code', 'ilike', "%{$search}%")));
                });
            })
            ->when(in_array($status, AssetDisposal::STATUSES, true), fn (Builder $query) => $query->where('status', $status))
            ->orderByRaw("CASE status WHEN 'PENDING_APPROVAL' THEN 1 WHEN 'APPROVED' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Transaction/AssetManagement/Disposal/Index', [
            'disposals' => $disposals,
            'filters' => [
                'search' => $search,
                'status' => in_array($status, AssetDisposal::STATUSES, true) ? $status : 'ALL',
            ],
            'counts' => [
                'pending' => AssetDisposal::query()->where('status', AssetDisposal::STATUS_PENDING_APPROVAL)->count(),
                'approved' => AssetDisposal::query()->where('status', AssetDisposal::STATUS_APPROVED)->count(),
                'rejected' => AssetDisposal::query()->where('status', AssetDisposal::STATUS_REJECTED)->count(),
            ],
            'can_create' => $request->user()?->can('asset.dispose') === true,
        ]);
    }

    public function create(Request $request, ?Asset $asset = null): Response
    {
        abort_unless($request->user()?->can('asset.dispose'), 403);
        abort_if($asset !== null && ($asset->status !== Asset::STATUS_ACTIVE || $asset->custodian_id !== null), 422);

        if ($asset !== null) {
            $asset->load(['product:id,code,name', 'company:id,code,name', 'department:id,code,name', 'branch:id,code,name', 'location:id,code,name']);
        }

        return Inertia::render('Transaction/AssetManagement/Disposal/Create', [
            'initial_asset' => $asset,
            'assets' => Asset::query()
                ->where('status', Asset::STATUS_ACTIVE)
                ->whereNull('custodian_id')
                ->with(['product:id,code,name', 'company:id,code,name', 'department:id,code,name', 'location:id,code,name'])
                ->orderBy('number')
                ->get(['id', 'number', 'serial_number', 'condition', 'product_id', 'company_id', 'department_id', 'location_id']),
            'type_options' => [
                AssetDisposal::TYPE_SOLD => 'Dijual',
                AssetDisposal::TYPE_DONATED => 'Dihibahkan',
                AssetDisposal::TYPE_WRITTEN_OFF => 'Dihapuskan',
            ],
        ]);
    }

    public function store(StoreAssetDisposalRequest $request, AssetDisposalService $service): RedirectResponse
    {
        $actor = $request->user();
        abort_if($actor === null, 401);

        $evidencePath = $request->file('evidence')?->store('asset-disposals/evidence', 'local');
        if ($request->hasFile('evidence') && $evidencePath === false) {
            return back()->with('error', 'Dokumen pendukung gagal disimpan. Silakan coba kembali.');
        }

        try {
            $disposal = $service->requestDisposal(
                $request->safe()->except(['asset_id', 'evidence']) + ['asset_id' => $request->validated('asset_id')],
                $actor,
                $evidencePath,
            );
        } catch (Throwable $exception) {
            if (is_string($evidencePath)) {
                Storage::disk('local')->delete($evidencePath);
            }

            throw $exception;
        }

        return to_route('transaction.asset-management.disposals.show', $disposal)
            ->with('success', 'Pengajuan pelepasan tersimpan dan menunggu keputusan. Status aset dikunci sementara.');
    }

    public function show(Request $request, AssetDisposal $disposal): Response
    {
        abort_unless($request->user()?->can('asset.view'), 403);

        $disposal->load([
            'asset:id,number,product_id,status',
            'asset.product:id,code,name',
            'requestedBy:id,name',
            'decidedBy:id,name',
        ]);

        return Inertia::render('Transaction/AssetManagement/Disposal/Show', [
            'disposal' => $disposal,
            'can_decide' => $request->user()?->can('asset.dispose') === true
                && $disposal->status === AssetDisposal::STATUS_PENDING_APPROVAL,
        ]);
    }

    public function print(Request $request, AssetDisposal $disposal): Response
    {
        abort_unless($request->user()?->can('asset.view'), 403);
        abort_unless($disposal->status === AssetDisposal::STATUS_PENDING_APPROVAL, 404);

        $disposal->load('requestedBy:id,name');

        return Inertia::render('Transaction/AssetManagement/Disposal/Print', [
            'disposal' => $disposal,
            'auto_print' => $request->boolean('print'),
            'close_after_print' => $request->boolean('closeAfterPrint'),
        ]);
    }

    public function approve(ApproveAssetDisposalRequest $request, AssetDisposal $disposal, AssetDisposalService $service): RedirectResponse
    {
        $actor = $request->user();
        abort_if($actor === null, 401);

        $service->approve($disposal, $actor, $request->validated('decision_notes'));

        return to_route('transaction.asset-management.disposals.show', $disposal)
            ->with('success', 'Pelepasan disetujui. Aset kini berstatus Disposed dan tetap tersimpan di riwayat.');
    }

    public function reject(RejectAssetDisposalRequest $request, AssetDisposal $disposal, AssetDisposalService $service): RedirectResponse
    {
        $actor = $request->user();
        abort_if($actor === null, 401);

        $service->reject($disposal, $actor, $request->validated('decision_notes'));

        return to_route('transaction.asset-management.disposals.show', $disposal)
            ->with('success', 'Pengajuan pelepasan ditolak. Status aset dikembalikan menjadi Aktif.');
    }

    public function downloadEvidence(Request $request, AssetDisposal $disposal): StreamedResponse
    {
        abort_unless($request->user()?->can('asset.view'), 403);
        abort_if($disposal->evidence_path === null || ! Storage::disk('local')->exists($disposal->evidence_path), 404);

        $extension = pathinfo($disposal->evidence_path, PATHINFO_EXTENSION);
        $fileName = $disposal->number.'-dokumen-pendukung'.($extension !== '' ? '.'.$extension : '');

        return Storage::disk('local')->download($disposal->evidence_path, $fileName);
    }
}
