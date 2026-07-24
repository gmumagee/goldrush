<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\CalendarEvent;
use App\Models\Location;
use App\Models\Machine;
use App\Models\RouteLocation;
use App\Models\User;
use App\Models\VendingRoute;
use App\Services\CalendarService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationMachineAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_location_page_add_machine_button_links_to_the_attach_screen(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Location Button Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Button Route');
        $location = $this->createCustomerLocation($account, $route, 'Button Stop');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.show', $location))
            ->assertOk()
            ->assertSee('href="'.route('locations.machines.attach', $location).'"', false)
            ->assertDontSee('href="'.route('machines.create', ['location_id' => $location->id]).'"', false);
    }

    public function test_attach_screen_lists_only_current_account_inventory_machines(): void
    {
        Carbon::setTestNow('2026-07-24 09:00:00');

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Attach Listing Account');
        $otherAccount = $this->createAccount('Foreign Attach Listing');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Attach Route');
        $location = $this->createCustomerLocation($account, $route, 'Attach Stop');
        $inventoryLocation = $account->inventoryLocation()->firstOrFail();
        $otherInventoryLocation = $otherAccount->inventoryLocation()->firstOrFail();

        $inventoryMachine = $this->createMachine($account, $inventoryLocation, 'snack', 'INV-100', 'Inventory Alpha');
        $deployedMachine = $this->createMachine($account, $location, 'combo', 'DEP-200', 'Deployed Beta');
        $foreignMachine = $this->createMachine($otherAccount, $otherInventoryLocation, 'soda', 'FOR-300', 'Foreign Gamma');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.machines.attach', $location))
            ->assertOk()
            ->assertSeeText($inventoryMachine->serial_number)
            ->assertSeeText($inventoryMachine->model)
            ->assertSee('type="date"', false)
            ->assertSee('value="2026-07-24"', false)
            ->assertDontSeeText($deployedMachine->serial_number)
            ->assertDontSeeText($foreignMachine->serial_number);
    }

    public function test_attaching_multiple_machines_updates_each_location_and_install_date_and_creates_one_machine_calendar_event_per_machine(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Attach Success Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Install Route');
        $location = $this->createCustomerLocation($account, $route, 'Install Stop');
        $inventoryLocation = $account->inventoryLocation()->firstOrFail();
        $machineA = $this->createMachine($account, $inventoryLocation, 'snack', 'INST-100', 'Install Model A');
        $machineB = $this->createMachine($account, $inventoryLocation, 'combo', 'INST-200', 'Install Model B');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('locations.machines.attach.store', $location), [
                'machine_ids' => [$machineA->id, $machineB->id],
                'installation_date' => '2026-07-30',
            ])
            ->assertRedirect(route('locations.show', $location));

        $this->assertSame($location->id, $machineA->fresh()->location_id);
        $this->assertSame($location->id, $machineB->fresh()->location_id);
        $this->assertSame('2026-07-30', $machineA->fresh()->installed_on?->format('Y-m-d'));
        $this->assertSame('2026-07-30', $machineB->fresh()->installed_on?->format('Y-m-d'));

        $events = CalendarEvent::query()
            ->where('account_id', $account->id)
            ->where('source_type', CalendarEvent::SOURCE_TYPE_MACHINE)
            ->orderBy('source_id')
            ->get();

        $this->assertCount(2, $events);
        $this->assertSame([$machineA->id, $machineB->id], $events->pluck('source_id')->all());

        foreach ($events as $event) {
            $this->assertSame(CalendarEvent::EVENT_TYPE_MACHINE_INSTALLATION, $event->event_type);
            $this->assertSame('2026-07-30 00:00:00', $event->start_at?->format('Y-m-d H:i:s'));
            $this->assertSame($location->id, $event->location_id);
            $this->assertSame('machines.show', $event->sourceRouteName());
            $this->assertSame('View Machine', $event->sourceLinkLabel());
            $this->assertSame($user->id, $event->assigned_user_id);
        }
    }

    public function test_submitting_with_no_machines_selected_fails_validation(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Attach Empty Selection Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Selection Route');
        $location = $this->createCustomerLocation($account, $route, 'Selection Stop');
        $inventoryLocation = $account->inventoryLocation()->firstOrFail();
        $this->createMachine($account, $inventoryLocation, 'snack', 'SEL-100', 'Selection Model');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('locations.machines.attach', $location))
            ->post(route('locations.machines.attach.store', $location), [
                'installation_date' => '2026-07-31',
            ])
            ->assertRedirect(route('locations.machines.attach', $location))
            ->assertSessionHasErrors('machine_ids');
    }

    public function test_submitting_a_deployed_machine_or_foreign_machine_rejects_the_whole_request_and_attaches_nothing(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Attach Guard Account');
        $otherAccount = $this->createAccount('Attach Guard Foreign');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Guard Route');
        $location = $this->createCustomerLocation($account, $route, 'Guard Stop');
        $otherLocation = $this->createCustomerLocation($account, $route, 'Other Guard Stop');
        $inventoryLocation = $account->inventoryLocation()->firstOrFail();
        $otherInventoryLocation = $otherAccount->inventoryLocation()->firstOrFail();

        $inventoryMachine = $this->createMachine($account, $inventoryLocation, 'snack', 'GOOD-100', 'Good Model');
        $deployedMachine = $this->createMachine($account, $otherLocation, 'combo', 'BAD-200', 'Bad Model');
        $foreignMachine = $this->createMachine($otherAccount, $otherInventoryLocation, 'soda', 'BAD-300', 'Foreign Model');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('locations.machines.attach', $location))
            ->post(route('locations.machines.attach.store', $location), [
                'machine_ids' => [$inventoryMachine->id, $deployedMachine->id],
                'installation_date' => '2026-07-31',
            ])
            ->assertRedirect(route('locations.machines.attach', $location))
            ->assertSessionHasErrors('machine_ids');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('locations.machines.attach', $location))
            ->post(route('locations.machines.attach.store', $location), [
                'machine_ids' => [$inventoryMachine->id, $foreignMachine->id],
                'installation_date' => '2026-07-31',
            ])
            ->assertRedirect(route('locations.machines.attach', $location))
            ->assertSessionHasErrors('machine_ids');

        $this->assertSame($inventoryLocation->id, $inventoryMachine->fresh()->location_id);
        $this->assertNull($inventoryMachine->fresh()->installed_on);
        $this->assertDatabaseCount('tbl_calendar_events', 0);
    }

    public function test_attaching_to_the_inventory_location_is_rejected(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Inventory Target Guard');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $inventoryLocation = $account->inventoryLocation()->firstOrFail();
        $machine = $this->createMachine($account, $inventoryLocation, 'snack', 'INV-SELF', 'Inventory Self');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('locations.show', $inventoryLocation))
            ->post(route('locations.machines.attach.store', $inventoryLocation), [
                'machine_ids' => [$machine->id],
                'installation_date' => '2026-07-31',
            ])
            ->assertRedirect(route('locations.show', $inventoryLocation))
            ->assertSessionHasErrors('location');
    }

    public function test_attach_screen_shows_empty_state_with_link_to_machine_create_when_inventory_is_empty(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Empty Inventory Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Empty Route');
        $location = $this->createCustomerLocation($account, $route, 'Empty Stop');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.machines.attach', $location))
            ->assertOk()
            ->assertSeeText('No machines are currently in Inventory for this account.')
            ->assertSee('href="'.route('machines.create').'"', false);
    }

    public function test_machine_move_rolls_back_if_calendar_event_creation_fails(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Rollback Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Rollback Route');
        $location = $this->createCustomerLocation($account, $route, 'Rollback Stop');
        $inventoryLocation = $account->inventoryLocation()->firstOrFail();
        $machineA = $this->createMachine($account, $inventoryLocation, 'snack', 'ROLL-100', 'Rollback Model A');
        $machineB = $this->createMachine($account, $inventoryLocation, 'combo', 'ROLL-200', 'Rollback Model B');

        $this->mock(CalendarService::class, function ($mock): void {
            $mock->shouldReceive('createMachineInstallationEvent')
                ->twice()
                ->andReturnUsing(function () {
                    static $calls = 0;
                    $calls++;

                    if ($calls === 2) {
                        throw new \RuntimeException('Calendar event creation failed.');
                    }

                    return new CalendarEvent();
                });
        });

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('locations.machines.attach.store', $location), [
                'machine_ids' => [$machineA->id, $machineB->id],
                'installation_date' => '2026-07-31',
            ]);

        $response->assertStatus(500);
        $this->assertSame($inventoryLocation->id, $machineA->fresh()->location_id);
        $this->assertSame($inventoryLocation->id, $machineB->fresh()->location_id);
        $this->assertNull($machineA->fresh()->installed_on);
        $this->assertNull($machineB->fresh()->installed_on);
        $this->assertDatabaseCount('tbl_calendar_events', 0);
    }

    protected function createAccount(string $name): Account
    {
        return Account::create([
            'account_name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'status' => Account::STATUS_ACTIVE,
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

    protected function createRoute(Account $account, string $name): VendingRoute
    {
        return VendingRoute::create([
            'account_id' => $account->id,
            'route_name' => $name,
            'description' => $name.' description',
        ]);
    }

    protected function createCustomerLocation(Account $account, VendingRoute $route, string $name): Location
    {
        $location = Location::create([
            'account_id' => $account->id,
            'location_name' => $name,
            'address' => '123 Route Street',
            'city' => 'New York',
            'state' => 'NY',
            'zip_code' => '10001',
            'is_inventory' => null,
        ]);

        RouteLocation::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_id' => $location->id,
            'stop_order' => (int) RouteLocation::query()
                ->where('account_id', $account->id)
                ->where('route_id', $route->id)
                ->max('stop_order') + 1,
            'is_primary' => true,
        ]);

        return $location;
    }

    protected function createMachine(Account $account, Location $location, string $type, string $serialNumber, string $model): Machine
    {
        return Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => $type,
            'serial_number' => $serialNumber,
            'model' => $model,
            'status' => 'active',
            'installed_on' => null,
        ]);
    }
}
