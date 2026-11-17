<?php

namespace Tests\Feature\Administrator;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_account_list_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('administrator.users.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_user_accounts(): void
    {
        $administrator = $this->userWithPermissions(['users.view']);

        $this->actingAs($administrator)
            ->get(route('administrator.users.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Administrator/UserManagement/Users/Index')
                ->has('users.data')
                ->has('roles'));
    }

    public function test_authorized_user_can_create_an_account_with_a_role(): void
    {
        $operator = $this->userWithPermissions(['users.create']);
        $role = Role::query()->create([
            'name' => 'Staff',
            'guard_name' => 'web',
        ]);

        $response = $this->actingAs($operator)
            ->post(route('administrator.users.store'), [
                'name' => 'New Staff Member',
                'email' => 'staff@example.test',
                'password' => 'secure-password',
                'password_confirmation' => 'secure-password',
                'is_active' => true,
                'role_ids' => [$role->id],
            ]);

        $response->assertRedirect(route('administrator.users.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'name' => 'New Staff Member',
            'email' => 'staff@example.test',
            'is_active' => true,
        ]);

        $createdUser = User::query()->where('email', 'staff@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('secure-password', $createdUser->password));
        $this->assertTrue($createdUser->hasRole($role));
    }

    public function test_non_administrator_cannot_assign_the_administrator_role(): void
    {
        $operator = $this->userWithPermissions(['users.create']);
        $administratorRole = Role::query()->create([
            'name' => 'Administrator',
            'guard_name' => 'web',
        ]);

        $this->actingAs($operator)
            ->from(route('administrator.users.index'))
            ->post(route('administrator.users.store'), [
                'name' => 'Escalated Account',
                'email' => 'escalated@example.test',
                'password' => 'secure-password',
                'password_confirmation' => 'secure-password',
                'is_active' => true,
                'role_ids' => [$administratorRole->id],
            ])
            ->assertSessionHasErrors('role_ids');

        $this->assertDatabaseMissing('users', [
            'email' => 'escalated@example.test',
        ]);
    }

    public function test_non_administrator_cannot_update_an_administrator_account(): void
    {
        $operator = $this->userWithPermissions(['users.update']);
        $administratorRole = Role::query()->create([
            'name' => 'Administrator',
            'guard_name' => 'web',
        ]);
        $administrator = User::factory()->create();
        $administrator->assignRole($administratorRole);

        $this->actingAs($operator)
            ->put(route('administrator.users.update', $administrator), [
                'name' => 'Changed Administrator',
                'email' => $administrator->email,
                'password' => '',
                'password_confirmation' => '',
                'is_active' => true,
                'role_ids' => [$administratorRole->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $administrator->id,
            'name' => $administrator->name,
        ]);
    }

    public function test_user_cannot_deactivate_their_own_account(): void
    {
        $user = $this->userWithPermissions(['users.update']);
        $role = $user->roles()->firstOrFail();

        $this->actingAs($user)
            ->from(route('administrator.users.index'))
            ->put(route('administrator.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => '',
                'password_confirmation' => '',
                'is_active' => false,
                'role_ids' => [$role->id],
            ])
            ->assertSessionHasErrors('is_active');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => true,
        ]);
    }

    public function test_administrator_cannot_remove_their_own_administrator_role(): void
    {
        $administratorRole = Role::query()->create([
            'name' => 'Administrator',
            'guard_name' => 'web',
        ]);
        $administratorRole->givePermissionTo(Permission::query()->create([
            'name' => 'users.update',
            'guard_name' => 'web',
        ]));
        $userRole = Role::query()->create([
            'name' => 'User',
            'guard_name' => 'web',
        ]);
        $administrator = User::factory()->create();
        $administrator->assignRole($administratorRole);

        $this->actingAs($administrator)
            ->from(route('administrator.users.index'))
            ->put(route('administrator.users.update', $administrator), [
                'name' => $administrator->name,
                'email' => $administrator->email,
                'password' => '',
                'password_confirmation' => '',
                'is_active' => true,
                'role_ids' => [$userRole->id],
            ])
            ->assertSessionHasErrors('role_ids');

        $this->assertTrue($administrator->fresh()->hasRole('Administrator'));
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::query()->create([
            'name' => 'Test Role '.fake()->unique()->word(),
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
