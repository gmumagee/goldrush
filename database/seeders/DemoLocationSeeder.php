<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\RouteLocation;
use Illuminate\Support\Facades\DB;

class DemoLocationSeeder extends DemoSeeder
{
    public function run(): void
    {
        $accountId = $this->demoAccount()->id;

        foreach ($this->locations() as $location) {
            $route = $this->routeForAccount($accountId, $location['route_name']);

            Location::query()->updateOrCreate(
                [
                    'account_id' => $accountId,
                    'location_name' => $location['location_name'],
                ],
                [
                    'address' => $location['address'],
                    'city' => $location['city'],
                    'state' => $location['state'],
                    'zip_code' => $location['zip_code'],
                ],
            );
        }

        DB::transaction(function () use ($accountId) {
            foreach ($this->routeStops() as $routeName => $locationNames) {
                $route = $this->routeForAccount($accountId, $routeName);
                $locationIds = collect($locationNames)
                    ->map(fn (string $locationName) => $this->locationForAccount($accountId, $locationName)->id)
                    ->all();

                RouteLocation::query()
                    ->where('account_id', $accountId)
                    ->where('route_id', $route->id)
                    ->whereNotIn('location_id', $locationIds)
                    ->delete();

                RouteLocation::query()
                    ->where('account_id', $accountId)
                    ->where('route_id', $route->id)
                    ->update([
                        'stop_order' => DB::raw('stop_order + 1000'),
                    ]);

                foreach ($locationIds as $index => $locationId) {
                    $locationName = $locationNames[$index];
                    $isPrimary = $this->primaryRouteNameForLocation($locationName) === $routeName;

                    RouteLocation::query()->updateOrCreate(
                        [
                            'account_id' => $accountId,
                            'route_id' => $route->id,
                            'location_id' => $locationId,
                        ],
                        [
                            'stop_order' => $index + 1,
                            'is_primary' => $isPrimary,
                        ],
                    );
                }
            }
        });
    }

    protected function locations(): array
    {
        return [
            [
                'route_name' => 'Monday Arlington Route',
                'location_name' => 'Main Office',
                'address' => '123 Main Street',
                'city' => 'Arlington',
                'state' => 'VA',
                'zip_code' => '22201',
            ],
            [
                'route_name' => 'Monday Arlington Route',
                'location_name' => 'Tech Center',
                'address' => '500 Innovation Drive',
                'city' => 'Arlington',
                'state' => 'VA',
                'zip_code' => '22202',
            ],
            [
                'route_name' => 'Wednesday DC Route',
                'location_name' => 'University Hall',
                'address' => '100 Campus Lane',
                'city' => 'Washington',
                'state' => 'DC',
                'zip_code' => '20001',
            ],
            [
                'route_name' => 'Friday Northern Virginia Route',
                'location_name' => 'City Gym',
                'address' => '22 Fitness Road',
                'city' => 'Alexandria',
                'state' => 'VA',
                'zip_code' => '22301',
            ],
            [
                'route_name' => 'Friday Northern Virginia Route',
                'location_name' => 'Medical Plaza',
                'address' => '88 Health Park Drive',
                'city' => 'Falls Church',
                'state' => 'VA',
                'zip_code' => '22042',
            ],
        ];
    }

    protected function routeStops(): array
    {
        return [
            'Monday Arlington Route' => [
                'Main Office',
                'Tech Center',
            ],
            'Wednesday DC Route' => [
                'University Hall',
            ],
            'Friday Northern Virginia Route' => [
                'City Gym',
                'Medical Plaza',
            ],
        ];
    }

    protected function primaryRouteNameForLocation(string $locationName): ?string
    {
        return collect($this->locations())
            ->firstWhere('location_name', $locationName)['route_name'] ?? null;
    }
}
