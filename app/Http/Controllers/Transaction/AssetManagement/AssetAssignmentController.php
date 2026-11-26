<?php

namespace App\Http\Controllers\Transaction\AssetManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\AssetManagement\AssignAssetRequest;
use App\Http\Requests\Transaction\AssetManagement\ReturnAssetRequest;
use App\Models\Master\Organization\Location;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetAssignmentHistory;
use App\Models\User;
use App\Services\AssetManagement\AssetAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetAssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('asset.view'), 403);

        $requestedPerPage = $request->integer('per_page', 15);
        $perPage = in_array($requestedPerPage, [5, 15, 25, 50, 100], true) ? $requestedPerPage : 15;
        $search = trim($request->string('search')->toString());
        $assignmentStatus = $request->string('assignment_status', 'ALL')->toString();

        $assets = Asset::query()
            ->whereIn('status', [Asset::STATUS_ACTIVE, Asset::STATUS_MISSING])
            ->with([
                'product:id,code,name',
                'company:id,code,name',
                'department:id,code,name',
                'location:id,code,name,location_type',
                'custodian:id,name,email',
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('number', 'ilike', "%{$search}%")
                        ->orWhere('serial_number', 'ilike', "%{$search}%")
                        ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'ilike', "%{$search}%")
                            ->orWhere('code', 'ilike', "%{$search}%"))
                        ->orWhereHas('location', fn (Builder $location) => $location->where('name', 'ilike', "%{$search}%")
                            ->orWhere('code', 'ilike', "%{$search}%"))
                        ->orWhereHas('custodian', fn (Builder $custodian) => $custodian->where('name', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%"));
                });
            })
            ->when($assignmentStatus === Asset::ASSIGNMENT_LOCATION, fn (Builder $query) => $query->where('assignment_type', Asset::ASSIGNMENT_LOCATION))
            ->when($assignmentStatus === Asset::ASSIGNMENT_USER, fn (Builder $query) => $query->where('assignment_type', Asset::ASSIGNMENT_USER))
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Transaction/AssetManagement/Assignment/Index', [
            'assets' => $assets,
            'filters' => [
                'search' => $search,
                'assignment_status' => in_array($assignmentStatus, [Asset::ASSIGNMENT_LOCATION, Asset::ASSIGNMENT_USER], true) ? $assignmentStatus : 'ALL',
            ],
            'can_manage' => $request->user()?->can('asset.transfer') === true,
        ]);
    }

    public function create(Request $request, Asset $asset): Response
    {
        abort_unless($request->user()?->can('asset.transfer'), 403);
        abort_unless($asset->status === Asset::STATUS_ACTIVE, 422);

        $asset->load([
            'product:id,code,name',
            'company:id,code,name',
            'department:id,code,name',
            'location:id,code,name,location_type',
            'custodian:id,name,email',
        ]);

        $users = User::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($asset): void {
                $query->whereNull('company_id')
                    ->orWhere('company_id', $asset->company_id);
            })
            ->orderBy('name')
            ->get(['id', 'company_id', 'name', 'email']);

        $locations = Location::query()
            ->withoutGlobalScope('branch_access')
            ->where('company_id', $asset->company_id)
            ->where(function (Builder $query) use ($asset): void {
                $query->whereNull('branch_id')
                    ->orWhere('branch_id', $asset->branch_id);
            })
            ->where('is_active', true)
            ->with('parent:id,code,name')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'code', 'name', 'location_type']);

        return Inertia::render('Transaction/AssetManagement/Assignment/Create', [
            'asset' => $asset,
            'users' => $users,
            'locations' => $locations,
            'is_transfer' => $asset->assignment_type === Asset::ASSIGNMENT_USER,
            'condition_options' => Asset::CONDITION_OPTIONS,
        ]);
    }

    public function assign(AssignAssetRequest $request, Asset $asset, AssetAssignmentService $service): RedirectResponse
    {
        $assignee = $request->validated('assignee_id') !== null
            ? User::query()->findOrFail($request->validated('assignee_id'))
            : null;
        $location = $request->validated('location_id') !== null
            ? Location::query()->findOrFail($request->validated('location_id'))
            : null;

        $service->assign(
            $asset,
            $request->validated('assignment_target'),
            $assignee,
            $location,
            $request->user(),
            $request->validated('condition'),
            $request->validated('effective_at'),
            $request->validated('remarks'),
        );

        return to_route('transaction.asset-management.assignments.show', $asset)
            ->with('success', 'Penempatan aset berhasil disimpan dan masuk ke riwayat.');
    }

    public function returnAsset(ReturnAssetRequest $request, Asset $asset, AssetAssignmentService $service): RedirectResponse
    {
        $service->returnAsset(
            $asset,
            $request->user(),
            $request->validated('condition'),
            $request->validated('effective_at'),
            $request->validated('remarks'),
        );

        return to_route('transaction.asset-management.assignments.show', $asset)
            ->with('success', 'Aset berhasil dilepas dari pengguna dan kembali dalam penempatan lokasi.');
    }

    public function show(Request $request, Asset $asset): Response
    {
        abort_unless($request->user()?->can('asset.view'), 403);

        $asset->load([
            'product:id,code,name,category_id,type_id,brand_id,model_id',
            'product.category:id,name',
            'product.type:id,name',
            'product.brand:id,name',
            'product.model:id,name',
            'company:id,code,name',
            'department:id,code,name',
            'location:id,code,name,location_type',
            'branch:id,code,name',
            'custodian:id,name,email',
            'assignmentHistories' => fn (HasMany $query): HasMany => $query
                ->with('performedBy:id,name')
                ->orderByDesc('effective_at')
                ->orderByDesc('created_at'),
            'maintenanceRecords' => fn (HasMany $query): HasMany => $query
                ->orderByDesc('requested_at')
                ->orderByDesc('id'),
            'transferHistories' => fn (HasMany $query): HasMany => $query
                ->with('performedBy:id,name')
                ->orderByDesc('effective_at')
                ->orderByDesc('created_at'),
            'disposals' => fn (HasMany $query): HasMany => $query
                ->with(['requestedBy:id,name', 'decidedBy:id,name'])
                ->orderByDesc('created_at'),
        ]);

        return Inertia::render('Transaction/AssetManagement/Assignment/Show', [
            'asset' => $asset,
            'can_manage' => $request->user()?->can('asset.transfer') === true,
            'can_create_maintenance' => $request->user()?->can('asset.create') === true,
            'condition_options' => Asset::CONDITION_OPTIONS,
            'can_dispose' => $request->user()?->can('asset.dispose') === true,
        ]);
    }

    public function printHistory(Request $request, AssetAssignmentHistory $assignmentHistory): Response
    {
        abort_unless($request->user()?->can('asset.view'), 403);

        $assignmentHistory->load([
            'asset:id,number,serial_number,product_id,company_id,department_id,location_id',
            'asset.product:id,code,name',
            'asset.company:id,code,name',
            'asset.department:id,code,name',
            'asset.location:id,code,name',
            'performedBy:id,name',
        ]);

        return Inertia::render('Transaction/AssetManagement/Assignment/PrintHandover', [
            'history' => $assignmentHistory,
        ]);
    }
}
