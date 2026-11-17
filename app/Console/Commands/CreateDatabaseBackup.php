<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use App\Services\SettingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('system:backup')]
#[Description('Create a scheduled PostgreSQL database backup.')]
class CreateDatabaseBackup extends Command
{
    public function handle(
        DatabaseBackupService $backups,
        SettingService $settings,
    ): int {
        $backup = $backups->createBackup(
            (string) $settings->get('backup.directory', 'backups/scheduled'),
        );

        $this->info("Database backup created: {$backup['filename']}");

        return self::SUCCESS;
    }
}
