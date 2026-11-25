<?php

namespace Database\Seeders;

use App\Models\NumberingModule;
use Illuminate\Database\Seeder;

class NumberingModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (NumberingModule::availableModules() as $module) {
            NumberingModule::query()->firstOrCreate(
                ['code' => $module['code']],
                [
                    ...$module,
                    'default_pattern' => null,
                    'sequence_length' => null,
                    'reset_period' => null,
                    'is_active' => true,
                ],
            );
        }
    }
}
