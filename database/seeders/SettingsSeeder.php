<?php

namespace Database\Seeders;

use App\Services\SettingService;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = app(SettingService::class);

        $settings->set(
            key: 'company.name',
            value: 'ERP Platform',
            group: 'company',
            description: 'Company or organization name.',
            isPublic: true,
        );

        $settings->set(
            key: 'company.email',
            value: 'admin@example.com',
            group: 'company',
            description: 'Primary company email address.',
            isPublic: true,
        );

        $settings->set(
            key: 'company.phone',
            value: null,
            group: 'company',
            description: 'Primary company phone number.',
            isPublic: true,
        );

        $settings->set(
            key: 'company.address',
            value: null,
            group: 'company',
            description: 'Company address.',
            isPublic: true,
        );

        $settings->set(
            key: 'system.timezone',
            value: 'Asia/Jakarta',
            group: 'system',
            description: 'Application timezone.',
            isPublic: true,
        );

        $settings->set(
            key: 'system.date_format',
            value: 'd/m/Y',
            group: 'system',
            description: 'Default application date format.',
            isPublic: true,
        );

        $settings->set(
            key: 'system.datetime_format',
            value: 'd/m/Y H:i',
            group: 'system',
            description: 'Default application date and time format.',
            isPublic: true,
        );
    }
}
