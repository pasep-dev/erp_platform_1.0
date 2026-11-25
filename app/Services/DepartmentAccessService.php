<?php

namespace App\Services;

use App\Models\Master\Organization\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DepartmentAccessService
{
    public function hasGlobalAccess(User $user): bool
    {
        return $user->hasRole(['Administrator', 'Owner']);
    }

    /** @return list<string> */
    public function departmentIds(User $user): array
    {
        if ($this->hasGlobalAccess($user)) {
            return Department::query()->pluck('id')->all();
        }

        $user->loadMissing('additionalDepartments:id');

        return collect([$user->department_id])
            ->merge($user->additionalDepartments->modelKeys())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function canAccess(User $user, ?string $departmentId): bool
    {
        return $departmentId !== null
            && ($this->hasGlobalAccess($user) || in_array($departmentId, $this->departmentIds($user), true));
    }

    public function authorize(User $user, ?string $departmentId): void
    {
        abort_unless($this->canAccess($user, $departmentId), 403);
    }

    /** @param Builder<Department> $query
     * @return Builder<Department>
     */
    public function scopeDepartments(Builder $query, User $user): Builder
    {
        if (! $this->hasGlobalAccess($user)) {
            $query->whereIn('mst_org_departments.id', $this->departmentIds($user));
        }

        return $query;
    }

    /** @template TModel of \Illuminate\Database\Eloquent\Model
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeByDepartment(Builder $query, User $user, string $column = 'department_id'): Builder
    {
        if (! $this->hasGlobalAccess($user)) {
            $query->whereIn($column, $this->departmentIds($user));
        }

        return $query;
    }
}
