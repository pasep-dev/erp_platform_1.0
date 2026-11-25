<?php

namespace Tests\Feature\Settings;

use App\Enums\NumberingModuleCode;
use App\Models\NumberingModule;
use App\Models\Setting;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.edit'))
            ->assertForbidden();
    }

    public function test_settings_update_requires_update_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('settings.update'), $this->validSettingsPayload())
            ->assertForbidden();
    }

    public function test_authorized_user_sees_application_and_backup_settings(): void
    {
        $user = $this->userWithPermissions('system.settings.view');

        $this->actingAs($user)
            ->get(route('settings.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings/Index')
                ->where('settings.system.name', 'ERP Platform')
                ->where('backup.enabled', true)
                ->where('backup.time', '10:00')
                ->where('backup.retention', 7)
                ->where('backup.directory', 'backups/scheduled')
                ->has('backup.backups')
                ->has('modules', 19)
                ->where('modules.0.code', 'ASSET_ASSIGNMENT'));
    }

    public function test_settings_only_lists_modules_integrated_with_numbering(): void
    {
        $user = $this->userWithPermissions('system.settings.view');
        NumberingModule::query()->create([
            'code' => 'SALES_ORDER',
            'name' => 'Sales Order',
            'category' => 'Sales',
            'default_pattern' => null,
            'sequence_length' => null,
            'reset_period' => null,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('settings.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings/Index')
                ->has('modules', 19)
                ->where('modules', fn (array $modules): bool => collect($modules)
                    ->pluck('code')
                    ->diff(NumberingModuleCode::values())
                    ->isEmpty()));
    }

    public function test_user_without_update_permission_cannot_change_module_availability(): void
    {
        $user = $this->userWithPermissions('system.settings.view');

        $this->actingAs($user)
            ->patch(route('settings.modules.update', [
                'numberingModule' => 'VENDOR',
            ]), [
                'is_active' => false,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('numbering_modules', [
            'code' => 'VENDOR',
            'is_active' => true,
        ]);
    }

    public function test_authorized_user_can_disable_a_module_numbering_integration(): void
    {
        $user = $this->userWithPermissions('system.settings.update');

        $this->actingAs($user)
            ->patch(route('settings.modules.update', [
                'numberingModule' => 'VENDOR',
            ]), [
                'is_active' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Module availability was updated successfully.');

        $this->assertDatabaseHas('numbering_modules', [
            'code' => 'VENDOR',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'module' => 'Module Settings',
            'action' => 'UPDATE',
            'subject_id' => 'VENDOR',
        ]);
    }

    public function test_unintegrated_module_cannot_be_enabled_from_settings(): void
    {
        $user = $this->userWithPermissions('system.settings.update');
        NumberingModule::query()->create([
            'code' => 'SALES_ORDER',
            'name' => 'Sales Order',
            'category' => 'Sales',
            'default_pattern' => null,
            'sequence_length' => null,
            'reset_period' => null,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->patch(route('settings.modules.update', [
                'numberingModule' => 'SALES_ORDER',
            ]), [
                'is_active' => false,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('numbering_modules', [
            'code' => 'SALES_ORDER',
            'is_active' => true,
        ]);
    }

    public function test_authorized_user_can_update_system_identity_and_backup_schedule(): void
    {
        Storage::fake('public');
        $user = $this->userWithPermissions(
            'system.settings.view',
            'system.settings.update',
        );

        $response = $this->actingAs($user)
            ->put(route('settings.update'), $this->validSettingsPayload([
                'system' => [
                    'name' => 'Operations ERP',
                    'logo' => UploadedFile::fake()->image('system-logo.png'),
                    'remove_logo' => false,
                ],
                'backup' => [
                    'enabled' => true,
                    'time' => '10:00',
                    'directory' => 'backups/operations',
                ],
            ]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $logoPath = Setting::query()
            ->where('key', 'system.logo_path')
            ->value('value');

        $this->assertDatabaseHas('settings', [
            'key' => 'system.name',
            'value' => 'Operations ERP',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'backup.time',
            'value' => '10:00',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'backup.directory',
            'value' => 'backups/operations',
        ]);
        $this->assertIsString($logoPath);
        Storage::disk('public')->assertExists($logoPath);

        $this->actingAs($user)
            ->get(route('settings.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('settings.system.name', 'Operations ERP')
                ->where('app.name', 'Operations ERP')
                ->where('app.logo_url', Storage::disk('public')->url($logoPath)));
    }

    public function test_backup_settings_reject_invalid_time_and_directory_traversal(): void
    {
        $user = $this->userWithPermissions('system.settings.update');
        $payload = $this->validSettingsPayload([
            'system' => [
                'name' => 'Unchanged ERP',
            ],
            'backup' => [
                'time' => '25:61',
                'directory' => '../outside',
            ],
        ]);

        $this->actingAs($user)
            ->from(route('settings.edit'))
            ->put(route('settings.update'), $payload)
            ->assertSessionHasErrors([
                'backup.time',
                'backup.directory',
            ]);

        $this->assertDatabaseMissing('settings', [
            'key' => 'system.name',
            'value' => 'Unchanged ERP',
        ]);
    }

    public function test_svg_logo_is_rejected_without_changing_system_name(): void
    {
        Storage::fake('public');
        $user = $this->userWithPermissions('system.settings.update');
        $payload = $this->validSettingsPayload([
            'system' => [
                'name' => 'Unchanged ERP',
                'logo' => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml'),
                'remove_logo' => false,
            ],
        ]);

        $this->actingAs($user)
            ->from(route('settings.edit'))
            ->put(route('settings.update'), $payload)
            ->assertSessionHasErrors('system.logo');

        $this->assertDatabaseMissing('settings', [
            'key' => 'system.name',
            'value' => 'Unchanged ERP',
        ]);
        $this->assertSame([], Storage::disk('public')->files('branding'));
    }

    public function test_manual_backup_requires_backup_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('settings.backup'))
            ->assertForbidden();
    }

    public function test_authorized_user_stores_manual_backup_and_receives_success_feedback(): void
    {
        $user = $this->userWithPermissions('system.settings.backup');

        $this->mock(DatabaseBackupService::class)
            ->shouldReceive('createBackup')
            ->once()
            ->with('backups/scheduled')
            ->andReturn([
                'filename' => 'database-20260110_123456-01ARZ3NDEKTSV4RRFFQ69G5FAV.dump',
                'size_bytes' => 20,
                'created_at' => '2026-01-10T12:34:56+07:00',
            ]);

        $this->actingAs($user)
            ->post(route('settings.backup'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Database backup was created successfully.');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'module' => 'Database Backup',
            'action' => 'CREATE',
        ]);
    }

    public function test_authorized_user_can_delete_a_saved_backup(): void
    {
        $user = $this->userWithPermissions('system.settings.backup');
        $filename = 'database-20260110_123456-01ARZ3NDEKTSV4RRFFQ69G5FAV.dump';

        $this->mock(DatabaseBackupService::class)
            ->shouldReceive('deleteBackup')
            ->once()
            ->with('backups/scheduled', $filename)
            ->andReturnTrue();

        $this->actingAs($user)
            ->delete(route('settings.backup.destroy', $filename))
            ->assertRedirect()
            ->assertSessionHas('success', 'Database backup was deleted successfully.');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'module' => 'Database Backup',
            'action' => 'DELETE',
        ]);
    }

    public function test_backup_deletion_requires_backup_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route(
                'settings.backup.destroy',
                'database-20260110_123456-01ARZ3NDEKTSV4RRFFQ69G5FAV.dump',
            ))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validSettingsPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'system' => [
                'name' => 'ERP Platform',
                'logo' => null,
                'remove_logo' => false,
            ],
            'company' => [
                'name' => 'Example Company',
                'email' => 'admin@example.test',
                'phone' => '',
                'address' => '',
            ],
            'regional' => [
                'timezone' => 'Asia/Jakarta',
                'date_format' => 'd/m/Y',
                'datetime_format' => 'd/m/Y H:i',
            ],
            'backup' => [
                'enabled' => true,
                'time' => '10:00',
                'directory' => 'backups/scheduled',
            ],
        ], $overrides);
    }

    private function userWithPermissions(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }
}
