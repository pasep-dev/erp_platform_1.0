<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = app(SettingService::class);

        $this->setIfMissing(
            $settings,
            'company.name',
            'ERP Platform',
            'company',
            'Company or organization name.',
            isPublic: true,
        );

        $this->setIfMissing(
            $settings,
            'company.email',
            'admin@example.com',
            'company',
            'Primary company email address.',
            isPublic: true,
        );

        $this->setIfMissing(
            $settings,
            'company.phone',
            null,
            'company',
            'Primary company phone number.',
            isPublic: true,
        );

        $this->setIfMissing(
            $settings,
            'company.address',
            null,
            'company',
            'Company address.',
            isPublic: true,
        );

        $this->setIfMissing(
            $settings,
            'system.name',
            'ERP Platform',
            'system',
            'Application name shown across the interface.',
            isPublic: true,
        );

        $this->setIfMissing(
            $settings,
            'system.logo_path',
            null,
            'system',
            'Public storage path for the application logo.',
            isPublic: true,
        );

        $this->setIfMissing(
            $settings,
            'system.timezone',
            'Asia/Jakarta',
            'system',
            'Application timezone.',
            isPublic: true,
        );

        $this->setIfMissing(
            $settings,
            'system.date_format',
            'd/m/Y',
            'system',
            'Default application date format.',
            isPublic: true,
        );

        $this->setIfMissing(
            $settings,
            'system.datetime_format',
            'd/m/Y H:i',
            'system',
            'Default application date and time format.',
            isPublic: true,
        );

        $this->setIfMissing(
            $settings,
            'backup.enabled',
            true,
            'backup',
            'Whether the scheduled database backup is enabled.',
            type: 'boolean',
        );

        $this->setIfMissing(
            $settings,
            'backup.time',
            '10:00',
            'backup',
            'Daily scheduled database backup time.',
        );

        $this->setIfMissing(
            $settings,
            'backup.directory',
            'backups/scheduled',
            'backup',
            'Backup directory relative to the mounted backup volume.',
        );
    }

    private function setIfMissing(
        SettingService $settings,
        string $key,
        mixed $value,
        string $group,
        string $description,
        string $type = 'string',
        bool $isPublic = false,
    ): void {
        if (Setting::query()->where('key', $key)->exists()) {
            return;
        }

        $settings->set(
            key: $key,
            value: $value,
            type: $type,
            group: $group,
            description: $description,
            isPublic: $isPublic,
        );
    }
}
