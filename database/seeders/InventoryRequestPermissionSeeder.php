<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class InventoryRequestPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::query()->firstOrCreate([
            'name' => 'consumable.request',
            'guard_name' => 'web',
        ]);

        foreach (['Administrator', 'Manager', 'User'] as $roleName) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
    }
}
