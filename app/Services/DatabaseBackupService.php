<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseBackupService
{
    private const MAX_BACKUPS = 7;

    public function __construct(
        private SettingService $settings,
    ) {}

    public function driver(): string
    {
        return DB::connection()->getDriverName();
    }

    public function isAvailable(): bool
    {
        if ($this->driver() !== 'pgsql') {
            return false;
        }

        return $this->findDumpBinary() !== null;
    }

    public function isStorageAvailable(string $directory): bool
    {
        $root = realpath((string) config('filesystems.disks.database_backups.root'));

        if ($root === false || ! is_writable($root)) {
            return false;
        }

        try {
            $segments = $this->directorySegments($directory);
        } catch (RuntimeException) {
            return false;
        }

        $candidate = $root;

        foreach ($segments as $segment) {
            $candidate .= DIRECTORY_SEPARATOR.$segment;

            if (! file_exists($candidate)) {
                break;
            }

            if (is_link($candidate)) {
                return false;
            }

            $resolved = realpath($candidate);

            if ($resolved === false || ! $this->isWithinRoot($resolved, $root)) {
                return false;
            }

            $candidate = $resolved;
        }

        if (is_dir($candidate)) {
            return is_writable($candidate);
        }

        return ! file_exists($candidate) && is_writable(dirname($candidate));
    }

    public function storageLocation(string $directory): string
    {
        $root = rtrim((string) config('filesystems.disks.database_backups.root'), DIRECTORY_SEPARATOR);

        return $root.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $this->directorySegments($directory));
    }

    public function mountRoot(): string
    {
        return (string) config('filesystems.disks.database_backups.root');
    }

    /**
     * @return array{filename: string, size_bytes: int, created_at: string}
     */
    public function createBackup(string $directory): array
    {
        $backupDirectory = $this->resolveBackupDirectory($directory, true);
        $lockPath = $backupDirectory.DIRECTORY_SEPARATOR.'.backup.lock';
        $lock = fopen($lockPath, 'c');

        if ($lock === false || ! chmod($lockPath, 0600)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            throw new RuntimeException('Unable to secure the database backup lock.');
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Unable to lock the database backup directory.');
            }

            $temporaryPath = $this->generateDump();
            $filename = 'database-'.now()->format('Ymd_His').'-'.Str::ulid().'.dump';
            $storedPath = $backupDirectory.DIRECTORY_SEPARATOR.$filename;
            $stream = fopen($temporaryPath, 'rb');

            try {
                if ($stream === false) {
                    throw new RuntimeException('Unable to read the generated database backup.');
                }

                if (file_put_contents($storedPath, $stream) === false) {
                    throw new RuntimeException('Unable to store the database backup.');
                }

                if (! chmod($storedPath, 0600)) {
                    unlink($storedPath);

                    throw new RuntimeException('Unable to secure the database backup file.');
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }

                $this->removeTemporaryFile($temporaryPath);
            }

            $this->removeExpiredBackups($backupDirectory);
            $this->recordSuccessfulBackup();

            return [
                'filename' => $filename,
                'size_bytes' => (int) filesize($storedPath),
                'created_at' => now()->toIso8601String(),
            ];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return list<array{filename: string, size_bytes: int, created_at: string}>
     */
    public function listBackups(string $directory): array
    {
        if (realpath(Storage::disk('database_backups')->path('')) === false) {
            return [];
        }

        $backupDirectory = $this->resolveBackupDirectory($directory);

        if (! is_dir($backupDirectory)) {
            return [];
        }

        $backups = [];

        foreach (glob($backupDirectory.DIRECTORY_SEPARATOR.'database-*.dump') ?: [] as $path) {
            $filename = basename($path);

            if (! $this->isBackupFilename($filename) || ! is_file($path) || is_link($path)) {
                continue;
            }

            $modifiedAt = filemtime($path);

            if ($modifiedAt === false) {
                throw new RuntimeException('Unable to read the database backup timestamp.');
            }

            $backups[] = [
                'filename' => $filename,
                'size_bytes' => (int) filesize($path),
                'created_at' => date(DATE_ATOM, $modifiedAt),
            ];
        }

        usort(
            $backups,
            fn (array $first, array $second): int => strcmp($second['filename'], $first['filename']),
        );

        return $backups;
    }

    public function deleteBackup(string $directory, string $filename): bool
    {
        if (! $this->isBackupFilename($filename)) {
            throw new RuntimeException('The requested database backup filename is invalid.');
        }

        $backupDirectory = $this->resolveBackupDirectory($directory);
        $path = $backupDirectory.DIRECTORY_SEPARATOR.$filename;

        if (! is_file($path) || is_link($path)) {
            return false;
        }

        if (! unlink($path)) {
            throw new RuntimeException('Unable to delete the database backup file.');
        }

        return true;
    }

    private function generateDump(): string
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Database backups are currently supported only for PostgreSQL.');
        }

        $binary = $this->findDumpBinary();

        if ($binary === null) {
            throw new RuntimeException('The PostgreSQL pg_dump utility is not available.');
        }

        $backupPath = tempnam(sys_get_temp_dir(), 'erp-database-backup-');

        if (! is_string($backupPath)) {
            throw new RuntimeException('Unable to create a temporary database backup file.');
        }

        if (! chmod($backupPath, 0600)) {
            $this->removeTemporaryFile($backupPath);

            throw new RuntimeException('Unable to secure the temporary database backup file.');
        }

        $passwordFile = null;

        try {
            $environment = $this->processEnvironment(
                $connection->getConfig(),
                $passwordFile,
            );

            $process = new Process(
                [
                    $binary,
                    '--format=custom',
                    '--no-password',
                    '--file='.$backupPath,
                ],
                env: $environment,
                timeout: 1800,
            );
            $process->mustRun();

            $size = is_file($backupPath)
                ? filesize($backupPath)
                : false;

            if ($size === false || $size === 0) {
                throw new RuntimeException('PostgreSQL produced an empty database backup.');
            }

            return $backupPath;
        } catch (Throwable $exception) {
            $this->removeTemporaryFile($backupPath);

            throw $exception;
        } finally {
            if ($passwordFile !== null) {
                $this->removeTemporaryFile($passwordFile);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return array<string, string>
     */
    private function processEnvironment(
        array $connection,
        ?string &$passwordFile,
    ): array {
        $environment = [
            'PGCONNECT_TIMEOUT' => '10',
        ];

        foreach ([
            'host' => 'PGHOST',
            'port' => 'PGPORT',
            'database' => 'PGDATABASE',
            'username' => 'PGUSER',
            'sslmode' => 'PGSSLMODE',
        ] as $configKey => $environmentKey) {
            $value = $connection[$configKey] ?? null;

            if (is_string($value) && $value !== '') {
                $environment[$environmentKey] = $value;
            }
        }

        $password = $connection['password'] ?? null;

        if (is_string($password) && $password !== '') {
            $temporaryPasswordFile = tempnam(sys_get_temp_dir(), 'erp-pgpass-');

            if (! is_string($temporaryPasswordFile)) {
                throw new RuntimeException('Unable to create a temporary PostgreSQL password file.');
            }

            $passwordFile = $temporaryPasswordFile;
            $entry = implode(':', array_map(
                $this->escapePasswordFileValue(...),
                [
                    (string) ($connection['host'] ?? '*') ?: '*',
                    (string) ($connection['port'] ?? '*') ?: '*',
                    (string) ($connection['database'] ?? '*') ?: '*',
                    (string) ($connection['username'] ?? '*') ?: '*',
                    $password,
                ],
            )).PHP_EOL;

            if (file_put_contents($passwordFile, $entry, LOCK_EX) === false
                || ! chmod($passwordFile, 0600)) {
                $this->removeTemporaryFile($passwordFile);

                throw new RuntimeException('Unable to secure the temporary PostgreSQL password file.');
            }

            $environment['PGPASSFILE'] = $passwordFile;
        }

        return $environment;
    }

    private function escapePasswordFileValue(string $value): string
    {
        return str_replace(
            ['\\', ':'],
            ['\\\\', '\\:'],
            $value,
        );
    }

    private function findDumpBinary(): ?string
    {
        $binary = (string) config(
            'database.connections.pgsql.dump_binary',
            'pg_dump',
        );

        if (is_file($binary) && is_executable($binary)) {
            return $binary;
        }

        return (new ExecutableFinder)->find($binary);
    }

    private function removeExpiredBackups(string $directory): void
    {
        $backupPaths = glob($directory.DIRECTORY_SEPARATOR.'database-*.dump') ?: [];
        $backupPaths = array_values(array_filter(
            $backupPaths,
            fn (string $path): bool => $this->isBackupFilename(basename($path))
                && is_file($path)
                && ! is_link($path),
        ));

        usort(
            $backupPaths,
            fn (string $first, string $second): int => strcmp(basename($second), basename($first)),
        );

        foreach (array_slice($backupPaths, self::MAX_BACKUPS) as $expiredPath) {
            if (! unlink($expiredPath)) {
                throw new RuntimeException('Unable to remove an expired database backup.');
            }
        }
    }

    private function resolveBackupDirectory(string $directory, bool $create = false): string
    {
        $disk = Storage::disk('database_backups');
        $root = realpath($disk->path(''));

        if ($root === false || ! is_dir($root)) {
            throw new RuntimeException('The mounted database backup directory is unavailable.');
        }

        $segments = $this->directorySegments($directory);
        $path = $root;

        foreach ($segments as $segment) {
            $path .= DIRECTORY_SEPARATOR.$segment;

            if (is_link($path)) {
                throw new RuntimeException('Symbolic links are not allowed in the database backup directory.');
            }

            if (! is_dir($path)) {
                if (! $create) {
                    return $path;
                }

                if (! mkdir($path, 0700) && ! is_dir($path)) {
                    throw new RuntimeException('Unable to create the database backup directory.');
                }
            }

            $resolvedPath = realpath($path);

            if ($resolvedPath === false || ! $this->isWithinRoot($resolvedPath, $root)) {
                throw new RuntimeException('The database backup directory must remain inside the mounted volume.');
            }

            $path = $resolvedPath;
        }

        if (! is_writable($path)) {
            throw new RuntimeException('The database backup directory is not writable.');
        }

        return $path;
    }

    /**
     * @return list<string>
     */
    private function directorySegments(string $directory): array
    {
        $segments = explode('/', $directory);

        if ($directory === ''
            || preg_match('/\A[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*\z/D', $directory) !== 1
            || in_array('', $segments, true)) {
            throw new RuntimeException('The database backup directory must be a relative path inside the mounted volume.');
        }

        return $segments;
    }

    private function isWithinRoot(string $path, string $root): bool
    {
        return str_starts_with(
            $path.DIRECTORY_SEPARATOR,
            rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR,
        );
    }

    private function isBackupFilename(string $filename): bool
    {
        return preg_match(
            '/\Adatabase-\d{8}_\d{6}-[0-9A-HJKMNP-TV-Z]{26}\.dump\z/D',
            $filename,
        ) === 1;
    }

    private function recordSuccessfulBackup(): void
    {
        $this->settings->set(
            key: 'backup.last_success_at',
            value: now()->toIso8601String(),
            group: 'backup',
            description: 'Timestamp of the most recently completed database backup.',
        );
    }

    private function removeTemporaryFile(string $path): void
    {
        if (is_file($path) && ! unlink($path)) {
            Log::warning('Unable to remove a temporary database backup file.', [
                'path' => $path,
            ]);
        }
    }
}
