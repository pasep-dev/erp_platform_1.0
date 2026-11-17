<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';

        throw_if(empty($tableNames), 'Permission table names are not configured.');

        $permissionId = DB::table($tableNames['permissions'])
            ->where('name', 'activity-logs.view')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId === null) {
            $permissionId = DB::table($tableNames['permissions'])->insertGetId([
                'name' => 'activity-logs.view',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $administratorRoleId = DB::table($tableNames['roles'])
            ->where('name', 'Administrator')
            ->where('guard_name', 'web')
            ->value('id');

        if ($administratorRoleId !== null) {
            DB::table($tableNames['role_has_permissions'])->insertOrIgnore([
                $pivotPermission => $permissionId,
                $pivotRole => $administratorRoleId,
            ]);
        }

        $this->clearPermissionCache();
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $permissionId = DB::table($tableNames['permissions'])
            ->where('name', 'activity-logs.view')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table($tableNames['role_has_permissions'])
            ->where($pivotPermission, $permissionId)
            ->delete();

        DB::table($tableNames['model_has_permissions'])
            ->where($pivotPermission, $permissionId)
            ->delete();

        DB::table($tableNames['permissions'])
            ->where('id', $permissionId)
            ->delete();

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
