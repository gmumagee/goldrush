<?php

namespace Database\Seeders;

use App\Models\Warehouse;

class DemoWarehouseSeeder extends DemoSeeder
{
    public function run(): void
    {
        $accountId = $this->demoAccount()->id;

        foreach ($this->warehouses() as $warehouse) {
            Warehouse::query()->updateOrCreate(
                [
                    'account_id' => $accountId,
                    'warehouse_name' => $warehouse['warehouse_name'],
                ],
                [
                    'address' => $warehouse['address'],
                    'city' => $warehouse['city'],
                    'state' => $warehouse['state'],
                    'zip_code' => $warehouse['zip_code'],
                ],
            );
        }
    }

    protected function warehouses(): array
    {
        return [
            [
                'warehouse_name' => 'Main Warehouse',
                'address' => '100 Warehouse Way',
                'city' => 'Arlington',
                'state' => 'VA',
                'zip_code' => '22201',
            ],
            [
                'warehouse_name' => 'North Storage',
                'address' => '200 Storage Ave',
                'city' => 'Washington',
                'state' => 'DC',
                'zip_code' => '20001',
            ],
        ];
    }
}
