<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Workflow\ApprovalAction;
use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use App\Models\Transaction\Inventory\InventoryDocument;
use App\Models\Transaction\Inventory\InventoryRequest;
use App\Models\Transaction\Procurement\PurchaseRequest;
use App\Services\DepartmentAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalHistoryController extends Controller
{
    public function index(Request $request, DepartmentAccessService $departmentAccess): Response
    {
        $user = $request->user();
        $departmentIds = $departmentAccess->departmentIds($user);

        $search = trim((string) $request->input('search', ''));
        $module = $request->input('module');
        $action = $request->input('action');
        $perPage = (int) $request->input('per_page', 10);

        if (! in_array($perPage, [5, 10, 15, 25, 50], true)) {
            $perPage = 10;
        }

        $histories = ApprovalAction::query()
            ->with([
                'request.workflow.company',
                'request.workflow.department',
                'request.submitter',
                'request.levels.approver',
                'requestLevel.approver',
                'user',
            ])
            ->where('user_id', $user->id)
            ->whereHas('request', function ($query) use ($departmentIds): void {
                $query->where(function ($query) use ($departmentIds): void {
                    $query->where(function ($query) use ($departmentIds): void {
                        $query->where('module', 'INVENTORY_REQUEST')
                            ->where('document_type', 'INVENTORY_REQUEST')
                            ->whereIn('document_id', InventoryRequest::query()->whereIn('department_id', $departmentIds)->select('id'));
                    })->orWhere(function ($query) use ($departmentIds): void {
                        $query->where('module', 'INVENTORY_OPENING_BALANCE')
                            ->where('document_type', 'INVENTORY_OPENING_BALANCE')
                            ->whereIn('document_id', InventoryDocument::query()
                                ->where('document_type', InventoryDocument::TYPE_OPENING_BALANCE)
                                ->whereIn('department_id', $departmentIds)
                                ->select('id'));
                    })->orWhere(function ($query) use ($departmentIds): void {
                        $query->where('module', 'PURCHASE_REQUEST')
                            ->where('document_type', 'PURCHASE_REQUEST')
                            ->whereIn('document_id', PurchaseRequest::query()->whereIn('department_id', $departmentIds)->select('id'));
                    })->orWhere(function ($query) use ($departmentIds): void {
                        $query->where('module', 'ASSET_MAINTENANCE')
                            ->where('document_type', 'ASSET_MAINTENANCE')
                            ->whereIn('document_id', AssetMaintenanceRecord::query()
                                ->whereHas('asset', fn ($assetQuery) => $assetQuery->whereIn('department_id', $departmentIds))
                                ->select('id'));
                    });
                });
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->whereHas('request', function ($query) use ($search) {
                            $query
                                ->where(
                                    'document_id',
                                    'ilike',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'document_type',
                                    'ilike',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'module',
                                    'ilike',
                                    "%{$search}%"
                                );
                        })
                        ->orWhereHas(
                            'request.workflow',
                            function ($query) use ($search) {
                                $query->where(
                                    'name',
                                    'ilike',
                                    "%{$search}%"
                                );
                            }
                        );
                });
            })
            ->when(
                $module && $module !== 'ALL',
                function ($query) use ($module) {
                    $query->whereHas(
                        'request',
                        function ($query) use ($module) {
                            $query->where('module', $module);
                        }
                    );
                }
            )
            ->when(
                $action && $action !== 'ALL',
                function ($query) use ($action) {
                    $query->where('action', $action);
                }
            )
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Approval/History/Index', [
            'histories' => $histories,
            'filters' => [
                'search' => $search,
                'module' => $module,
                'action' => $action,
                'per_page' => $perPage,
            ],
        ]);
    }
}
