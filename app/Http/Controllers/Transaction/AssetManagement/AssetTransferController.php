<?php

namespace App\Http\Controllers\Transaction\AssetManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\AssetManagement\StoreAssetTransferRequest;
use App\Models\Master\Organization\Branch;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetTransferHistory;
use App\Services\AssetManagement\AssetTransferService;
use App\Services\AssetManagement\BranchWarehouseResolver;
use App\Services\BranchAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetTransferController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('asset.view'), 403);

        $search = trim($request->string('search')->toString());
        $requestedPerPage = $request->integer('per_page', 15);
        $perPage = in_array($requestedPerPage, [5, 15, 25, 50, 100], true) ? $requestedPerPage : 15;

        $transfers = AssetTransferHistory::query()
            ->with(['asset:id,number,product_id', 'asset.product:id,code,name', 'performedBy:id,name'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('number', 'ilike', "%{$search}%")
                        ->orWhere('reason', 'ilike', "%{$search}%")
                        ->orWhereHas('asset', fn (Builder $asset) => $asset->where('number', 'ilike', "%{$search}%")
                            ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'ilike', "%{$search}%")
                                ->orWhere('code', 'ilike', "%{$search}%")));
                });
            })
            ->orderByDesc('effective_at')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Transaction/AssetManagement/Transfer/Index', [
            'transfers' => $transfers,
            'filters' => ['search' => $search],
            'can_manage' => $request->user()?->can('asset.transfer') === true,
        ]);
    }

    public function create(
        Request $request,
        BranchAccessService $branchAccess,
        BranchWarehouseResolver $warehouseResolver,
        ?Asset $asset = null,
    ): Response {
        $user = $request->user();
        abort_unless($user?->can('asset.transfer'), 403);
        if ($asset !== null) {
            abort_unless($asset->status === Asset::STATUS_ACTIVE, 422);
            $asset->load(['product:id,code,name', 'company:id,code,name', 'department:id,code,name', 'branch:id,code,name', 'location:id,code,name']);
        }

        $branches = $branchAccess->scopeBranches(Branch::query(), $user)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'company_id', 'code', 'name']);

        return Inertia::render('Transaction/AssetManagement/Transfer/Create', [
            'initial_asset' => $asset,
            'assets' => Asset::query()
                ->where('status', Asset::STATUS_ACTIVE)
                ->with(['product:id,code,name', 'company:id,code,name', 'department:id,code,name', 'branch:id,code,name', 'location:id,code,name'])
                ->orderBy('number')
                ->get(['id', 'number', 'serial_number', 'product_id', 'company_id', 'department_id', 'branch_id', 'location_id']),
            'branches' => $warehouseResolver->options($branches),
        ]);
    }

    public function store(StoreAssetTransferRequest $request, AssetTransferService $service): RedirectResponse
    {
        $asset = Asset::query()->findOrFail($request->validated('asset_id'));
        $actor = $request->user();
        abort_if($actor === null, 401);

        $transfer = $service->transfer($asset, $request->safe()->except('asset_id'), $actor);

        return to_route('transaction.asset-management.transfers.show', $transfer)
            ->with('success', 'Perpindahan aset berhasil dicatat beserta snapshot asal dan tujuan.');
    }

    public function show(Request $request, AssetTransferHistory $transfer): Response
    {
        abort_unless($request->user()?->can('asset.view'), 403);

        $transfer->load(['asset:id,number,product_id', 'asset.product:id,code,name', 'performedBy:id,name']);

        return Inertia::render('Transaction/AssetManagement/Transfer/Show', [
            'transfer' => $transfer,
        ]);
    }
}
