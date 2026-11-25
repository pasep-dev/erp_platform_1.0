<?php

namespace App\Http\Controllers\Transaction\AssetManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\AssetManagement\StoreAssetRegistrationRequest;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Master\Product\Product;
use App\Models\User;
use App\Services\AssetManagement\AssetRegistrationService;
use App\Services\DepartmentAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetRegistrationController extends Controller
{
    public function index(Request $request, AssetRegistrationService $service): Response
    {
        abort_unless($request->user()?->can('registration.view'), 403);

        return Inertia::render('Transaction/AssetManagement/Registration/Index', [
            'queue' => $service->registrationQueue(),
            'can_create' => $request->user()?->can('registration.create') === true,
        ]);
    }

    public function create(Request $request, AssetRegistrationService $service, DepartmentAccessService $departmentAccess): Response
    {
        $user = $request->user();
        abort_unless($user?->can('registration.create'), 403);
        $accessibleDepartmentIds = $departmentAccess->departmentIds($user);

        $goodsReceiptItemId = $request->string('goods_receipt_item_id')->toString();
        $source = $goodsReceiptItemId !== ''
            ? $service->procurementDetails($goodsReceiptItemId)
            : null;

        $companies = Company::query()
            ->whereIn('id', Department::query()->whereIn('id', $accessibleDepartmentIds)->select('company_id'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        $departments = $departmentAccess->scopeDepartments(Department::query(), $user)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'company_id', 'code', 'name']);
        $locations = Location::query()
            ->whereIn('company_id', Department::query()->whereIn('id', $accessibleDepartmentIds)->select('company_id'))
            ->with('branch:id,code,name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'company_id', 'branch_id', 'code', 'name']);
        $products = Product::query()
            ->where('group', 'ASSET')
            ->where('is_active', true)
            ->whereHas('departments', fn ($query) => $query->whereIn('mst_org_departments.id', $accessibleDepartmentIds))
            ->with([
                'category:id,name',
                'type:id,name',
                'brand:id,name',
                'model:id,name',
                'departments' => fn ($query) => $query
                    ->whereIn('mst_org_departments.id', $accessibleDepartmentIds)
                    ->select('mst_org_departments.id'),
            ])
            ->orderBy('name')
            ->get(['id', 'category_id', 'type_id', 'brand_id', 'model_id', 'code', 'name'])
            ->map(fn (Product $product): array => [
                ...$product->toArray(),
                'department_ids' => $product->departments->pluck('id')->all(),
            ]);
        $users = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($accessibleDepartmentIds): void {
                $query->whereIn('department_id', $accessibleDepartmentIds)
                    ->orWhereHas('additionalDepartments', fn ($departments) => $departments->whereIn('mst_org_departments.id', $accessibleDepartmentIds))
                    ->orWhereHas('roles', fn ($roles) => $roles->whereIn('name', ['Administrator', 'Owner']));
            })
            ->orderBy('name')
            ->with('additionalDepartments:id')
            ->get(['id', 'company_id', 'department_id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'company_id' => $user->company_id,
                'name' => $user->name,
                'email' => $user->email,
                'department_ids' => $user->hasGlobalDepartmentAccess()
                    ? $accessibleDepartmentIds
                    : collect([$user->department_id])
                        ->merge($user->additionalDepartments->pluck('id'))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
            ]);

        return Inertia::render('Transaction/AssetManagement/Registration/Create', [
            'source' => $source,
            'companies' => $companies,
            'departments' => $departments,
            'locations' => $locations,
            'products' => $products,
            'users' => $users,
        ]);
    }

    public function store(StoreAssetRegistrationRequest $request, AssetRegistrationService $service): RedirectResponse
    {
        $data = $request->validated();
        $assets = isset($data['goods_receipt_item_id'])
            ? $service->registerFromProcurement($request->user(), $data)
            : $service->registerExisting($request->user(), $data);

        return to_route('transaction.asset-management.registrations.index')
            ->with('success', count($assets).' unit aset berhasil diregistrasi.');
    }

    public function show(Request $request, Asset $asset): Response
    {
        abort_unless($request->user()?->can('registration.view'), 403);

        $asset->load([
            'product.category:id,name',
            'product.type:id,name',
            'product.brand:id,name',
            'product.model:id,name',
            'company:id,name,code',
            'department:id,name,code',
            'location:id,name,code',
            'branch:id,name,code',
            'custodian:id,name,email',
            'goodsReceiptItem.goodsReceipt:id,number,source_type,purchase_order_id,purchase_request_id',
            'goodsReceiptItem.goodsReceipt.purchaseOrder:id,number',
            'goodsReceiptItem.goodsReceipt.purchaseRequest:id,number',
            'registeredBy:id,name',
        ]);

        return Inertia::render('Transaction/AssetManagement/Registration/Show', [
            'asset' => $asset,
        ]);
    }
}
