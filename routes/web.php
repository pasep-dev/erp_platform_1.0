<?php

use App\Http\Controllers\Administrator\ActivityLogController;
use App\Http\Controllers\Administrator\MenuControlController;
use App\Http\Controllers\Administrator\NumberingDocumentController;
use App\Http\Controllers\Administrator\UserManagement\AccessControlController;
use App\Http\Controllers\Administrator\UserManagement\RoleController;
use App\Http\Controllers\Administrator\UserManagement\UserAccountController;
use App\Http\Controllers\Administrator\Workflow\ApprovalWorkflowController;
use App\Http\Controllers\Approval\ApprovalHistoryController;
use App\Http\Controllers\Approval\ApprovalInboxController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Master\Organization\BranchController;
use App\Http\Controllers\Master\Organization\CompanyController;
use App\Http\Controllers\Master\Organization\CostCenterController;
use App\Http\Controllers\Master\Organization\DepartmentController;
use App\Http\Controllers\Master\Organization\LocationController;
use App\Http\Controllers\Master\Procurement\PaymentTermController;
use App\Http\Controllers\Master\Procurement\TaxController;
use App\Http\Controllers\Master\Procurement\UnitOfMeasureController;
use App\Http\Controllers\Master\Procurement\VendorController;
use App\Http\Controllers\Master\Procurement\VendorTypeController;
use App\Http\Controllers\Master\Product\BrandController;
use App\Http\Controllers\Master\Product\CategoryController;
use App\Http\Controllers\Master\Product\LicenseTypeController;
use App\Http\Controllers\Master\Product\ModelController;
use App\Http\Controllers\Master\Product\ProductController;
use App\Http\Controllers\Master\Product\TypeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings\NumberingModuleSettingsController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Transaction\AssetManagement\AssetAssignmentController;
use App\Http\Controllers\Transaction\AssetManagement\AssetAuditController;
use App\Http\Controllers\Transaction\AssetManagement\AssetDirectoryController;
use App\Http\Controllers\Transaction\AssetManagement\AssetDisposalController;
use App\Http\Controllers\Transaction\AssetManagement\AssetIncidentController;
use App\Http\Controllers\Transaction\AssetManagement\AssetMaintenanceController;
use App\Http\Controllers\Transaction\AssetManagement\AssetRegistrationController;
use App\Http\Controllers\Transaction\AssetManagement\AssetTransferController;
use App\Http\Controllers\Transaction\Inventory\InventoryController;
use App\Http\Controllers\Transaction\Inventory\InventoryManualReceiptController;
use App\Http\Controllers\Transaction\Inventory\InventoryRequestController;
use App\Http\Controllers\Transaction\Inventory\InventoryStocktakeController;
use App\Http\Controllers\Transaction\Procurement\GoodsReceiptController;
use App\Http\Controllers\Transaction\Procurement\PurchaseOrderController;
use App\Http\Controllers\Transaction\Procurement\PurchaseRequestController;
use App\Models\User;
use App\Services\DashboardDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [
        AuthenticatedSessionController::class,
        'create',
    ])->name('login');

    Route::post('/login', [
        AuthenticatedSessionController::class,
        'store',
    ])->name('login.store');
});

