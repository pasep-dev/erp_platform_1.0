<?php

namespace App\Http\Controllers\Administrator\UserManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    private const SYSTEM_ROLES = ['Administrator', 'Owner', 'Manager', 'User'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', Rule::in([5, 15, 25, 50, 100])],
        ]);

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->withCount(['users', 'permissions'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString()
            ->through(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
                'is_system' => in_array($role->name, self::SYSTEM_ROLES, true),
            ]);

        return Inertia::render('Administrator/UserManagement/Roles/Index', [
            'roles' => $roles,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'per_page' => (int) ($filters['per_page'] ?? 15),
            ],
            'can' => [
                'create' => $request->user()->can('roles.create'),
                'update' => $request->user()->can('roles.update'),
                'delete' => $request->user()->can('roles.delete'),
                'manageAccess' => $request->user()->hasGlobalDepartmentAccess(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:125',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
        ]);

        Role::query()->create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        return to_route('administrator.roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless($role->guard_name === 'web', 404);
        abort_if(in_array($role->name, self::SYSTEM_ROLES, true), 403);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:125',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($role->id),
            ],
        ]);

        $role->update(['name' => $validated['name']]);

        return to_route('administrator.roles.index')
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_unless($role->guard_name === 'web', 404);
        abort_if(in_array($role->name, self::SYSTEM_ROLES, true), 403);

        if ($role->users()->exists()) {
            return back()->with(
                'error',
                'This role is assigned to user accounts and cannot be deleted.',
            );
        }

        $role->delete();

        return to_route('administrator.roles.index')
            ->with('success', 'Role deleted successfully.');
    }
}
