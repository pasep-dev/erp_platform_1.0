<?php

namespace Tests\Feature\Administrator;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessControlControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_view_role_access_control_index(): void
    {
        $administrator = $this->administrator();
        $role = Role::query()->create([
            'name' => 'Inventory Clerk',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($this->permission('inventory.view'));

        $this->actingAs($administrator)
            ->get(route('administrator.access-control.index', ['type' => 'role']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Administrator/UserManagement/AccessControl/Index')
                ->where('filters.type', 'role')
                ->where('targets.data.1.id', $role->id)
                ->where('targets.data.1.permissions_count', 1));
    }

    public function test_administrator_can_view_user_direct_permission_index(): void
    {
        $administrator = $this->administrator();
        $targetUser = User::factory()->create([
            'name' => 'User with direct access',
            'email' => 'direct-access@example.test',
        ]);
        $targetUser->givePermissionTo($this->permission('inventory.view'));

        $this->actingAs($administrator)
            ->get(route('administrator.access-control.index', ['type' => 'user']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Administrator/UserManagement/AccessControl/Index')
                ->where('filters.type', 'user')
                ->where('targets.data.0.id', $targetUser->id)
                ->where('targets.data.0.permissions_count', 1));
    }

    public function test_administrator_can_edit_a_role_permission_matrix(): void
    {
        $administrator = $this->administrator();
        $role = Role::query()->create([
            'name' => 'Inventory Clerk',
            'guard_name' => 'web',
        ]);
        $permission = $this->permission('inventory.view');
        $role->givePermissionTo($permission);

        $this->actingAs($administrator)
            ->get(route('administrator.access-control.edit', [
                'type' => 'role',
                'target' => $role->id,
            ]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Administrator/UserManagement/AccessControl/Edit')
                ->where('target.type', 'role')
                ->where('target.id', $role->id)
                ->where('canEdit', true)
                ->where('assignedPermissionIds', [$permission->id]));
    }

    public function test_administrator_can_assign_permissions_directly_to_a_user(): void
    {
        $administrator = $this->administrator();
        $targetUser = User::factory()->create();
        $permission = $this->permission('inventory.view');

        $response = $this->actingAs($administrator)
            ->put(route('administrator.access-control.update', [
                'type' => 'user',
                'target' => $targetUser->id,
            ]), [
                'permission_ids' => [$permission->id],
            ]);

        $response->assertRedirect(route('administrator.access-control.index', [
            'type' => 'user',
        ]));
        $response->assertSessionHasNoErrors();
        $this->assertTrue($targetUser->fresh()->hasDirectPermission($permission));
    }

    public function test_non_administrator_with_role_update_permission_is_forbidden(): void
    {
        $permission = $this->permission('roles.update');
        $role = Role::query()->create([
            'name' => 'Delegated Role Manager',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->get(route('administrator.access-control.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('administrator.access-control.update', [
                'type' => 'role',
                'target' => $role->id,
            ]), [
                'permission_ids' => [],
            ])
            ->assertForbidden();

        $this->assertTrue($role->fresh()->hasPermissionTo($permission));
    }

    public function test_administrator_role_access_cannot_be_changed(): void
    {
        $administrator = $this->administrator();
        $administratorRole = $administrator->roles()->firstOrFail();
        $originalPermission = $this->permission('users.view');
        $otherPermission = $this->permission('users.delete');
        $administratorRole->givePermissionTo($originalPermission);

        $this->actingAs($administrator)
            ->put(route('administrator.access-control.update', [
                'type' => 'role',
                'target' => $administratorRole->id,
            ]), [
                'permission_ids' => [$otherPermission->id],
            ])
            ->assertForbidden();

        $this->assertTrue($administratorRole->fresh()->hasPermissionTo($originalPermission));
        $this->assertFalse($administratorRole->fresh()->hasPermissionTo($otherPermission));
    }

    private function administrator(): User
    {
        $role = Role::query()->create([
            'name' => 'Administrator',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($this->permission('roles.update'));

        $user = User::factory()->create([
            'name' => 'zzzz Test Administrator',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function permission(string $name): Permission
    {
        return Permission::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]);
    }
}
