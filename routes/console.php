<?php

use App\Services\SettingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('system:backup')
    ->everyMinute()
    ->when(function (): bool {
        $settings = app(SettingService::class);

        if (! $settings->get('backup.enabled', true)) {
            return false;
        }

        $timezone = (string) $settings->get('system.timezone', 'Asia/Jakarta');
        $backupTime = (string) $settings->get('backup.time', '10:00');

        return now($timezone)->format('H:i') === $backupTime;
    })
    ->withoutOverlapping(1440);
