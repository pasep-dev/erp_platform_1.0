<?php

namespace Tests\Feature\Administrator;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ActivityLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_page_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('administrator.activity-logs.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_filter_activity_logs_and_view_record_details(): void
    {
        $user = User::factory()->create();
        $permission = Permission::query()->create([
            'name' => 'activity-logs.view',
            'guard_name' => 'web',
        ]);
        $user->givePermissionTo($permission);

        ActivityLog::query()->create([
            'user_id' => $user->id,
            'action' => 'CREATE',
            'module' => 'Transaction Inventory',
            'subject_type' => 'App\\Models\\Transaction\\Inventory\\InventoryRequest',
            'subject_id' => 'INV-001',
            'description' => 'Created inventory request.',
            'old_values' => null,
            'new_values' => [
                'remarks' => 'Urgent supplies',
                'api_token' => 'legacy-secret-value',
            ],
            'ip_address' => '192.0.2.10',
            'user_agent' => 'ActivityLogControllerTest',
            'request_id' => 'request-001',
            'created_at' => now(),
        ]);

        ActivityLog::query()->create([
            'user_id' => $user->id,
            'action' => 'PAGE_VIEW',
            'module' => 'Transaction Inventory',
            'description' => 'Viewed inventory request.',
            'created_at' => now(),
        ]);

        ActivityLog::query()->create([
            'user_id' => $user->id,
            'action' => 'UPDATE',
            'module' => 'Settings',
            'description' => 'Updated application settings.',
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('administrator.activity-logs.index', [
                'search' => 'inventory request',
                'module' => 'Transaction Inventory',
                'action' => 'CREATE',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Administrator/ActivityLogs/Index')
                ->where('logs.total', 1)
                ->where('logs.data.0.module', 'Transaction Inventory')
                ->where('logs.data.0.action', 'CREATE')
                ->where('logs.data.0.subject_id', 'INV-001')
                ->where('logs.data.0.user.name', $user->name)
                ->where('logs.data.0.new_values.remarks', 'Urgent supplies')
                ->where('logs.data.0.new_values.api_token', '[REDACTED]')
                ->where('filters.module', 'Transaction Inventory')
                ->where('filters.action', 'CREATE'));

        $this->actingAs($user)
            ->get(route('administrator.activity-logs.index', [
                'search' => 'inventory request',
                'module' => 'Transaction Inventory',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Administrator/ActivityLogs/Index')
                ->where('logs.total', 1)
                ->where('logs.data.0.action', 'CREATE'));
    }
}
