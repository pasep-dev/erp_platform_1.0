<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->syncOwnerPermissions([
            'dashboard.view',
            'approval.view',
            'purchase-request.view',
            'purchase-order.view',
            'good-receipt.view',
            'registration.view',
            'asset.view',
            'consumable.view',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $rolePivot = $columnNames['role_pivot_key'] ?? 'role_id';
        $permissionPivot = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $ownerRoleId = DB::table($tableNames['roles'])
            ->where('name', 'Owner')
            ->where('guard_name', 'web')
            ->value('id');

        if ($ownerRoleId === null) {
            return;
        }

        DB::table($tableNames['role_has_permissions'])
            ->where($rolePivot, $ownerRoleId)
            ->delete();

        $permissions = DB::table($tableNames['permissions'])
            ->where('guard_name', 'web')
            ->get(['id']);

        foreach ($permissions as $permission) {
            DB::table($tableNames['role_has_permissions'])->insertOrIgnore([
                $rolePivot => $ownerRoleId,
                $permissionPivot => $permission->id,
            ]);
        }

        $this->clearPermissionCache();
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function syncOwnerPermissions(array $permissionNames): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $rolePivot = $columnNames['role_pivot_key'] ?? 'role_id';
        $permissionPivot = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $ownerRoleId = DB::table($tableNames['roles'])
            ->where('name', 'Owner')
            ->where('guard_name', 'web')
            ->value('id');

        if ($ownerRoleId === null) {
            return;
        }

        $permissionIds = DB::table($tableNames['permissions'])
            ->where('guard_name', 'web')
            ->whereIn('name', $permissionNames)
            ->pluck('id');

        DB::table($tableNames['role_has_permissions'])
            ->where($rolePivot, $ownerRoleId)
            ->delete();

        foreach ($permissionIds as $permissionId) {
            DB::table($tableNames['role_has_permissions'])->insertOrIgnore([
                $rolePivot => $ownerRoleId,
                $permissionPivot => $permissionId,
            ]);
        }

        $this->clearPermissionCache();
    }

    private function clearPermissionCache(): void
    {
        app('cache')
            ->store(config('permission.cache.store') !== 'default'
                ? config('permission.cache.store')
                : null)
            ->forget(config('permission.cache.key'));
    }
};