Route::middleware([
    'auth',
    'prevent-back-history',
    'route-permission',
])->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'show'])
        ->name('profile.show');

    Route::get('/', function (Request $request, DashboardDataService $dashboardData) {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return inertia('Dashboard/Index', $dashboardData->forUser($user));
    })->name('dashboard');

    Route::get('/settings', [
        SettingsController::class,
        'edit',
    ])->middleware('can:system.settings.view')
        ->name('settings.edit');

    Route::put('/settings', [
        SettingsController::class,
        'update',
    ])->middleware('can:system.settings.update')
        ->name('settings.update');

    Route::patch('/settings/modules/{numberingModule:code}', [
        NumberingModuleSettingsController::class,
        'update',
    ])->middleware('can:system.settings.update')
        ->name('settings.modules.update');

    Route::post('/settings/modules', [
        NumberingModuleSettingsController::class,
        'store',
    ])->middleware('can:system.settings.update')
        ->name('settings.modules.store');

    Route::post('/settings/backup', [
        SettingsController::class,
        'backup',
    ])->middleware([
        'can:system.settings.backup',
        'throttle:2,5',
    ])->name('settings.backup');

    Route::delete('/settings/backup/{filename}', [
        SettingsController::class,
        'destroyBackup',
    ])->middleware([
        'can:system.settings.backup',
        'throttle:10,1',
    ])->where('filename', 'database-[A-Za-z0-9._-]+\.dump')
        ->name('settings.backup.destroy');

    Route::post('/logout', [
        AuthenticatedSessionController::class,
        'destroy',
    ])->name('logout');

    Route::prefix('master/organization')->name('master.organization.')->group(function () {
        Route::resource('companies', CompanyController::class)
            ->except(['show']);
        Route::resource('branches', BranchController::class)
            ->except(['show']);
        Route::resource('departments', DepartmentController::class)
            ->except(['show']);
        Route::resource('locations', LocationController::class)
            ->except(['show']);
        Route::resource('cost-centers', CostCenterController::class)
            ->except(['show']);
    });
    Route::prefix('master/product')->name('master.product.')->group(function () {
        Route::resource('categories', CategoryController::class)
            ->except(['show']);
        Route::resource('types', TypeController::class)
            ->except(['show']);
        Route::resource('brands', BrandController::class)
            ->except(['show']);
        Route::resource('models', ModelController::class)
            ->except(['show']);
        Route::resource('license-types', LicenseTypeController::class)
            ->except(['show']);
        Route::resource('products', ProductController::class)
            ->names('products');
    });

    Route::resource(
        'administrator/approval-workflows',
        ApprovalWorkflowController::class
    )->names('administrator.approval-workflows');

    Route::resource(
        'administrator/numbering-documents',
        NumberingDocumentController::class,
    )->only(['index', 'store', 'update'])
        ->names('administrator.numbering-documents');

    Route::get('/administrator/users', [
        UserAccountController::class,
        'index',
    ])->middleware('can:users.view')
        ->name('administrator.users.index');

    Route::get('/administrator/activity-logs', [
        ActivityLogController::class,
        'index',
    ])->middleware('can:activity-logs.view')
        ->name('administrator.activity-logs.index');

    Route::post('/administrator/users', [
        UserAccountController::class,
        'store',
    ])->middleware('can:users.create')
        ->name('administrator.users.store');

    Route::put('/administrator/users/{user}', [
        UserAccountController::class,
        'update',
    ])->whereNumber('user')
        ->middleware('can:users.update')
        ->name('administrator.users.update');

    Route::delete('/administrator/users/{user}', [
        UserAccountController::class,
        'destroy',
    ])->whereNumber('user')
        ->middleware('can:users.delete')
        ->name('administrator.users.destroy');

    Route::get('/administrator/roles', [
        RoleController::class,
        'index',
    ])->middleware('can:roles.view')
        ->name('administrator.roles.index');

    Route::post('/administrator/roles', [
        RoleController::class,
        'store',
    ])->middleware('can:roles.create')
        ->name('administrator.roles.store');

    Route::put('/administrator/roles/{role}', [
        RoleController::class,
        'update',
    ])->whereNumber('role')
        ->middleware('can:roles.update')
        ->name('administrator.roles.update');

    Route::delete('/administrator/roles/{role}', [
        RoleController::class,
        'destroy',
    ])->whereNumber('role')
        ->middleware('can:roles.delete')
        ->name('administrator.roles.destroy');

    Route::get('/administrator/access-control', [
        AccessControlController::class,
        'index',
    ])->middleware('can:roles.update')
        ->name('administrator.access-control.index');

    Route::get('/administrator/access-control/{type}/{target}/edit', [
        AccessControlController::class,
        'edit',
    ])->whereIn('type', ['role', 'user'])
        ->whereNumber('target')
        ->middleware('can:roles.update')
        ->name('administrator.access-control.edit');

    Route::put('/administrator/access-control/{type}/{target}', [
        AccessControlController::class,
        'update',
    ])->whereIn('type', ['role', 'user'])
        ->whereNumber('target')
        ->middleware('can:roles.update')
        ->name('administrator.access-control.update');

    Route::get('/administrator/menu-control', [MenuControlController::class, 'index'])
        ->name('administrator.menu-control.index');

    Route::get('/administrator/menu-control/{type}/{target}/edit', [MenuControlController::class, 'edit'])
        ->whereIn('type', ['role', 'user'])
        ->whereNumber('target')
        ->name('administrator.menu-control.edit');

    Route::put('/administrator/menu-control/{type}/{target}', [MenuControlController::class, 'update'])
        ->whereIn('type', ['role', 'user'])
        ->whereNumber('target')
        ->name('administrator.menu-control.update');

    Route::prefix('approval')->name('approval.')->group(function () {
        Route::get('/inbox', [
            ApprovalInboxController::class,
            'index',
        ])->name('inbox.index');

        Route::post('/inbox/{approvalRequest}/approve', [
            ApprovalInboxController::class,
            'approve',
        ])->name('inbox.approve');

        Route::post('/inbox/{approvalRequest}/reject', [
            ApprovalInboxController::class,
            'reject',
        ])->name('inbox.reject');

        Route::get('/history', [
            ApprovalHistoryController::class,
            'index',
        ])->name('history.index');
    });

    Route::prefix('master/procurement')->name('master.procurement.')->group(function () {
        Route::resource('vendors', VendorController::class)
            ->except(['show']);
        Route::resource('units-of-measure', UnitOfMeasureController::class)
            ->except(['show']);
        Route::resource('payment-terms', PaymentTermController::class)
            ->except(['show']);
        Route::resource('taxes', TaxController::class)
            ->except(['show']);
        Route::resource('vendor-types', VendorTypeController::class)
            ->except(['show']);
    });

    Route::prefix('transaction/procurement')
        ->name('transaction.procurement.')
        ->group(function () {
            Route::post('goods-receipts/{goodsReceipt}/submit', [GoodsReceiptController::class, 'submit'])
                ->name('goods-receipts.submit');
            Route::resource('goods-receipts', GoodsReceiptController::class);

            Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])
                ->name('purchase-orders.index');
            Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
                ->name('purchase-orders.show');
            Route::get('purchase-orders/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])
                ->name('purchase-orders.edit');
            Route::put('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])
                ->name('purchase-orders.update');
            Route::post('purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])
                ->name('purchase-orders.submit');
            Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
                ->name('purchase-orders.cancel');

            Route::resource('purchase-requests', PurchaseRequestController::class)
                ->only([
                    'index',
                    'create',
                    'edit',
                    'store',
                    'update',
                    'show',
                ]);

            Route::delete(
                'purchase-requests/{purchaseRequest}',
                [PurchaseRequestController::class, 'destroy'],
            )->name('purchase-requests.destroy');

            Route::post(
                'purchase-requests/{purchaseRequest}/cancel',
                [PurchaseRequestController::class, 'cancel'],
            )->name('purchase-requests.cancel');

            Route::post(
                'purchase-requests/{purchaseRequest}/mark-approved',
                [PurchaseRequestController::class, 'markApproved'],
            )->name('purchase-requests.mark-approved');

            Route::get(
                'purchase-requests/{purchaseRequest}/approval-document',
                [PurchaseRequestController::class, 'downloadApprovalDocument'],
            )->name('purchase-requests.approval-document');

            Route::post(
                'purchase-requests/{purchaseRequest}/submit',
                [PurchaseRequestController::class, 'submit']
            )->name('purchase-requests.submit');
        });

    Route::prefix('transaction/inventory')->name('transaction.inventory.')->controller(InventoryController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/stocks', 'stocks')->name('stocks.index');
        Route::get('/settings', 'settings')->name('settings');
        Route::get('/receipts', 'receipts')->name('receipts.index');
        Route::get('/receipts/manual/create', [InventoryManualReceiptController::class, 'create'])->name('receipts.manual.create');
        Route::post('/receipts/manual', [InventoryManualReceiptController::class, 'store'])->name('receipts.manual.store');
        Route::get('/receipts/manual/{inventoryDocument}', [InventoryManualReceiptController::class, 'show'])->name('receipts.manual.show');
        Route::post('/receipts/manual/{inventoryDocument}/mark-approved', [InventoryManualReceiptController::class, 'markApproved'])->name('receipts.manual.mark-approved');
        Route::get('/receipts/manual/{inventoryDocument}/approval-document', [InventoryManualReceiptController::class, 'downloadApprovalDocument'])->name('receipts.manual.approval-document');
        Route::get('/transactions', 'transactions')->name('transactions.index');
        Route::get('/issues/create', 'createIssue')->name('issues.create');
        Route::post('/issues', 'storeIssue')->name('issues.store');
        Route::get('/transfers/create', 'createTransfer')->name('transfers.create');
        Route::post('/transfers', 'storeTransfer')->name('transfers.store');
        Route::get('/returns/create', 'createReturn')->name('returns.create');
        Route::post('/returns', 'storeReturn')->name('returns.store');
        Route::get('/adjustments/create', 'createAdjustment')->name('adjustments.create');
        Route::post('/adjustments', 'storeAdjustment')->name('adjustments.store');
        Route::put('/departments/{department}/warehouses/{location}/products/{product}/minimum-stock', 'saveMinimumStock')->name('minimum-stock.update');
        Route::get('/products/{product}/locations/{location}/stock-card', 'stockCard')->name('stock-card');
        Route::post('/receipt-integrations/{integration}/serials', 'receiveSerials')->name('receipt-integrations.serials');
        Route::post('/receipt-integrations/{integration}/retry', 'retryReceipt')->name('receipt-integrations.retry');
    });

    Route::prefix('transaction/inventory/stocktakes')->name('transaction.inventory.stocktakes.')->controller(InventoryStocktakeController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{stocktake}', 'show')->name('show');
        Route::put('/{stocktake}', 'update')->name('update');
        Route::post('/{stocktake}/post', 'post')->name('post');
    });

    Route::prefix('transaction/inventory/requests')->name('transaction.inventory.requests.')->controller(InventoryRequestController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{inventoryRequest}', 'show')->name('show');
        Route::post('/{inventoryRequest}/submit', 'submit')->name('submit');
        Route::post('/{inventoryRequest}/fulfill', 'fulfill')->name('fulfill');
    });

    Route::prefix('transaction/asset-management/assets')
        ->name('transaction.asset-management.directory.')
        ->controller(AssetDirectoryController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/{asset}', 'show')->whereUlid('asset')->name('show');
            Route::get('/template', 'template')->name('template');
            Route::post('/import', 'import')->name('import');
            Route::get('/export', 'export')->name('export');
            Route::get('/qr-codes/print', 'printQrCodes')->name('qr-codes.print');
        });

    Route::prefix('transaction/asset-management/registrations')
        ->name('transaction.asset-management.registrations.')
        ->controller(AssetRegistrationController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{asset}', 'show')->name('show');
        });

    Route::prefix('transaction/asset-management/assignments')
        ->name('transaction.asset-management.assignments.')
        ->controller(AssetAssignmentController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/history/{assignmentHistory}/print', 'printHistory')->name('history.print');
            Route::get('/{asset}/assign', 'create')->name('create');
            Route::post('/{asset}/assign', 'assign')->name('assign');
            Route::post('/{asset}/return', 'returnAsset')->name('return');
            Route::get('/{asset}', 'show')->name('show');
        });

    Route::prefix('transaction/asset-management/audits')
        ->name('transaction.asset-management.audits.')
        ->controller(AssetAuditController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{audit}/print', 'print')->name('print');
            Route::post('/{audit}/scan', 'scan')->name('scan');
            Route::put('/{audit}/items/{item}', 'updateItem')->name('items.update');
            Route::post('/{audit}/items/{item}/resolve', 'resolveItem')->name('items.resolve');
            Route::post('/{audit}/complete', 'complete')->name('complete');
            Route::get('/{audit}', 'show')->name('show');
        });

    Route::prefix('transaction/asset-management/incidents')
        ->name('transaction.asset-management.incidents.')
        ->controller(AssetIncidentController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::post('/{incident}/investigate', 'investigate')->name('investigate');
            Route::post('/{incident}/resolve', 'resolve')->name('resolve');
            Route::post('/{incident}/dismiss', 'dismiss')->name('dismiss');
            Route::get('/{incident}/evidence', 'downloadEvidence')->name('evidence');
            Route::get('/{incident}', 'show')->name('show');
        });

    Route::prefix('transaction/asset-management/transfers')
        ->name('transaction.asset-management.transfers.')
        ->controller(AssetTransferController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/create/{asset?}', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{transfer}', 'show')->name('show');
        });

    Route::prefix('transaction/asset-management/reports')
        ->name('transaction.asset-management.reports.')
        ->controller(AssetDirectoryController::class)
        ->group(function (): void {
            Route::get('/', 'reportIndex')->name('index');
            Route::get('/export', 'export')->name('export');
        });

    Route::prefix('transaction/asset-management/disposals')
        ->name('transaction.asset-management.disposals.')
        ->controller(AssetDisposalController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/create/{asset?}', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::post('/{disposal}/approve', 'approve')->name('approve');
            Route::post('/{disposal}/reject', 'reject')->name('reject');
            Route::get('/{disposal}/evidence', 'downloadEvidence')->name('evidence');
            Route::get('/{disposal}/print', 'print')->name('print');
            Route::get('/{disposal}', 'show')->name('show');
        });

    Route::prefix('transaction/asset-management/maintenance')
        ->name('transaction.asset-management.maintenance.')
        ->controller(AssetMaintenanceController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/create/{asset?}', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::post('/{maintenance}/submit', 'submit')->name('submit');
            Route::post('/{maintenance}/mark-approved', 'markApproved')->name('mark-approved');
            Route::post('/{maintenance}/mark-rejected', 'markRejected')->name('mark-rejected');
            Route::get('/{maintenance}/approval-document', 'downloadApprovalDocument')->name('approval-document');
            Route::get('/{maintenance}/print', 'print')->name('print');
            Route::get('/{maintenance}/edit', 'edit')->name('edit');
            Route::put('/{maintenance}', 'update')->name('update');
            Route::post('/{maintenance}/complete', 'complete')->name('complete');
            Route::post('/{maintenance}/cancel', 'cancel')->name('cancel');
            Route::get('/{maintenance}', 'show')->name('show');
        });
});
