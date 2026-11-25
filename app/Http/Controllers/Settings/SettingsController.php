<?php

namespace App\Http\Controllers\Settings;

use App\Enums\NumberingModuleCode;
use App\Http\Controllers\Controller;
use App\Models\NumberingModule;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\DatabaseBackupService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class SettingsController extends Controller
{
    public function edit(
        SettingService $settings,
        DatabaseBackupService $backups,
    ): Response {
        $logoPath = $settings->get('system.logo_path');
        $backupDirectory = (string) $settings->get('backup.directory', 'backups/scheduled');

        return Inertia::render('Settings/Index', [
            'settings' => [
                'system' => [
                    'name' => $settings->get(
                        'system.name',
                        $settings->get('company.name', config('app.name')),
                    ),
                    'logo_url' => is_string($logoPath) && $logoPath !== ''
                        ? Storage::disk('public')->url($logoPath)
                        : null,
                ],

                'company' => [
                    'name' => $settings->get('company.name', ''),
                    'email' => $settings->get('company.email', ''),
                    'phone' => $settings->get('company.phone', ''),
                    'address' => $settings->get('company.address', ''),
                ],

                'regional' => [
                    'timezone' => $settings->get(
                        'system.timezone',
                        'Asia/Jakarta',
                    ),
                    'date_format' => $settings->get(
                        'system.date_format',
                        'd/m/Y',
                    ),
                    'datetime_format' => $settings->get(
                        'system.datetime_format',
                        'd/m/Y H:i',
                    ),
                ],
            ],
            'backup' => [
                'enabled' => $settings->get('backup.enabled', true),
                'time' => $settings->get('backup.time', '10:00'),
                'retention' => 7,
                'directory' => $backupDirectory,
                'last_success_at' => $settings->get('backup.last_success_at'),
                'available' => $backups->isAvailable(),
                'driver' => $backups->driver(),
                'storage_available' => $backups->isStorageAvailable($backupDirectory),
                'storage_location' => $backups->storageLocation($backupDirectory),
                'mount_root' => $backups->mountRoot(),
                'backups' => $backups->listBackups($backupDirectory),
            ],
            'modules' => NumberingModule::query()
                ->whereIn('code', NumberingModuleCode::values())
                ->orderBy('category')
                ->orderBy('name')
                ->get([
                    'code',
                    'name',
                    'category',
                    'is_active',
                ]),
        ]);
    }

    public function update(
        Request $request,
        SettingService $settings,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'system.name' => [
                'required',
                'string',
                'max:150',
            ],

            'system.logo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'system.remove_logo' => [
                'required',
                'boolean',
            ],

            'company.name' => [
                'required',
                'string',
                'max:150',
            ],

            'company.email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'company.phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'company.address' => [
                'nullable',
                'string',
                'max:500',
            ],

            'backup.enabled' => [
                'required',
                'boolean',
            ],

            'backup.time' => [
                'required',
                'date_format:H:i',
            ],

            'backup.directory' => [
                'required',
                'string',
                'max:255',
                'regex:/\A[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*\z/',
            ],

            'regional.timezone' => [
                'required',
                'timezone',
            ],

            'regional.date_format' => [
                'required',
                'string',
                'max:50',
            ],

            'regional.datetime_format' => [
                'required',
                'string',
                'max:50',
            ],
        ]);

        $oldLogoPath = $settings->get('system.logo_path');
        $newLogoPath = $oldLogoPath;
        $uploadedLogoPath = null;

        if ($request->hasFile('system.logo')) {
            $uploadedLogoPath = $request->file('system.logo')
                ->storePublicly('branding', 'public');

            if (! is_string($uploadedLogoPath)) {
                throw new RuntimeException('Unable to store the application logo.');
            }

            $newLogoPath = $uploadedLogoPath;
        } elseif ($validated['system']['remove_logo']) {
            $newLogoPath = null;
        }

        $oldValues = [
            'system.name' => $settings->get(
                'system.name',
                $settings->get('company.name', config('app.name')),
            ),
            'system.logo_path' => $oldLogoPath,
            'company.name' => $settings->get('company.name'),
            'company.email' => $settings->get('company.email'),
            'company.phone' => $settings->get('company.phone'),
            'company.address' => $settings->get('company.address'),
            'backup.enabled' => $settings->get('backup.enabled', true),
            'backup.time' => $settings->get('backup.time', '10:00'),
            'backup.directory' => $settings->get('backup.directory', 'backups/scheduled'),
            'system.timezone' => $settings->get('system.timezone'),
            'system.date_format' => $settings->get('system.date_format'),
            'system.datetime_format' => $settings->get('system.datetime_format'),
        ];

        $newValues = [
            'system.name' => $validated['system']['name'],
            'system.logo_path' => $newLogoPath,
            'company.name' => $validated['company']['name'],
            'company.email' => $validated['company']['email'],
            'company.phone' => $validated['company']['phone'],
            'company.address' => $validated['company']['address'],
            'backup.enabled' => (bool) $validated['backup']['enabled'],
            'backup.time' => $validated['backup']['time'],
            'backup.directory' => $validated['backup']['directory'],
            'system.timezone' => $validated['regional']['timezone'],
            'system.date_format' => $validated['regional']['date_format'],
            'system.datetime_format' => $validated['regional']['datetime_format'],
        ];

        try {
            DB::transaction(function () use (
                $settings,
                $validated,
                $newLogoPath,
            ): void {
                $settings->set(
                    key: 'system.name',
                    value: $validated['system']['name'],
                    group: 'system',
                    description: 'Application name shown across the interface.',
                    isPublic: true,
                );

                $settings->set(
                    key: 'system.logo_path',
                    value: $newLogoPath,
                    group: 'system',
                    description: 'Public storage path for the application logo.',
                    isPublic: true,
                );

                $settings->set(
                    key: 'company.name',
                    value: $validated['company']['name'],
                    group: 'company',
                    description: 'Company or organization name.',
                    isPublic: true,
                );

                $settings->set(
                    key: 'company.email',
                    value: $validated['company']['email'],
                    group: 'company',
                    description: 'Primary company email address.',
                    isPublic: true,
                );

                $settings->set(
                    key: 'company.phone',
                    value: $validated['company']['phone'],
                    group: 'company',
                    description: 'Primary company phone number.',
                    isPublic: true,
                );

                $settings->set(
                    key: 'company.address',
                    value: $validated['company']['address'],
                    group: 'company',
                    description: 'Company address.',
                    isPublic: true,
                );

                $settings->set(
                    key: 'backup.enabled',
                    value: (bool) $validated['backup']['enabled'],
                    type: 'boolean',
                    group: 'backup',
                    description: 'Whether the scheduled database backup is enabled.',
                );

                $settings->set(
                    key: 'backup.time',
                    value: $validated['backup']['time'],
                    group: 'backup',
                    description: 'Daily scheduled database backup time.',
                );

                $settings->set(
                    key: 'backup.directory',
                    value: $validated['backup']['directory'],
                    group: 'backup',
                    description: 'Backup directory relative to the mounted backup volume.',
                );

                $settings->set(
                    key: 'system.timezone',
                    value: $validated['regional']['timezone'],
                    group: 'system',
                    description: 'Application timezone.',
                    isPublic: true,
                );

                $settings->set(
                    key: 'system.date_format',
                    value: $validated['regional']['date_format'],
                    group: 'system',
                    description: 'Default application date format.',
                    isPublic: true,
                );

                $settings->set(
                    key: 'system.datetime_format',
                    value: $validated['regional']['datetime_format'],
                    group: 'system',
                    description: 'Default application date and time format.',
                    isPublic: true,
                );
            });
        } catch (Throwable $exception) {
            if ($uploadedLogoPath !== null
                && ! Storage::disk('public')->delete($uploadedLogoPath)) {
                Log::warning('Unable to remove a new application logo after settings failed to save.', [
                    'path' => $uploadedLogoPath,
                ]);
            }

            throw $exception;
        }

        if (is_string($oldLogoPath)
            && $oldLogoPath !== $newLogoPath
            && str_starts_with($oldLogoPath, 'branding/')
            && ! Storage::disk('public')->delete($oldLogoPath)) {
            Log::warning('Unable to remove the previous application logo.', [
                'path' => $oldLogoPath,
            ]);
        }

        if ($oldValues !== $newValues) {
            $activityLogger->log(
                action: 'UPDATE',
                module: 'Settings',
                description: 'Application settings updated.',
                subjectType: Setting::class,
                oldValues: $oldValues,
                newValues: $newValues,
            );
        }

        return back()->with(
            'success',
            'Settings were saved successfully.',
        );
    }

    public function backup(
        SettingService $settings,
        DatabaseBackupService $backups,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        try {
            $backup = $backups->createBackup(
                (string) $settings->get('backup.directory', 'backups/scheduled'),
            );
        } catch (Throwable $exception) {
            Log::error('Manual database backup failed.', [
                'exception' => $exception,
            ]);

            return back()->with(
                'error',
                'Database backup failed. Check the application logs for details.',
            );
        }

        $activityLogger->log(
            action: 'CREATE',
            module: 'Database Backup',
            description: 'A manual database backup was generated.',
            newValues: [
                'filename' => $backup['filename'],
                'size_bytes' => $backup['size_bytes'],
            ],
        );

        return back()->with(
            'success',
            'Database backup was created successfully.',
        );
    }

    public function destroyBackup(
        string $filename,
        SettingService $settings,
        DatabaseBackupService $backups,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        $directory = (string) $settings->get('backup.directory', 'backups/scheduled');

        try {
            $deleted = $backups->deleteBackup($directory, $filename);
        } catch (Throwable $exception) {
            Log::error('Database backup deletion failed.', [
                'filename' => $filename,
                'exception' => $exception,
            ]);

            return back()->with(
                'error',
                'The database backup could not be deleted. Check the application logs for details.',
            );
        }

        if (! $deleted) {
            abort(404);
        }

        $activityLogger->log(
            action: 'DELETE',
            module: 'Database Backup',
            description: 'A database backup was deleted.',
            newValues: ['filename' => $filename],
        );

        return back()->with(
            'success',
            'Database backup was deleted successfully.',
        );
    }
}
