<?php

namespace App\Services;

use App\Models\MenuVisibilityProfile;
use App\Models\User;

class MenuVisibilityService
{
    /**
     * Return null when the user should keep the default permission-filtered menu.
     *
     * @return list<string>|null
     */
    public function visibleMenuKeysFor(User $user): ?array
    {
        if ($user->isAdministrator()) {
            return null;
        }

        $userProfile = MenuVisibilityProfile::query()
            ->where('user_id', $user->id)
            ->first();

        if ($userProfile !== null) {
            return $userProfile->menu_keys;
        }

        $roleIds = $user->roles()
            ->where('guard_name', 'web')
            ->pluck('roles.id');

        $roleProfiles = MenuVisibilityProfile::query()
            ->whereIn('role_id', $roleIds)
            ->get(['menu_keys']);

        if ($roleProfiles->isEmpty()) {
            return null;
        }

        return $roleProfiles
            ->flatMap(fn (MenuVisibilityProfile $profile): array => $profile->menu_keys)
            ->unique()
            ->values()
            ->all();
    }
}
