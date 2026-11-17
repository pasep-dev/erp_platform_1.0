<?php

namespace Tests\Feature\Administrator;

use App\Models\MenuVisibilityProfile;
use App\Models\User;
use App\Services\MenuVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MenuControlControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_save_menu_visibility_for_a_role(): void
    {
        $administrator = $this->administrator();
        $role = Role::query()->create([
            'name' => 'Purchasing',
            'guard_name' => 'web',
        ]);

        $response = $this->actingAs($administrator)
            ->put(route('administrator.menu-control.update', [
                'type' => 'role',
                'target' => $role->id,
            ]), [
                'menu_keys' => ['dashboard', 'procurement', 'purchase-request'],
            ]);

        $response->assertRedirect(route('administrator.menu-control.index', [
            'type' => 'role',
        ]));
        $this->assertSame(
            ['dashboard', 'procurement', 'purchase-request'],
            MenuVisibilityProfile::query()
                ->where('role_id', $role->id)
                ->firstOrFail()
                ->menu_keys,
        );
    }

    public function test_administrator_can_view_the_role_and_user_menu_control_lists(): void
    {
        $administrator = $this->administrator();
        $role = Role::query()->create([
            'name' => 'Purchasing',
            'guard_name' => 'web',
        ]);
        $user = User::factory()->create([
            'name' => 'Target Menu User',
        ]);
        $user->assignRole($role);

        $this->actingAs($administrator)
            ->get(route('administrator.menu-control.index', ['type' => 'role']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Administrator/MenuControl/Index')
                ->where('filters.type', 'role')
                ->has('targets.data'));

        $this->actingAs($administrator)
            ->get(route('administrator.menu-control.index', ['type' => 'user']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Administrator/MenuControl/Index')
                ->where('filters.type', 'user')
                ->where('targets.data.0.id', $user->id));
    }

    public function test_user_menu_visibility_overrides_the_configured_role_menu(): void
    {
        $role = Role::query()->create([
            'name' => 'Purchasing',
            'guard_name' => 'web',
        ]);
        $user = User::factory()->create([
            'name' => 'Menu Owner User',
        ]);
        $user->assignRole($role);
        MenuVisibilityProfile::query()->create([
            'role_id' => $role->id,
            'menu_keys' => ['dashboard', 'procurement', 'purchase-request'],
        ]);
        MenuVisibilityProfile::query()->create([
            'user_id' => $user->id,
            'menu_keys' => ['dashboard', 'master-data', 'companies'],
        ]);

        $menuKeys = app(MenuVisibilityService::class)
            ->visibleMenuKeysFor($user);

        $this->assertSame(['dashboard', 'master-data', 'companies'], $menuKeys);
    }

    public function test_user_without_personal_settings_inherits_configured_role_menus(): void
    {
        $role = Role::query()->create([
            'name' => 'Purchasing',
            'guard_name' => 'web',
        ]);
        $user = User::factory()->create([
            'name' => 'Administrator Test User',
        ]);
        $user->assignRole($role);
        MenuVisibilityProfile::query()->create([
            'role_id' => $role->id,
            'menu_keys' => ['dashboard', 'procurement'],
        ]);

        $menuKeys = app(MenuVisibilityService::class)
            ->visibleMenuKeysFor($user);

        $this->assertSame(['dashboard', 'procurement'], $menuKeys);
    }

    public function test_administrator_can_reset_a_role_to_default_menu_visibility(): void
    {
        $administrator = $this->administrator();
        $role = Role::query()->create([
            'name' => 'Purchasing',
            'guard_name' => 'web',
        ]);
        MenuVisibilityProfile::query()->create([
            'role_id' => $role->id,
            'menu_keys' => ['dashboard'],
        ]);

        $this->actingAs($administrator)
            ->put(route('administrator.menu-control.update', [
                'type' => 'role',
                'target' => $role->id,
            ]), [
                'reset_to_default' => true,
            ])
            ->assertRedirect(route('administrator.menu-control.index', [
                'type' => 'role',
            ]));

        $this->assertDatabaseMissing('menu_visibility_profiles', [
            'role_id' => $role->id,
        ]);
    }

    public function test_non_administrator_cannot_change_menu_visibility(): void
    {
        $permission = Permission::query()->create([
            'name' => 'roles.update',
            'guard_name' => 'web',
        ]);
        $role = Role::query()->create([
            'name' => 'Role Manager',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);
        $user = User::factory()->create([
            'name' => 'Administrator Test User',
        ]);
        $user->assignRole($role);
        $targetRole = Role::query()->create([
            'name' => 'Purchasing',
            'guard_name' => 'web',
        ]);

        $this->actingAs($user)
            ->put(route('administrator.menu-control.update', [
                'type' => 'role',
                'target' => $targetRole->id,
            ]), [
                'menu_keys' => ['dashboard'],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('menu_visibility_profiles', [
            'role_id' => $targetRole->id,
        ]);
    }

    private function administrator(): User
    {
        $role = Role::query()->create([
            'name' => 'Administrator',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo(Permission::query()->firstOrCreate([
            'name' => 'roles.update',
            'guard_name' => 'web',
        ]));
        $user = User::factory()->create([
            'name' => 'Administrator Test User',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
