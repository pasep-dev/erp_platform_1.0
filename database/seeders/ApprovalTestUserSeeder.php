<?php

namespace Database\Seeders;

use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Position;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ApprovalTestUserSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'CAA')->firstOrFail();

        $department = Department::where('code', 'FIN')->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Positions
        |--------------------------------------------------------------------------
        */

        $positions = [
            'ADMIN' => 'Administrator',
            'MANAGER' => 'Manager',
            'PURCHASING_FINANCE' => 'Purchasing / Finance',
            'DIRECTOR' => 'Director',
        ];

        $positionModels = [];

        foreach ($positions as $code => $name) {
            $positionModels[$code] = Position::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'code' => $code,
                ],
                [
                    'name' => $name,
                    'is_active' => true,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Test Users
        |--------------------------------------------------------------------------
        */

        $users = [
            [
                'name' => 'Admin Test',
                'email' => 'admin.test@company.local',
                'position' => 'ADMIN',
            ],
            [
                'name' => 'Manager Test',
                'email' => 'manager.test@company.local',
                'position' => 'MANAGER',
            ],
            [
                'name' => 'Purchasing Finance Test',
                'email' => 'purchasing.test@company.local',
                'position' => 'PURCHASING_FINANCE',
            ],
            [
                'name' => 'Director Test',
                'email' => 'director.test@company.local',
                'position' => 'DIRECTOR',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                [
                    'email' => $userData['email'],
                ],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('Password123!'),
                    'is_active' => true,
                    'company_id' => $company->id,
                    'branch_id' => null,
                    'department_id' => $department->id,
                    'position_id' => $positionModels[$userData['position']]->id,
                ]
            );
        }

        $this->command->info('Approval test users created successfully.');
        $this->command->newLine();

        foreach ($users as $userData) {
            $this->command->line(
                sprintf(
                    '%-28s %s',
                    $userData['name'],
                    $userData['email']
                )
            );
        }

        $this->command->newLine();
        $this->command->info('Password: Password123!');
    }
}
