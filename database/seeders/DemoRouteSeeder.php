<?php

namespace Database\Seeders;

use App\Models\VendingRoute;

class DemoRouteSeeder extends DemoSeeder
{
    public function run(): void
    {
        $accountId = $this->demoAccount()->id;

        foreach ($this->routes() as $route) {
            VendingRoute::query()->updateOrCreate(
                [
                    'account_id' => $accountId,
                    'route_name' => $route['route_name'],
                ],
                [
                    'description' => $route['description'],
                    'scheduled_day' => $route['scheduled_day'],
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
            ],
            [
                'route_name' => 'Wednesday DC Route',
                'scheduled_day' => 'Wednesday',
                'description' => 'Washington, DC campus and office route.',
            ],
            [
                'route_name' => 'Friday Northern Virginia Route',
                'scheduled_day' => 'Friday',
                'description' => 'Northern Virginia end-of-week service route.',
            ],
        ];
    }
}
