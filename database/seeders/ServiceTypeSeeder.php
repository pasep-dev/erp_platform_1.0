<?php

namespace Database\Seeders;

use App\Models\Master\Procurement\ServiceType;
use Illuminate\Database\Seeder;

class ServiceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'code' => 'SRV-AC',
                'name' => 'Service AC',
                'requires_asset' => true,
            ],
            [
                'code' => 'SRV-PRINTER',
                'name' => 'Maintenance Printer',
                'requires_asset' => true,
            ],
            [
                'code' => 'SRV-LAPTOP',
                'name' => 'Service Laptop',
                'requires_asset' => true,
            ],
            [
                'code' => 'SRV-CLEAN',
                'name' => 'Cleaning Service Office',
                'requires_asset' => false,
            ],
            [
                'code' => 'SRV-CONSULT',
                'name' => 'Consulting Service',
                'requires_asset' => false,
            ],
        ];

        foreach ($services as $service) {
            ServiceType::updateOrCreate(
                [
                    'code' => $service['code'],
                ],
                [
                    'name' => $service['name'],
                    'requires_asset' => $service['requires_asset'],
                    'is_active' => true,
                ]
            );
        }
    }
}
