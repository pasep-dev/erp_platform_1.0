<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            InventoryRequestPermissionSeeder::class,
            NumberingModuleSeeder::class,
            MasterNumberingSeeder::class,
            PurchaseOrderNumberSeeder::class,
            AssetNumberSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
