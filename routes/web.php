<?php

use App\Http\Controllers\Operation\Procurement\PurchaseRequestController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Master\AssetManagement\BrandController;
use App\Http\Controllers\Master\AssetManagement\CategoryController;
use App\Http\Controllers\Master\AssetManagement\LicenseTypeController;
use App\Http\Controllers\Master\AssetManagement\ModelController;
use App\Http\Controllers\Master\AssetManagement\TypeController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Master\Organization\CompanyController;
use App\Http\Controllers\Master\Organization\BranchController;
use App\Http\Controllers\Master\Organization\CostCenterController;
use App\Http\Controllers\Master\Organization\DepartmentController;
use App\Http\Controllers\Master\Organization\LocationController;
use App\Http\Controllers\Master\Procurement\PaymentTermController;
use App\Http\Controllers\Master\Procurement\ProcurementChannelController;
use App\Http\Controllers\Master\Procurement\TaxController;
use App\Http\Controllers\Master\Procurement\UnitOfMeasureController;
use App\Http\Controllers\Master\Procurement\VendorController;
use App\Http\Controllers\Master\Procurement\VendorTypeController;

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
])->group(function (): void {
    Route::get('/', function () {
        return inertia('Dashboard/Index');
    })->name('dashboard');

    Route::get('/settings', [
        SettingsController::class,
        'edit',
    ])->name('settings.edit');

    Route::put('/settings', [
        SettingsController::class,
        'update',
    ])->name('settings.update');


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
    Route::prefix('master/asset-management')->name('master.asset-management.')->group(function () {
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
        Route::resource('procurement-channels', ProcurementChannelController::class)
            ->except(['show']);
    });
    Route::prefix('operational/procurement')->name('operational.procurement.')->group(function () {
        Route::resource('purchase-request', PurchaseRequestController::class)
            ->except(['show']);
        // Route::resource('units-of-measure', UnitOfMeasureController::class)
        //     ->except(['show']);
        // Route::resource('payment-terms', PaymentTermController::class)
        //     ->except(['show']);
        // Route::resource('taxes', TaxController::class)
        //     ->except(['show']);
        // Route::resource('vendor-types', VendorTypeController::class)
        //     ->except(['show']);
        // Route::resource('procurement-channels', ProcurementChannelController::class)
        //     ->except(['show']);
    });
});
