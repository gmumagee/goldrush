<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\CalendarEvent;
use App\Models\CalendarReminder;
use App\Models\Location;
use App\Models\RouteLocation;
use App\Models\Service;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoScheduleRouteServicesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_creates_services_events_and_reminders_seven_days_ahead_and_is_idempotent(): void
    {
        Carbon::setTestNow('2026-07-20 08:00:00');

        $account = $this->createAccount('Alpha Vending');
        $technician = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->attachUserToAccount($technician, $account, AccountUser::ROLE_TECHNICIAN);

        $warehouse = $this->createWarehouse($account, 'Main Warehouse');
        $route = $this->createRoute($account, 'Monday Route', [
            'scheduled_day' => 'Monday',
            'warehouse_id' => $warehouse->id,
            'assigned_user_id' => $technician->id,
            'auto_schedule_enabled' => true,
        ]);

        $locationOne = $this->createLocation($account, $route, 'Campus Center');
        $locationTwo = $this->createLocation($account, $route, 'Library Annex');
        $this->attachLocationToRoute($account, $route, $locationOne, 1);
        $this->attachLocationToRoute($account, $route, $locationTwo, 2);

        $this->artisan('services:auto-schedule-routes', ['--date' => '2026-07-20'])
            ->expectsOutput('Auto-scheduling route services for 2026-07-27 Monday')
            ->expectsOutput('Routes found: 1')
            ->expectsOutput('Services created: 2')
            ->expectsOutput('Services skipped as duplicates: 0')
            ->expectsOutput('Routes skipped without warehouse: 0')
            ->assertExitCode(0);

        $services = Service::query()
            ->where('account_id', $account->id)
            ->orderBy('location_id')
            ->get();

        $this->assertCount(2, $services);

        foreach ($services as $service) {
            $this->assertSame($account->id, $service->account_id);
            $this->assertSame($warehouse->id, $service->warehouse_id);
            $this->assertSame($technician->id, $service->user_id);
            $this->assertSame(Service::TYPE_LOCATION_SERVICE, $service->service_type);
            $this->assertSame(Service::STATUS_AWAITING_SERVICE, $service->status);
            $this->assertSame('2026-07-27', $service->service_date?->format('Y-m-d'));

            $event = CalendarEvent::query()
                ->where('account_id', $account->id)
                ->where('source_type', CalendarEvent::SOURCE_TYPE_SERVICE)
                ->where('source_id', $service->id)
                ->first();

            $this->assertNotNull($event);
            $this->assertSame('Service', $event->event_type);
            $this->assertSame(CalendarEvent::STATUS_SCHEDULED, $event->status);
            $this->assertSame('Normal', $event->priority);
            $this->assertTrue($event->all_day);
            $this->assertSame($route->id, $event->route_id);
            $this->assertSame($warehouse->id, $event->warehouse_id);
            $this->assertSame($service->location_id, $event->location_id);
            $this->assertSame($technician->id, $event->assigned_user_id);
            $this->assertSame('2026-07-27 00:00:00', $event->start_at?->format('Y-m-d H:i:s'));
            $this->assertSame('2026-07-27 23:59:59', $event->end_at?->format('Y-m-d H:i:s'));
            $this->assertSame(
                'Automatically scheduled from route: Monday Route',
                $event->description
            );

            $reminder = CalendarReminder::query()
                ->where('account_id', $account->id)
                ->where('calendar_event_id', $event->id)
                ->where('status', CalendarReminder::STATUS_PENDING)
                ->first();

            $this->assertNotNull($reminder);
            $this->assertSame(CalendarReminder::TYPE_DASHBOARD, $reminder->reminder_type);
            $this->assertSame($technician->id, $reminder->assigned_user_id);
            $this->assertSame('2026-07-20 08:00:00', $reminder->remind_at?->format('Y-m-d H:i:s'));
        }

        $this->artisan('services:auto-schedule-routes', ['--date' => '2026-07-20'])
            ->expectsOutput('Auto-scheduling route services for 2026-07-27 Monday')
            ->expectsOutput('Routes found: 1')
            ->expectsOutput('Services created: 0')
            ->expectsOutput('Services skipped as duplicates: 2')
            ->expectsOutput('Routes skipped without warehouse: 0')
            ->assertExitCode(0);

        $this->assertDatabaseCount('tbl_services', 2);
        $this->assertDatabaseCount('tbl_calendar_events', 2);
        $this->assertDatabaseCount('tbl_calendar_reminders', 2);
    }

    public function test_command_skips_routes_without_valid_warehouse_and_does_not_assign_cross_account_users(): void
    {
        Carbon::setTestNow('2026-07-20 09:30:00');

        $accountA = $this->createAccount('Account A');
        $accountB = $this->createAccount('Account B');

        $validWarehouse = $this->createWarehouse($accountA, 'Account A Warehouse');
        $foreignUser = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->attachUserToAccount($foreignUser, $accountB, AccountUser::ROLE_TECHNICIAN);

        $missingWarehouseRoute = $this->createRoute($accountA, 'No Warehouse Route', [
            'scheduled_day' => 'Monday',
            'warehouse_id' => null,
            'assigned_user_id' => null,
            'auto_schedule_enabled' => true,
        ]);
        $missingWarehouseLocation = $this->createLocation($accountA, $missingWarehouseRoute, 'Skipped Stop');
        $this->attachLocationToRoute($accountA, $missingWarehouseRoute, $missingWarehouseLocation, 1);

        $foreignUserRoute = $this->createRoute($accountA, 'Foreign User Route', [
            'scheduled_day' => 'Monday',
            'warehouse_id' => $validWarehouse->id,
            'assigned_user_id' => $foreignUser->id,
            'auto_schedule_enabled' => true,
        ]);
        $foreignUserLocation = $this->createLocation($accountA, $foreignUserRoute, 'Valid Stop');
        $this->attachLocationToRoute($accountA, $foreignUserRoute, $foreignUserLocation, 1);

        $this->artisan('services:auto-schedule-routes', ['--date' => '2026-07-20'])
            ->expectsOutput('Auto-scheduling route services for 2026-07-27 Monday')
            ->expectsOutput('Routes found: 2')
            ->expectsOutput('Services created: 1')
            ->expectsOutput('Services skipped as duplicates: 0')
            ->expectsOutput('Routes skipped without warehouse: 1')
            ->expectsOutput('Routes with invalid assigned technicians: 1')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('tbl_services', [
            'account_id' => $accountA->id,
            'location_id' => $missingWarehouseLocation->id,
            'service_date' => '2026-07-27',
        ]);

        $service = Service::query()
            ->where('account_id', $accountA->id)
            ->where('location_id', $foreignUserLocation->id)
            ->firstOrFail();

        $this->assertNull($service->user_id);
        $this->assertSame($validWarehouse->id, $service->warehouse_id);
    }

    protected function createAccount(string $name): Account
    {
        return Account::create([
            'account_name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'status' => 'active',
            'billing_email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
        ]);
    }

    protected function attachUserToAccount(User $user, Account $account, string $role): void
    {
        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => AccountUser::STATUS_ACTIVE,
        ]);
    }

    protected function createRoute(Account $account, string $name, array $attributes = []): VendingRoute
    {
        return VendingRoute::create(array_merge([
            'account_id' => $account->id,
            'route_name' => $name,
            'description' => $name.' description',
            'scheduled_day' => 'Monday',
            'warehouse_id' => null,
            'assigned_user_id' => null,
            'auto_schedule_enabled' => true,
        ], $attributes));
    }

    protected function createLocation(Account $account, VendingRoute $route, string $name): Location
    {
        return Location::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_name' => $name,
            'address' => '123 Service Road',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M1M1M1',
            'contact_name' => 'Casey Tech',
        ]);
    }

    protected function attachLocationToRoute(Account $account, VendingRoute $route, Location $location, int $stopOrder): RouteLocation
    {
        return RouteLocation::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_id' => $location->id,
            'stop_order' => $stopOrder,
        ]);
    }

    protected function createWarehouse(Account $account, string $name): Warehouse
    {
        return Warehouse::create([
            'account_id' => $account->id,
            'warehouse_name' => $name,
            'address' => '10 Storage Way',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M1M1M1',
        ]);
    }
}
