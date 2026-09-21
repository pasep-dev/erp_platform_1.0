<?php

namespace Database\Seeders\Master;

use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\CostCenter;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use Illuminate\Database\Seeder;

class OrganizationMasterSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::updateOrCreate(
            [
                'code' => 'CAA',
            ],
            [
                'name' => 'PT Contoh Abadi',
                'short_name' => 'CAA',
                'is_active' => true,
            ],
        );

        $branchJakarta = Branch::updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'JKT',
            ],
            [
                'name' => 'Cabang Jakarta',
                'address' => 'Jakarta',
                'is_active' => true,
            ],
        );

        $branchBandung = Branch::updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'BDG',
            ],
            [
                'name' => 'Cabang Bandung',
                'address' => 'Bandung',
                'is_active' => true,
            ],
        );

        $departmentIt = Department::updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'IT',
            ],
            [
                'name' => 'Information Technology',
                'is_active' => true,
            ],
        );

        $departmentFinance = Department::updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'FIN',
            ],
            [
                'name' => 'Finance',
                'is_active' => true,
            ],
        );

        $locationHeadOffice = Location::updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'HO',
            ],
            [
                'branch_id' => null,
                'name' => 'Head Office',
                'parent_id' => null,
                'location_type' => 'building',
                'is_active' => true,
            ],
        );

        $locationJakartaIt = Location::updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'JKT-IT',
            ],
            [
                'branch_id' => $branchJakarta->id,
                'name' => 'Ruang IT Jakarta',
                'parent_id' => $locationHeadOffice->id,
                'location_type' => 'room',
                'is_active' => true,
            ],
        );

        $locationBandungOffice = Location::updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'BDG-OFFICE',
            ],
            [
                'branch_id' => $branchBandung->id,
                'name' => 'Kantor Bandung',
                'parent_id' => null,
                'location_type' => 'building',
                'is_active' => true,
            ],
        );

        CostCenter::updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'CC-IT',
            ],
            [
                'name' => 'Cost Center IT',
                'is_active' => true,
            ],
        );

        CostCenter::updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'CC-FIN',
            ],
            [
                'name' => 'Cost Center Finance',
                'is_active' => true,
            ],
        );
    }
}
