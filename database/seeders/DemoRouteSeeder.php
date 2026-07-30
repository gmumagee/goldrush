<?php

namespace Database\Seeders;

use App\Models\VendingRoute;

class DemoRouteSeeder extends AbstractDemoSeeder
{
    public function run(): void
    {
        $accountId = $this->demoAccount()->id;
        $warehouseIdsByName = [
            'Main Warehouse' => $this->warehouseForAccount($accountId, 'Main Warehouse')->id,
            'North Storage' => $this->warehouseForAccount($accountId, 'North Storage')->id,
        ];

        foreach ($this->routes() as $route) {
            VendingRoute::query()->updateOrCreate(
                [
                    'account_id' => $accountId,
                    'route_name' => $route['route_name'],
                ],
                [
                    'description' => $route['description'],
                    'scheduled_day' => $route['scheduled_day'],
                    'warehouse_id' => $warehouseIdsByName[$route['warehouse_name']],
                    'auto_schedule_enabled' => true,
                ],
            );
        }
    }

    protected function routes(): array
    {
        return [
            [
                'route_name' => 'Monday Arlington Route',
                'scheduled_day' => 'Monday',
                'description' => 'Primary Arlington stops for Monday service visits.',
                'warehouse_name' => 'Main Warehouse',
            ],
            [
                'route_name' => 'Wednesday DC Route',
                'scheduled_day' => 'Wednesday',
                'description' => 'Washington, DC campus and office route.',
                'warehouse_name' => 'North Storage',
            ],
            [
                'route_name' => 'Friday Northern Virginia Route',
                'scheduled_day' => 'Friday',
                'description' => 'Northern Virginia end-of-week service route.',
                'warehouse_name' => 'Main Warehouse',
            ],
        ];
    }
}
