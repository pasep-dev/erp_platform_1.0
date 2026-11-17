<?php

namespace Tests\Feature\Services;

use App\Services\DatabaseBackupService;
use App\Services\SettingService;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DatabaseBackupServiceTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        config(['database.default' => 'sqlite']);
        DB::purge('pgsql');

        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_creates_a_private_manual_backup_in_the_mounted_directory(): void
    {
        Storage::fake('database_backups');
        $binary = $this->fakePgDumpBinary();
        $settings = $this->mock(SettingService::class);
        $settings->shouldReceive('set')
            ->once()
            ->withArgs(fn (...$arguments): bool => $arguments[0] === 'backup.last_success_at');
        $this->configurePostgresConnection($binary);
        $this->travelTo(new DateTimeImmutable('2026-01-10 12:34:56'));

        $backup = app(DatabaseBackupService::class)->createBackup('backups/manual');

        Storage::disk('database_backups')->assertExists(
            'backups/manual/'.$backup['filename'],
        );
        $this->assertSame(
            'pg_dump test archive',
            Storage::disk('database_backups')->get('backups/manual/'.$backup['filename']),
        );
        $this->assertSame(0600, fileperms(
            Storage::disk('database_backups')->path('backups/manual/'.$backup['filename']),
        ) & 0777);
    }

    public function test_creates_backup_and_retains_only_the_newest_seven_files(): void
    {
        Storage::fake('database_backups');
        $binary = $this->fakePgDumpBinary();
        $settings = $this->mock(SettingService::class);
        $settings->shouldReceive('set')
            ->once()
            ->withArgs(fn (...$arguments): bool => $arguments[0] === 'backup.last_success_at');
        $this->configurePostgresConnection($binary);

        foreach (range(1, 8) as $number) {
            Storage::disk('database_backups')->put(
                'backups/scheduled/database-2026010'.$number.'_000000-01ARZ3NDEKTSV4RRFFQ69G5FAV.dump',
                'old backup',
            );
        }

        $this->travelTo(new DateTimeImmutable('2026-01-10 12:34:56'));
        $backup = app(DatabaseBackupService::class)->createBackup('backups/scheduled');
        $backupFiles = app(DatabaseBackupService::class)->listBackups('backups/scheduled');

        $this->assertCount(7, $backupFiles);
        Storage::disk('database_backups')->assertExists(
            'backups/scheduled/'.$backup['filename'],
        );
        Storage::disk('database_backups')->assertMissing(
            'backups/scheduled/database-20260101_000000-01ARZ3NDEKTSV4RRFFQ69G5FAV.dump',
        );
        $this->assertSame(
            'pg_dump test archive',
            Storage::disk('database_backups')->get(
                'backups/scheduled/'.$backup['filename'],
            ),
        );
    }

    public function test_lists_and_deletes_only_generated_backup_files(): void
    {
        Storage::fake('database_backups');
        $directory = 'backups/scheduled';
        $filename = 'database-20260110_123456-01ARZ3NDEKTSV4RRFFQ69G5FAV.dump';
        Storage::disk('database_backups')->put($directory.'/'.$filename, 'backup');
        Storage::disk('database_backups')->put($directory.'/notes.txt', 'not a backup');
        $this->mock(SettingService::class);

        $service = app(DatabaseBackupService::class);
        $listedBackups = $service->listBackups($directory);
        $deleted = $service->deleteBackup($directory, $filename);

        $this->assertSame($filename, $listedBackups[0]['filename']);
        $this->assertTrue($deleted);
        Storage::disk('database_backups')->assertMissing($directory.'/'.$filename);
        Storage::disk('database_backups')->assertExists($directory.'/notes.txt');
    }

    public function test_rejects_backup_directory_paths_outside_the_mounted_volume(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('relative path inside the mounted volume');

        app(DatabaseBackupService::class)->storageLocation('../outside');
    }

    public function test_reports_mounted_backup_storage_availability(): void
    {
        $directory = tempnam(sys_get_temp_dir(), 'erp-backup-volume-');
        $this->assertIsString($directory);
        unlink($directory);
        $this->assertTrue(mkdir($directory));
        config(['filesystems.disks.database_backups.root' => $directory]);

        try {
            $this->assertTrue(app(DatabaseBackupService::class)->isStorageAvailable('backups/scheduled'));
        } finally {
            rmdir($directory);
        }
    }

    public function test_reports_mounted_backup_storage_unavailable_when_directory_is_missing(): void
    {
        $directory = tempnam(sys_get_temp_dir(), 'erp-backup-volume-');
        $this->assertIsString($directory);
        unlink($directory);

        config([
            'filesystems.disks.database_backups.root' => $directory,
        ]);

        $this->assertFalse(app(DatabaseBackupService::class)->isStorageAvailable('backups/scheduled'));
    }

    public function test_reports_backup_unavailable_when_pg_dump_is_missing(): void
    {
        $this->configurePostgresConnection('/nonexistent/pg_dump');

        $this->assertFalse(app(DatabaseBackupService::class)->isAvailable());
    }

    public function test_rejects_non_postgresql_database_backups(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('currently supported only for PostgreSQL');

        app(DatabaseBackupService::class)->createBackup('backups/scheduled');
    }

    private function fakePgDumpBinary(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fake-pg-dump-');
        $this->assertIsString($path);

        file_put_contents($path, <<<'PHP'
#!/usr/bin/env php
<?php
$destination = null;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--file=')) {
        $destination = substr($argument, strlen('--file='));
    }
}
$passwordFile = getenv('PGPASSFILE');
if ($destination === null || getenv('PGPASSWORD') !== false) {
    exit(2);
}
if ($passwordFile === false || (fileperms($passwordFile) & 0777) !== 0600) {
    exit(3);
}
if (file_get_contents($passwordFile) !== 'localhost:5432:erp_test:test_user:test\:secret' . PHP_EOL) {
    exit(4);
}
if (! in_array('--format=custom', $argv, true) || ! in_array('--no-password', $argv, true)) {
    exit(5);
}
if (file_put_contents($destination, 'pg_dump test archive') === false) {
    exit(6);
}
PHP);
        chmod($path, 0700);
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function configurePostgresConnection(string $binary): void
    {
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql' => [
                'driver' => 'pgsql',
                'host' => 'localhost',
                'port' => '5432',
                'database' => 'erp_test',
                'username' => 'test_user',
                'password' => 'test:secret',
                'sslmode' => 'prefer',
                'dump_binary' => $binary,
            ],
        ]);
    }
}
