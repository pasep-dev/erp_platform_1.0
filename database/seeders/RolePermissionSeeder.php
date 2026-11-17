<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Permissions
        |--------------------------------------------------------------------------
        |
        | Permission menggunakan pola:
        | module.action
        |
        | Contoh:
        | master.view
        | master.create
        | master.update
        | master.delete
        |
        */

        $permissions = [
            // Dashboard
            'dashboard.view',

            // Master - Organization
            'master.organization.view',
            'master.organization.create',
            'master.organization.update',
            'master.organization.delete',

            // Master - Product
            'master.product.view',
            'master.product.create',
            'master.product.update',
            'master.product.delete',

            // Master - Procurement
            'master.procurement.view',
            'master.procurement.create',
            'master.procurement.update',
            'master.procurement.delete',

            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Activity Logs
            'activity-logs.view',

            // System Settings
            'system.settings.view',
            'system.settings.update',
            'system.settings.backup',

            // Roles & Permissions
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',

            // Approval Workflow Configuration
            'approval.workflow.view',
            'approval.workflow.create',
            'approval.workflow.update',
            'approval.workflow.delete',

            // Document Numbering Configuration
            'numbering.document.view',
            'numbering.document.create',
            'numbering.document.update',

            // Approval Transaction
            'approval.view',
            'approval.approve',
            'approval.reject',

            // Purchase Request
            'purchase-request.view',
            'purchase-request.create',
            'purchase-request.update',
            'purchase-request.delete',
            'purchase-request.submit',
            'purchase-request.cancel',

            // Purchase Order
            'purchase-order.view',
            'purchase-order.create',
            'purchase-order.update',
            'purchase-order.delete',
            'purchase-order.submit',
            'purchase-order.cancel',

            // Good Receipt
            'good-receipt.view',
            'good-receipt.create',
            'good-receipt.update',
            'good-receipt.delete',
            'good-receipt.submit',

            // Registration
            'registration.view',
            'registration.create',
            'registration.update',

            // Asset
            'asset.view',
            'asset.create',
            'asset.update',
            'asset.delete',
            'asset.transfer',
            'asset.dispose',

            // Consumable
            'consumable.view',
            'consumable.create',
            'consumable.update',
            'consumable.delete',
            'consumable.issue',
            'consumable.request',

            // License
            'license.view',
            'license.create',
            'license.update',
            'license.delete',
            'license.assign',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */

        $administrator = Role::firstOrCreate([
            'name' => 'Administrator',
            'guard_name' => 'web',
        ]);

        $owner = Role::firstOrCreate([
            'name' => 'Owner',
            'guard_name' => 'web',
        ]);

        $manager = Role::firstOrCreate([
            'name' => 'Manager',
            'guard_name' => 'web',
        ]);

        $user = Role::firstOrCreate([
            'name' => 'User',
            'guard_name' => 'web',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Administrator
        |--------------------------------------------------------------------------
        |
        | Administrator mendapatkan seluruh permission.
        |
        */

        $administrator->syncPermissions(
            Permission::where('guard_name', 'web')->get()
        );

        $owner->syncPermissions(Permission::where('guard_name', 'web')->get());

        /*
        |--------------------------------------------------------------------------
        | Manager
        |--------------------------------------------------------------------------
        |
        | Manager dapat melihat transaksi dan melakukan approval.
        | Permission spesifik dapat kita tambah/ubah nanti sesuai kebutuhan
        | perusahaan.
        |
        */

        $manager->syncPermissions([
            'dashboard.view',

            'master.organization.view',
            'master.product.view',
            'master.procurement.view',

            'approval.workflow.view',
            'approval.view',
            'approval.approve',
            'approval.reject',

            'purchase-request.view',
            'purchase-request.create',
            'purchase-request.update',
            'purchase-request.submit',

            'purchase-order.view',
            'purchase-order.create',
            'purchase-order.update',
            'purchase-order.submit',

            'good-receipt.view',
            'good-receipt.create',
            'good-receipt.update',
            'good-receipt.submit',

            'registration.view',
            'registration.create',
            'registration.update',

            'asset.view',
            'asset.create',
            'asset.update',
            'asset.transfer',
            'asset.dispose',

            'consumable.view',
            'consumable.create',
            'consumable.update',
            'consumable.issue',
            'consumable.request',

            'license.view',
            'license.create',
            'license.update',
            'license.assign',
        ]);

        /*
        |--------------------------------------------------------------------------
        | User
        |--------------------------------------------------------------------------
        |
        | User biasa dapat membuat dan melihat Purchase Request.
        | Approval tetap dikontrol oleh approval workflow, bukan oleh role.
        |
        */

        $user->syncPermissions([
            'dashboard.view',

            'master.organization.view',
            'master.product.view',
            'master.procurement.view',

            'purchase-request.view',
            'purchase-request.create',
            'purchase-request.update',
            'purchase-request.submit',

            'registration.view',

            'asset.view',
            'consumable.view',
            'consumable.request',
            'license.view',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Assign Administrator Role
        |--------------------------------------------------------------------------
        |
        | Untuk sementara kita assign Administrator kepada user pertama.
        | Ini hanya untuk memastikan sistem bisa langsung digunakan setelah
        | seeding.
        |
        */

        $firstUser = User::query()
            ->orderBy('id')
            ->first();

        if ($firstUser) {
            $firstUser->syncRoles([$administrator]);
        }
    }
}
