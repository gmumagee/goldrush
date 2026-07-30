<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            DataDictionarySeeder::class,
            DemoAccountSeeder::class,
            DemoUsersSeeder::class,
            DemoWarehouseSeeder::class,
            DemoVendorSeeder::class,
            DemoProductSeeder::class,
            DemoRouteSeeder::class,
            DemoLocationSeeder::class,
            DemoContactSeeder::class,
            DemoMachineSeeder::class,
            DemoPurchaseSeeder::class,
            DemoServiceSeeder::class,
        ]);
    }
}
