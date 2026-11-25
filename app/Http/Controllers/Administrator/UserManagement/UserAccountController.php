<?php

namespace App\Http\Controllers\Administrator\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Department;
use App\Models\User;
use App\Services\BranchAccessService;
use App\Services\DepartmentAccessService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserAccountController extends Controller
{
    public function index(Request $request, DepartmentAccessService $departmentAccess, BranchAccessService $branchAccess): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', Rule::in(['0', '1', 'ALL'])],
            'per_page' => ['nullable', Rule::in([5, 15, 25, 50, 100])],
        ]);
        $activeFilter = $filters['is_active'] ?? '1';

        $users = User::query()
            ->when(! $branchAccess->hasGlobalAccess($request->user()), function ($query) use ($branchAccess, $request): void {
                $branchIds = $branchAccess->branchIds($request->user());
                $query->where(function ($branchQuery) use ($branchIds): void {
                    $branchQuery->whereIn('branch_id', $branchIds)
                        ->orWhereHas('additionalBranches', fn ($branches) => $branches->whereIn('mst_org_branches.id', $branchIds));
                });
            })
            ->select([
                'id',
                'name',
                'email',
                'is_active',
                'company_id',
                'branch_id',
                'department_id',
                'position_id',
                'last_login_at',
            ])
            ->with([
                'company:id,name',
                'branch:id,name',
                'department:id,name',
                'additionalBranches:id,name',
                'additionalDepartments:id,name',
                'position:id,name',
                'roles:id,name',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(
                $activeFilter !== 'ALL',
                fn ($query) => $query->where('is_active', $activeFilter === '1'),
            )
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return Inertia::render('Administrator/UserManagement/Users/Index', [
            'users' => $users,
            'departments' => $departmentAccess->scopeDepartments(Department::query(), $request->user())->where('is_active', true)->orderBy('name')->get(['id', 'name', 'company_id']),
            'branches' => $branchAccess->scopeBranches(Branch::query(), $request->user())->where('is_active', true)->orderBy('name')->get(['id', 'name', 'company_id']),
            'roles' => Role::query()
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'is_active' => $activeFilter,
                'per_page' => (int) ($filters['per_page'] ?? 15),
            ],
            'can' => [
                'create' => $request->user()->can('users.create'),
                'update' => $request->user()->can('users.update'),
                'delete' => $request->user()->can('users.delete'),
                'assignAdministrator' => $request->user()->hasGlobalDepartmentAccess(),
            ],
            'currentUserId' => $request->user()->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['required', 'boolean'],
            'department_id' => [Rule::requiredIf(! $request->user()->hasGlobalDepartmentAccess()), 'nullable', 'string', 'size:26', Rule::exists('mst_org_departments', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'branch_id' => [Rule::requiredIf(! $request->user()->hasGlobalBranchAccess()), 'nullable', 'string', 'size:26', Rule::exists('mst_org_branches', 'id')->where('is_active', true)->whereNull('deleted_at'), Rule::in(app(BranchAccessService::class)->branchIds($request->user()))],
            'additional_branch_ids' => ['sometimes', 'array'],
            'additional_branch_ids.*' => ['required', 'string', 'size:26', 'distinct', Rule::exists('mst_org_branches', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'additional_department_ids' => ['sometimes', 'array'],
            'additional_department_ids.*' => ['required', 'string', 'size:26', 'distinct', Rule::exists('mst_org_departments', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('roles', 'id')->where('guard_name', 'web'),
            ],
        ]);

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('id', $validated['role_ids'])
            ->get();

        if ($this->cannotAssignGlobalRole($request, $roles)) {
            return back()->withErrors([
                'role_ids' => 'Only an Administrator or Owner can assign global access roles.',
            ]);
        }

        if ($this->cannotAssignDepartments($request, $validated)) {
            return back()->withErrors(['additional_department_ids' => 'Only an Administrator or Owner can grant access to additional departments.']);
        }

        if ($this->cannotAssignBranches($request, $validated)) {
            return back()->withErrors(['additional_branch_ids' => 'Only an Administrator or Owner can grant access to additional branches.']);
        }

        if ($this->missingOrganizationForRegularUser($roles, $validated)) {
            return back()->withErrors([
                'branch_id' => 'A branch must be assigned to users without global access.',
                'department_id' => 'A department must be assigned to users without global access.',
            ]);
        }

        $branch = isset($validated['branch_id']) ? Branch::query()->findOrFail($validated['branch_id']) : null;
        $department = isset($validated['department_id']) ? Department::query()->findOrFail($validated['department_id']) : null;
        if ($branch !== null && $department !== null && $branch->company_id !== $department->company_id) {
            return back()->withErrors(['department_id' => 'Department must belong to the selected branch company.']);
        }

        DB::transaction(function () use ($validated, $roles, $branch): void {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'is_active' => $validated['is_active'],
                'company_id' => $branch?->company_id,
                'branch_id' => $branch?->id,
                'department_id' => $validated['department_id'] ?? null,
            ]);

            $user->syncRoles($roles);
            $user->additionalDepartments()->sync($validated['additional_department_ids'] ?? []);
            $user->additionalBranches()->sync($validated['additional_branch_ids'] ?? []);
        });

        return to_route('administrator.users.index')
            ->with('success', 'User account created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless(app(BranchAccessService::class)->hasGlobalAccess($request->user())
            || app(BranchAccessService::class)->canAccess($request->user(), $user->branch_id), 404);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['required', 'boolean'],
            'department_id' => [Rule::requiredIf(! $request->user()->hasGlobalDepartmentAccess()), 'nullable', 'string', 'size:26', Rule::exists('mst_org_departments', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'branch_id' => [Rule::requiredIf(! $request->user()->hasGlobalBranchAccess()), 'nullable', 'string', 'size:26', Rule::exists('mst_org_branches', 'id')->where('is_active', true)->whereNull('deleted_at'), Rule::in(app(BranchAccessService::class)->branchIds($request->user()))],
            'additional_branch_ids' => ['sometimes', 'array'],
            'additional_branch_ids.*' => ['required', 'string', 'size:26', 'distinct', Rule::exists('mst_org_branches', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'additional_department_ids' => ['sometimes', 'array'],
            'additional_department_ids.*' => ['required', 'string', 'size:26', 'distinct', Rule::exists('mst_org_departments', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('roles', 'id')->where('guard_name', 'web'),
            ],
        ]);

        $actor = $request->user();
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('id', $validated['role_ids'])
            ->get();

        if ($user->hasRole(['Administrator', 'Owner']) && ! $actor->hasGlobalDepartmentAccess()) {
            abort(403);
        }

        if ($this->cannotAssignGlobalRole($request, $roles)) {
            return back()->withErrors([
                'role_ids' => 'Only an Administrator or Owner can assign global access roles.',
            ]);
        }

        if ($this->cannotAssignDepartments($request, $validated)) {
            return back()->withErrors(['additional_department_ids' => 'Only an Administrator or Owner can grant access to additional departments.']);
        }

        if ($this->cannotAssignBranches($request, $validated)) {
            return back()->withErrors(['additional_branch_ids' => 'Only an Administrator or Owner can grant access to additional branches.']);
        }

        if ($this->missingOrganizationForRegularUser($roles, $validated)) {
            return back()->withErrors([
                'branch_id' => 'A branch must be assigned to users without global access.',
                'department_id' => 'A department must be assigned to users without global access.',
            ]);
        }

        $branch = isset($validated['branch_id']) ? Branch::query()->findOrFail($validated['branch_id']) : null;
        $department = isset($validated['department_id']) ? Department::query()->findOrFail($validated['department_id']) : null;
        if ($branch !== null && $department !== null && $branch->company_id !== $department->company_id) {
            return back()->withErrors(['department_id' => 'Department must belong to the selected branch company.']);
        }

        if ($user->is($actor)) {
            if (! $validated['is_active']) {
                return back()->withErrors([
                    'is_active' => 'You cannot deactivate your own account.',
                ]);
            }

            if ($user->hasRole(['Administrator', 'Owner']) && ! $roles->contains(fn (Role $role): bool => in_array($role->name, ['Administrator', 'Owner'], true))) {
                return back()->withErrors([
                    'role_ids' => 'You cannot remove your own Administrator role.',
                ]);
            }
        }

        DB::transaction(function () use ($validated, $roles, $user, $branch): void {
            $attributes = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'is_active' => $validated['is_active'],
                'company_id' => $branch?->company_id,
                'branch_id' => $branch?->id,
                'department_id' => $validated['department_id'] ?? null,
            ];

            if ($validated['password'] !== null) {
                $attributes['password'] = $validated['password'];
            }

            $user->update($attributes);
            $user->syncRoles($roles);
            $user->additionalDepartments()->sync($validated['additional_department_ids'] ?? []);
            $user->additionalBranches()->sync($validated['additional_branch_ids'] ?? []);
        });

        return to_route('administrator.users.index')
            ->with('success', 'User account updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless(app(BranchAccessService::class)->hasGlobalAccess($request->user())
            || app(BranchAccessService::class)->canAccess($request->user(), $user->branch_id), 404);
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->hasRole(['Administrator', 'Owner']) && ! $request->user()->hasGlobalDepartmentAccess()) {
            abort(403);
        }

        try {
            $deleted = DB::transaction(function () use ($user): bool {
                if ($user->hasRole(['Administrator', 'Owner'])) {
                    $administratorRole = Role::query()
                        ->where('guard_name', 'web')
                        ->whereIn('name', ['Administrator', 'Owner'])
                        ->lockForUpdate()
                        ->get();

                    if ($administratorRole->sum(fn (Role $role): int => $role->users()->count()) <= 1) {
                        return false;
                    }
                }

                $user->delete();

                return true;
            });
        } catch (QueryException $exception) {
            if (! in_array($exception->errorInfo[0] ?? $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }

            return back()->with(
                'error',
                'This account is referenced by saved records. Deactivate it instead of deleting it.',
            );
        }

        if (! $deleted) {
            return back()->with(
                'error',
                'The last Administrator account cannot be deleted.',
            );
        }

        return to_route('administrator.users.index')
            ->with('success', 'User account deleted successfully.');
    }

    private function cannotAssignGlobalRole(Request $request, iterable $roles): bool
    {
        return ! $request->user()->hasGlobalDepartmentAccess()
            && collect($roles)->contains(fn (Role $role): bool => in_array($role->name, ['Administrator', 'Owner'], true));
    }

    /** @param array<string, mixed> $validated */
    private function cannotAssignDepartments(Request $request, array $validated): bool
    {
        return ! $request->user()->hasGlobalDepartmentAccess()
            && (count($validated['additional_department_ids'] ?? []) > 0
                || (($validated['department_id'] ?? null) !== null
                    && $validated['department_id'] !== $request->user()->department_id));
    }

    /** @param array<string, mixed> $validated */
    private function cannotAssignBranches(Request $request, array $validated): bool
    {
        return ! $request->user()->hasGlobalBranchAccess()
            && (count($validated['additional_branch_ids'] ?? []) > 0
                || (($validated['branch_id'] ?? null) !== null
                    && ! in_array($validated['branch_id'], app(BranchAccessService::class)->branchIds($request->user()), true)));
    }

    /** @param array<string, mixed> $validated */
    private function missingOrganizationForRegularUser(iterable $roles, array $validated): bool
    {
        $hasGlobalRole = collect($roles)->contains(fn (Role $role): bool => in_array($role->name, ['Administrator', 'Owner'], true));

        return ! $hasGlobalRole
            && (empty($validated['branch_id']) || empty($validated['department_id']));
    }
}
