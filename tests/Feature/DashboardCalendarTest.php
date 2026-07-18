<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\CalendarEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-17 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_shows_the_current_sunday_through_saturday_week_with_scheduled_events_only(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Dashboard Account');
        $otherAccount = $this->createAccount('Other Dashboard Account');
        $this->attachUserToAccount($user, $account, 'owner');

        CalendarEvent::create([
            'account_id' => $account->id,
            'event_type' => 'Service',
            'title' => 'Service: Main Office',
            'start_at' => '2026-07-13 09:00:00',
            'status' => CalendarEvent::STATUS_SCHEDULED,
        ]);

        CalendarEvent::create([
            'account_id' => $account->id,
            'event_type' => 'Maintenance',
            'title' => 'Maintenance Check',
            'start_at' => '2026-07-14 10:00:00',
            'status' => CalendarEvent::STATUS_SCHEDULED,
        ]);

        CalendarEvent::create([
            'account_id' => $account->id,
            'event_type' => 'Purchase',
            'title' => 'Vendor Purchase',
            'start_at' => '2026-07-16 11:00:00',
            'status' => CalendarEvent::STATUS_SCHEDULED,
        ]);

        CalendarEvent::create([
            'account_id' => $account->id,
            'event_type' => 'General',
            'title' => 'General Visit',
            'start_at' => '2026-07-18 15:00:00',
            'all_day' => true,
            'status' => CalendarEvent::STATUS_SCHEDULED,
        ]);

        CalendarEvent::create([
            'account_id' => $account->id,
            'event_type' => 'Service',
            'title' => 'Completed Event',
            'start_at' => '2026-07-15 12:00:00',
            'status' => CalendarEvent::STATUS_COMPLETED,
        ]);

        CalendarEvent::create([
            'account_id' => $account->id,
            'event_type' => 'Service',
            'title' => 'Next Week Event',
            'start_at' => '2026-07-20 09:00:00',
            'status' => CalendarEvent::STATUS_SCHEDULED,
        ]);

        CalendarEvent::create([
            'account_id' => $otherAccount->id,
            'event_type' => 'Service',
            'title' => 'Other Account Event',
            'start_at' => '2026-07-13 09:00:00',
            'status' => CalendarEvent::STATUS_SCHEDULED,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Weekly Calendar')
            ->assertSee('July 12, 2026 - July 18, 2026')
            ->assertSee('Sunday')
            ->assertSee('Saturday')
            ->assertSee('Service: Main Office')
            ->assertSee('Maintenance Check')
            ->assertSee('Vendor Purchase')
            ->assertSee('General Visit')
            ->assertSee('calendar-event--service')
            ->assertSee('calendar-event--maintenance')
            ->assertSee('calendar-event--purchase')
            ->assertSee('calendar-event--default')
            ->assertSee(route('dashboard', ['date' => '2026-07-05']), false)
            ->assertSee(route('dashboard', ['date' => '2026-07-17']), false)
            ->assertSee(route('dashboard', ['date' => '2026-07-19']), false)
            ->assertDontSee('Upcoming Events')
            ->assertDontSee('Reminders')
            ->assertDontSee('Completed Event')
            ->assertDontSee('Next Week Event')
            ->assertDontSee('Other Account Event')
            ->assertDontSee('All day')
            ->assertDontSee('Assigned User')
            ->assertDontSee('Location');
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
}
