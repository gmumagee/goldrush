<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\CalendarEvent;
use App\Models\CalendarReminder;
use App\Models\Location;
use App\Models\Purchase;
use App\Models\Service;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Warehouse;
use Carbon\Carbon;
use Database\Seeders\DataDictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DataDictionarySeeder::class);
        Carbon::setTestNow('2026-07-16 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_user_can_create_events_and_manage_dashboard_reminders(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Alpha Vending');
        $this->attachUserToAccount($user, $account, 'owner');

        $route = $this->createRoute($account, 'North Route');
        $location = $this->createLocation($account, $route, 'Main Office');
        $warehouse = $this->createWarehouse($account, 'Main Warehouse');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('calendar-events.store'), [
                'event_type' => 'General',
                'title' => 'Insurance renewal call',
                'description' => 'Confirm policy dates and coverage.',
                'start_date' => '18-07-2026',
                'start_time' => '09:00:00',
                'end_date' => '18-07-2026',
                'end_time' => '10:00:00',
                'status' => CalendarEvent::STATUS_SCHEDULED,
                'priority' => 'High',
                'assigned_user_id' => $user->id,
                'location_id' => $location->id,
                'warehouse_id' => $warehouse->id,
                'route_id' => $route->id,
                'reminder_option' => 'custom',
                'reminder_custom_date' => '16-07-2026',
                'reminder_custom_time' => '07:00:00',
            ])
            ->assertRedirect();

        $event = CalendarEvent::query()->where('title', 'Insurance renewal call')->firstOrFail();
        $reminder = CalendarReminder::query()->where('calendar_event_id', $event->id)->firstOrFail();
        $reminder->update(['message' => 'Insurance renewal reminder']);

        $this->assertDatabaseHas('tbl_calendar_events', [
            'id' => $event->id,
            'account_id' => $account->id,
            'event_type' => 'General',
            'location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'route_id' => $route->id,
        ]);

        $this->assertDatabaseHas('tbl_calendar_reminders', [
            'id' => $reminder->id,
            'account_id' => $account->id,
            'status' => CalendarReminder::STATUS_PENDING,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Insurance renewal reminder')
            ->assertSee('Insurance renewal call')
            ->assertSee('Upcoming Events');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('calendar-reminders.dismiss', $reminder))
            ->assertRedirect();

        $this->assertDatabaseHas('tbl_calendar_reminders', [
            'id' => $reminder->id,
            'status' => CalendarReminder::STATUS_DISMISSED,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Insurance renewal reminder');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('calendar-events.store'), [
                'event_type' => 'Purchase',
                'title' => 'Buy inventory for Main Warehouse next Friday',
                'start_date' => '23-07-2026',
                'start_time' => '11:00:00',
                'status' => CalendarEvent::STATUS_SCHEDULED,
                'warehouse_id' => $warehouse->id,
                'reminder_option' => 'none',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tbl_calendar_events', [
            'account_id' => $account->id,
            'event_type' => 'Purchase',
            'title' => 'Buy inventory for Main Warehouse next Friday',
            'source_type' => null,
            'source_id' => null,
        ]);
    }

    public function test_calendar_access_and_links_are_scoped_to_the_current_account(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $accountA = $this->createAccount('Account A');
        $accountB = $this->createAccount('Account B');
        $this->attachUserToAccount($user, $accountA, 'owner');

        $routeB = $this->createRoute($accountB, 'South Route');
        $locationB = $this->createLocation($accountB, $routeB, 'Remote Stop');

        $eventB = CalendarEvent::create([
            'account_id' => $accountB->id,
            'event_type' => 'General',
            'title' => 'Other account event',
            'start_at' => '2026-07-18 09:00:00',
            'status' => CalendarEvent::STATUS_SCHEDULED,
        ]);

        $reminderB = CalendarReminder::create([
            'account_id' => $accountB->id,
            'calendar_event_id' => $eventB->id,
            'remind_at' => '2026-07-16 07:00:00',
            'reminder_type' => CalendarReminder::TYPE_DASHBOARD,
            'status' => CalendarReminder::STATUS_PENDING,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $accountA->id])
            ->get(route('calendar-events.show', $eventB))
            ->assertNotFound();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $accountA->id])
            ->post(route('calendar-reminders.dismiss', $reminderB))
            ->assertNotFound();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $accountA->id])
            ->from(route('calendar-events.create'))
            ->post(route('calendar-events.store'), [
                'event_type' => 'General',
                'title' => 'Cross account attempt',
                'start_date' => '20-07-2026',
                'start_time' => '09:00:00',
                'status' => CalendarEvent::STATUS_SCHEDULED,
                'location_id' => $locationB->id,
                'source_type' => CalendarEvent::SOURCE_TYPE_LOCATION,
                'source_id' => $locationB->id,
                'reminder_option' => 'none',
            ])
            ->assertRedirect(route('calendar-events.create'))
            ->assertSessionHasErrors(['location_id', 'source_id']);

        $this->assertDatabaseMissing('tbl_calendar_events', [
            'title' => 'Cross account attempt',
        ]);
    }

    public function test_service_creation_and_completion_sync_the_linked_calendar_event(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Service Account');
        $this->attachUserToAccount($user, $account, 'owner');

        $route = $this->createRoute($account, 'East Route');
        $location = $this->createLocation($account, $route, 'Campus Center');
        $warehouse = $this->createWarehouse($account, 'Service Warehouse');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.store'), [
                'location_id' => $location->id,
                'warehouse_id' => $warehouse->id,
                'service_type' => Service::TYPE_LOCATION_SERVICE,
                'service_date' => '2026-07-18',
                'user_id' => $user->id,
            ])
            ->assertRedirect();

        $service = Service::query()->firstOrFail();
        $calendarEvent = CalendarEvent::query()
            ->where('account_id', $account->id)
            ->where('source_type', CalendarEvent::SOURCE_TYPE_SERVICE)
            ->where('source_id', $service->id)
            ->firstOrFail();

        $this->assertSame('Service', $calendarEvent->event_type);
        $this->assertSame('Service: Campus Center', $calendarEvent->title);
        $this->assertSame(CalendarEvent::STATUS_SCHEDULED, $calendarEvent->status);
        $this->assertSame($location->id, $calendarEvent->location_id);
        $this->assertSame($warehouse->id, $calendarEvent->warehouse_id);
        $this->assertTrue($calendarEvent->all_day);
        $this->assertSame('2026-07-18 00:00:00', $calendarEvent->start_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-18 23:59:59', $calendarEvent->end_at?->format('Y-m-d H:i:s'));

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('calendar-events.show', $calendarEvent))
            ->assertOk()
            ->assertSee('View Service')
            ->assertSee('All day');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.open', $service))
            ->assertRedirect();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.complete', $service))
            ->assertRedirect();

        $this->assertDatabaseHas('tbl_calendar_events', [
            'id' => $calendarEvent->id,
            'status' => CalendarEvent::STATUS_COMPLETED,
        ]);

        $this->assertNotNull($calendarEvent->fresh()->completed_at);
    }

    public function test_calendar_index_defaults_to_the_current_sunday_through_saturday_week_and_scheduled_events(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Weekly Calendar Account');
        $otherAccount = $this->createAccount('Other Calendar Account');
        $this->attachUserToAccount($user, $account, 'owner');

        $route = $this->createRoute($account, 'North Route');
        $location = $this->createLocation($account, $route, 'Main Office');

        CalendarEvent::create([
            'account_id' => $account->id,
            'event_type' => 'General',
            'title' => 'Scheduled Monday Visit',
            'start_at' => '2026-07-13 09:00:00',
            'end_at' => '2026-07-13 10:00:00',
            'all_day' => false,
            'status' => CalendarEvent::STATUS_SCHEDULED,
            'assigned_user_id' => $user->id,
            'location_id' => $location->id,
        ]);

        CalendarEvent::create([
            'account_id' => $account->id,
            'event_type' => 'Service',
            'title' => 'All Day Service',
            'start_at' => '2026-07-16 00:00:00',
            'end_at' => '2026-07-16 23:59:59',
            'all_day' => true,
            'status' => CalendarEvent::STATUS_SCHEDULED,
            'assigned_user_id' => $user->id,
            'location_id' => $location->id,
        ]);

        CalendarEvent::create([
            'account_id' => $account->id,
            'event_type' => 'General',
            'title' => 'Completed This Week',
            'start_at' => '2026-07-14 11:00:00',
            'all_day' => false,
            'status' => CalendarEvent::STATUS_COMPLETED,
        ]);

        CalendarEvent::create([
            'account_id' => $account->id,
            'event_type' => 'General',
            'title' => 'Next Week Scheduled',
            'start_at' => '2026-07-20 09:00:00',
            'all_day' => false,
            'status' => CalendarEvent::STATUS_SCHEDULED,
        ]);

        CalendarEvent::create([
            'account_id' => $otherAccount->id,
            'event_type' => 'General',
            'title' => 'Other Account Scheduled',
            'start_at' => '2026-07-13 09:00:00',
            'all_day' => false,
            'status' => CalendarEvent::STATUS_SCHEDULED,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('calendar-events.index'))
            ->assertOk()
            ->assertSee('Calendar')
            ->assertSee('July 12, 2026 - July 18, 2026')
            ->assertSee('Previous Week')
            ->assertSee('Current Week')
            ->assertSee('Next Week')
            ->assertSee('Sunday')
            ->assertSee('Saturday')
            ->assertSee('Scheduled Monday Visit')
            ->assertSee('All Day Service')
            ->assertSee('All day')
            ->assertSee('Main Office')
            ->assertSee('Assigned: '.$user->name)
            ->assertDontSee('Completed This Week')
            ->assertDontSee('Next Week Scheduled')
            ->assertDontSee('Other Account Scheduled');
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
            'status' => 'active',
        ]);
    }

    protected function createRoute(Account $account, string $name): VendingRoute
    {
        return VendingRoute::create([
            'account_id' => $account->id,
            'route_name' => $name,
            'description' => $name.' description',
            'scheduled_day' => 'Monday',
        ]);
    }

    protected function createLocation(Account $account, VendingRoute $route, string $name): Location
    {
        return Location::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_name' => $name,
            'address' => '123 Calendar Road',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M1M1M1',
            'contact_name' => 'Jamie Admin',
        ]);
    }

    protected function createWarehouse(Account $account, string $name): Warehouse
    {
        return Warehouse::create([
            'account_id' => $account->id,
            'warehouse_name' => $name,
            'address' => '500 Warehouse Lane',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M2M2M2',
        ]);
    }
}
