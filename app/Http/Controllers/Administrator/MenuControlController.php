<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Models\MenuVisibilityProfile;
use App\Models\User;
use App\Services\MenuVisibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class MenuControlController extends Controller
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
                ->when($search, function (Builder $query, string $search): void {
                    $query->where('name', 'like', "%{$search}%");
                })
                ->orderBy('name')
                ->orderBy('id')
                ->paginate($perPage)
                ->withQueryString();

            $configuredIds = MenuVisibilityProfile::query()
                ->whereIn('role_id', $targets->getCollection()->modelKeys())
                ->pluck('role_id')
                ->all();

            $targets->through(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'type' => 'role',
                'identifier' => null,
                'is_configured' => in_array($role->id, $configuredIds),
                'is_editable' => $role->name !== 'Administrator',
            ]);
        } else {
            $targets = User::query()
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
                ->withQueryString();

            $configuredIds = MenuVisibilityProfile::query()
                ->whereIn('user_id', $targets->getCollection()->modelKeys())
                ->pluck('user_id')
                ->all();

            $targets->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'type' => 'user',
                'identifier' => $user->email,
                'roles' => $user->roles->pluck('name')->all(),
                'is_configured' => in_array($user->id, $configuredIds),
                'is_editable' => ! $user->isAdministrator(),
            ]);
        }

        return Inertia::render('Administrator/MenuControl/Index', [
            'targets' => $targets,
            'filters' => [
                'type' => $type,
                'search' => $search ?? '',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function edit(
        Request $request,
        string $type,
        string $target,
        MenuVisibilityService $menuVisibilityService,
    ): Response {
        $this->authorizeAdministrator($request);
        abort_unless(in_array($type, ['role', 'user'], true), 404);

        if ($type === 'role') {
            $subject = Role::query()
                ->where('guard_name', 'web')
                ->findOrFail($target);
            $profile = MenuVisibilityProfile::query()
                ->where('role_id', $subject->id)
                ->first();
            $selectedMenuKeys = $profile?->menu_keys;
            $hasCustomSettings = $profile !== null;
            $isEditable = $subject->name !== 'Administrator';
            $inheritsRoleSettings = false;
        } else {
            $subject = User::query()->findOrFail($target);
            $profile = MenuVisibilityProfile::query()
                ->where('user_id', $subject->id)
                ->first();
            $selectedMenuKeys = $profile?->menu_keys
                ?? $menuVisibilityService->visibleMenuKeysFor($subject);
            $hasCustomSettings = $profile !== null;
            $isEditable = ! $subject->isAdministrator();
            $inheritsRoleSettings = $profile === null && $selectedMenuKeys !== null;
        }

        return Inertia::render('Administrator/MenuControl/Edit', [
            'target' => [
                'id' => $subject->getKey(),
                'type' => $type,
                'type_label' => $type === 'role' ? 'Role' : 'User',
                'name' => $subject->name,
                'identifier' => $subject instanceof User ? $subject->email : null,
            ],
            'canEdit' => $isEditable,
            'hasCustomSettings' => $hasCustomSettings,
            'selectedMenuKeys' => $selectedMenuKeys,
            'inheritsRoleSettings' => $inheritsRoleSettings,
        ]);
    }

    public function update(Request $request, string $type, string $target): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        abort_unless(in_array($type, ['role', 'user'], true), 404);

        $subject = $type === 'role'
            ? Role::query()->where('guard_name', 'web')->findOrFail($target)
            : User::query()->findOrFail($target);

        abort_if(
            $subject instanceof Role
                ? $subject->name === 'Administrator'
                : $subject->isAdministrator(),
            403,
        );

        if ($request->boolean('reset_to_default')) {
            MenuVisibilityProfile::query()
                ->where($subject instanceof Role ? 'role_id' : 'user_id', $subject->id)
                ->delete();

            return to_route('administrator.menu-control.index', [
                'type' => $type,
            ])->with('success', 'Menu visibility reset to inherited/default settings.');
        }

        $validated = $request->validate([
            'menu_keys' => ['present', 'array'],
            'menu_keys.*' => [
                'required',
                'string',
                'distinct',
                'max:100',
                'regex:/^[a-z0-9-]+$/',
            ],
        ]);

        DB::transaction(function () use ($subject, $validated): void {
            $profile = MenuVisibilityProfile::query()->firstOrNew(
                $subject instanceof Role
                    ? ['role_id' => $subject->id]
                    : ['user_id' => $subject->id],
            );
            $profile->role_id = $subject instanceof Role ? $subject->id : null;
            $profile->user_id = $subject instanceof User ? $subject->id : null;
            $profile->menu_keys = array_values($validated['menu_keys']);
            $profile->save();
        });

        return to_route('administrator.menu-control.index', [
            'type' => $type,
        ])->with('success', 'Menu visibility updated successfully.');
    }

    private function authorizeAdministrator(Request $request): void
    {
        abort_unless($request->user()?->hasGlobalDepartmentAccess(), 403);
    }
}
