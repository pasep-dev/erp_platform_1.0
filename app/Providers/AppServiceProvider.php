<?php

namespace App\Providers;

use App\Models\Transaction\Procurement\GoodsReceipt;
use App\Observers\Transaction\Procurement\GoodsReceiptInventoryObserver;
use App\Services\ActivityLogModelEventRecorder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        GoodsReceipt::observe(GoodsReceiptInventoryObserver::class);

        Event::listen('eloquent.created: *', function (string $event, array $models): void {
            $this->app->make(ActivityLogModelEventRecorder::class)->created($models[0]);
        });
        Event::listen('eloquent.updating: *', function (string $event, array $models): void {
            $this->app->make(ActivityLogModelEventRecorder::class)->updating($models[0]);
        });
        Event::listen('eloquent.updated: *', function (string $event, array $models): void {
            $this->app->make(ActivityLogModelEventRecorder::class)->updated($models[0]);
        });
        Event::listen('eloquent.deleted: *', function (string $event, array $models): void {
            $this->app->make(ActivityLogModelEventRecorder::class)->deleted($models[0]);
        });
    }
}
