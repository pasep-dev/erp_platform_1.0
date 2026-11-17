<?php

namespace App\Http\Controllers\Administrator\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccessControlController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAdministrator($request);

        $filters = $request->validate([
            'type' => ['nullable', Rule::in(['role', 'user'])],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', Rule::in([5, 15, 25, 50, 100])],
        ]);
        $type = $filters['type'] ?? 'role';
        $perPage = (int) ($filters['per_page'] ?? 15);
        $search = $filters['search'] ?? null;

        if ($type === 'role') {
            $targets = Role::query()
                ->where('guard_name', 'web')
                ->withCount('permissions')
                ->when($search, function (Builder $query, string $search): void {
                    $query->where('name', 'like', "%{$search}%");
                })
                ->orderBy('name')
                ->orderBy('id')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'type' => 'role',
                    'identifier' => null,
                    'permissions_count' => $role->permissions_count,
                    'is_system' => in_array($role->name, ['Administrator', 'Owner'], true),
                    'is_editable' => ! in_array($role->name, ['Administrator', 'Owner'], true),
                ]);
        } else {
            $targets = User::query()
                ->withCount([
                    'permissions as direct_permissions_count' => fn (Builder $query) => $query
                        ->where('guard_name', 'web'),
                ])
                ->with('roles:id,name')
                ->when($search, function (Builder $query, string $search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->orderBy('id')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'type' => 'user',
                    'identifier' => $user->email,
                    'roles' => $user->roles->pluck('name')->all(),
                    'permissions_count' => $user->direct_permissions_count,
                    'is_system' => $user->hasGlobalDepartmentAccess(),
                    'is_editable' => ! $user->hasGlobalDepartmentAccess(),
                ]);
        }

        return Inertia::render('Administrator/UserManagement/AccessControl/Index', [
            'targets' => $targets,
            'filters' => [
                'type' => $type,
                'search' => $search ?? '',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function edit(Request $request, string $type, string $target): Response
    {
        $this->authorizeAdministrator($request);
        abort_unless(in_array($type, ['role', 'user'], true), 404);

        $subject = $this->findSubject($type, $target);
        $isAdministrator = $type === 'role'
            ? in_array($subject->name, ['Administrator', 'Owner'], true)
            : $subject->hasRole(['Administrator', 'Owner']);

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Administrator/UserManagement/AccessControl/Edit', [
            'target' => [
                'id' => $subject->getKey(),
                'type' => $type,
                'type_label' => $type === 'role' ? 'Role' : 'User',
                'name' => $subject->name,
                'identifier' => $type === 'user' ? $subject->email : null,
            ],
            'canEdit' => ! $isAdministrator,
            'permissionGroups' => $permissions
                ->groupBy(fn (Permission $permission): string => Str::before($permission->name, '.'))
                ->map(fn ($groupPermissions, string $group): array => [
                    'name' => $group,
                    'label' => Str::headline($group),
                    'permissions' => $groupPermissions
                        ->map(fn (Permission $permission): array => [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'label' => Str::headline(str_replace('.', ' ', $permission->name)),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'assignedPermissionIds' => $subject->permissions()
                ->where('permissions.guard_name', 'web')
                ->pluck('permissions.id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
        ]);
    }

    public function update(Request $request, string $type, string $target): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        abort_unless(in_array($type, ['role', 'user'], true), 404);

        $subject = $this->findSubject($type, $target);
        abort_if(
            $type === 'role'
                ? in_array($subject->name, ['Administrator', 'Owner'], true)
                : $subject->hasRole(['Administrator', 'Owner']),
            403,
        );

        $validated = $request->validate([
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('permissions', 'id')->where('guard_name', 'web'),
            ],
        ]);

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('id', $validated['permission_ids'])
            ->get();

        $subject->syncPermissions($permissions);

        return to_route('administrator.access-control.index', [
            'type' => $type,
        ])->with('success', 'Access control updated successfully.');
    }

    private function authorizeAdministrator(Request $request): void
    {
        abort_unless(
            $request->user()?->hasRole(['Administrator', 'Owner']),
            403,
        );
    }

    private function findSubject(string $type, string $target): Role|User
    {
        if ($type === 'role') {
            return Role::query()
                ->where('guard_name', 'web')
                ->findOrFail($target);
        }

        return User::query()->findOrFail($target);
    }
}
