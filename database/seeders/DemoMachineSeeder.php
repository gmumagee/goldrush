<?php

namespace Database\Seeders;

use App\Models\Bin;
use App\Models\Machine;

class DemoMachineSeeder extends DemoSeeder
{
    public function run(): void
    {
        $accountId = $this->demoAccount()->id;

        foreach ($this->machines() as $machineDefinition) {
            $location = $this->locationForAccount($accountId, $machineDefinition['location_name']);

            $machine = Machine::query()->updateOrCreate(
                [
                    'account_id' => $accountId,
                    'serial_number' => $machineDefinition['serial_number'],
                ],
                [
                    'location_id' => $location->id,
                    'type' => $machineDefinition['type'],
                    'model' => $machineDefinition['model'],
                    'status' => Machine::STATUS_ACTIVE,
                    'installed_on' => $machineDefinition['installed_on'],
                ],
            );

            foreach ($this->binBlueprints($machineDefinition['bin_layout']) as $binDefinition) {
                $product = $this->productForAccount($accountId, $binDefinition['sku']);

                Bin::query()->updateOrCreate(
                    [
                        'machine_id' => $machine->id,
                        'bin_code' => $binDefinition['bin_code'],
                    ],
                    [
                        'account_id' => $accountId,
                        'product_id' => $product->id,
                        'capacity' => $binDefinition['capacity'],
                        'price' => $binDefinition['price'],
                    ],
                );
            }
        }
    }

    protected function machines(): array
    {
        $installedOn = today()->subMonths(6)->toDateString();

        return [
            [
                'location_name' => 'Main Office',
                'type' => 'Snack Machine',
                'serial_number' => 'MO-SNACK-001',
                'model' => 'Crane National 167',
                'installed_on' => $installedOn,
                'bin_layout' => 'snack',
            ],
            [
                'location_name' => 'Main Office',
                'type' => 'Soda Machine',
                'serial_number' => 'MO-SODA-001',
                'model' => 'Dixie Narco 501E',
                'installed_on' => $installedOn,
                'bin_layout' => 'soda',
            ],
            [
                'location_name' => 'Tech Center',
                'type' => 'Combo Machine',
                'serial_number' => 'TC-COMBO-001',
                'model' => 'AMS Sensit 40',
                'installed_on' => $installedOn,
                'bin_layout' => 'combo',
            ],
            [
                'location_name' => 'University Hall',
                'type' => 'Snack Machine',
                'serial_number' => 'UH-SNACK-001',
                'model' => 'Crane National 167',
                'installed_on' => $installedOn,
                'bin_layout' => 'snack',
            ],
            [
                'location_name' => 'University Hall',
                'type' => 'Soda Machine',
                'serial_number' => 'UH-SODA-001',
                'model' => 'Dixie Narco 501E',
                'installed_on' => $installedOn,
                'bin_layout' => 'soda',
            ],
            [
                'location_name' => 'City Gym',
                'type' => 'Combo Machine',
                'serial_number' => 'CG-COMBO-001',
                'model' => 'AMS Sensit 40',
                'installed_on' => $installedOn,
                'bin_layout' => 'combo',
            ],
            [
                'location_name' => 'Medical Plaza',
                'type' => 'Snack Machine',
                'serial_number' => 'MP-SNACK-001',
                'model' => 'Crane National 167',
                'installed_on' => $installedOn,
                'bin_layout' => 'snack',
            ],
        ];
    }

    protected function binBlueprints(string $layout): array
    {
        $snackBins = [
            ['bin_code' => 'A1', 'sku' => 'DORITOS-NACHO-175-BAG', 'capacity' => 20, 'price' => 1.75],
            ['bin_code' => 'A2', 'sku' => 'LAYS-CLASSIC-15-BAG', 'capacity' => 20, 'price' => 1.50],
            ['bin_code' => 'A3', 'sku' => 'SNICKERS-186-BAR', 'capacity' => 20, 'price' => 1.75],
            ['bin_code' => 'A4', 'sku' => 'MMS-PEANUT-174-BAG', 'capacity' => 20, 'price' => 1.75],
            ['bin_code' => 'A5', 'sku' => 'OREO-24-PACK', 'capacity' => 20, 'price' => 1.50],
        ];

        $sodaBins = [
            ['bin_code' => 'B1', 'sku' => 'COKE-12-CAN', 'capacity' => 24, 'price' => 1.25],
            ['bin_code' => 'B2', 'sku' => 'DIETCOKE-12-CAN', 'capacity' => 24, 'price' => 1.25],
            ['bin_code' => 'B3', 'sku' => 'PEPSI-12-CAN', 'capacity' => 24, 'price' => 1.25],
            ['bin_code' => 'B4', 'sku' => 'AQUAFINA-169-BTL', 'capacity' => 24, 'price' => 1.50],
            ['bin_code' => 'B5', 'sku' => 'GATORADE-FP-20-BTL', 'capacity' => 18, 'price' => 2.25],
        ];

        return match ($layout) {
            'snack' => $snackBins,
            'soda' => $sodaBins,
            default => array_merge($snackBins, $sodaBins),
        };
    }
}
