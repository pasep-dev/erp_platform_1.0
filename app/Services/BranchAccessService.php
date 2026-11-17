<?php

namespace App\Services;

use App\Models\Master\Organization\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BranchAccessService
{
    public function hasGlobalAccess(User $user): bool
    {
        return $user->hasGlobalBranchAccess();
    }

    /** @return list<string> */
    public function branchIds(User $user): array
    {
        if ($this->hasGlobalAccess($user)) {
            return Branch::query()->pluck('id')->all();
        }

        return collect([$user->branch_id])
            ->merge($user->additionalBranches()->pluck('mst_org_branches.id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function canAccess(User $user, ?string $branchId): bool
    {
        return $branchId !== null
            && ($this->hasGlobalAccess($user) || in_array($branchId, $this->branchIds($user), true));
    }

    public function authorize(User $user, ?string $branchId): void
    {
        abort_unless($this->canAccess($user, $branchId), 403);
    }

    /** @param Builder<Branch> $query
     * @return Builder<Branch>
     */
    public function scopeBranches(Builder $query, User $user): Builder
    {
        if (! $this->hasGlobalAccess($user)) {
            $query->whereIn('mst_org_branches.id', $this->branchIds($user));
        }

        return $query;
    }
}
