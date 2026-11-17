<?php

namespace Tests\Feature\Administrator;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_list_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('administrator.roles.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_create_a_custom_role(): void
    {
        $administrator = $this->administratorWithPermissions(['roles.create']);

        $response = $this->actingAs($administrator)
            ->post(route('administrator.roles.store'), [
                'name' => 'Inventory Clerk',
            ]);

        $response->assertRedirect(route('administrator.roles.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('roles', [
            'name' => 'Inventory Clerk',
            'guard_name' => 'web',
        ]);
    }

    public function test_duplicate_role_name_is_rejected(): void
    {
        $administrator = $this->administratorWithPermissions(['roles.create']);
        Role::query()->create([
            'name' => 'Inventory Clerk',
            'guard_name' => 'web',
        ]);

        $this->actingAs($administrator)
            ->from(route('administrator.roles.index'))
            ->post(route('administrator.roles.store'), [
                'name' => 'Inventory Clerk',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(
            1,
            Role::query()->where('name', 'Inventory Clerk')->count(),
        );
    }

    public function test_system_role_cannot_be_renamed(): void
    {
        $administrator = $this->administratorWithPermissions(['roles.update']);
        $systemRole = Role::query()->create([
            'name' => 'Administrator',
            'guard_name' => 'web',
        ]);

        $this->actingAs($administrator)
            ->put(route('administrator.roles.update', $systemRole), [
                'name' => 'Renamed Administrator',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('roles', [
            'id' => $systemRole->id,
            'name' => 'Administrator',
        ]);
    }

    public function test_role_assigned_to_a_user_cannot_be_deleted(): void
    {
        $administrator = $this->administratorWithPermissions(['roles.delete']);
        $role = Role::query()->create([
            'name' => 'Inventory Clerk',
            'guard_name' => 'web',
        ]);
        User::factory()->create()->assignRole($role);

        $this->actingAs($administrator)
            ->from(route('administrator.roles.index'))
            ->delete(route('administrator.roles.destroy', $role))
            ->assertSessionHas('error', 'This role is assigned to user accounts and cannot be deleted.');

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'Inventory Clerk',
        ]);
    }

    private function administratorWithPermissions(array $permissions): User
    {
        $role = Role::query()->create([
            'name' => 'Administrator',
            'guard_name' => 'web',
        ]);

        $role->syncPermissions(collect($permissions)->map(
            fn (string $permission): Permission => Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]),
        ));

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
