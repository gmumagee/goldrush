<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Location;
use App\Models\Machine;
use App\Models\RouteLocation;
use App\Models\Service;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryLocationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_account_automatically_creates_exactly_one_inventory_location(): void
    {
        $account = $this->createAccount('Inventory Account');

        $inventoryLocations = Location::query()
            ->where('account_id', $account->id)
            ->inventory()
            ->get();

        $this->assertCount(1, $inventoryLocations);
        $this->assertSame(Location::INVENTORY_LOCATION_NAME, $inventoryLocations->first()->location_name);
        $this->assertTrue($inventoryLocations->first()->isInventory());
        $this->assertSame(0, $inventoryLocations->first()->routeLocations()->count());
    }

    public function test_inventory_backfill_command_creates_missing_locations_and_is_idempotent(): void
    {
        $account = $this->createAccount('Backfill Account');
        $inventoryLocation = $account->inventoryLocation()->firstOrFail();

        DB::table('tbl_locations')->where('id', $inventoryLocation->id)->delete();

        $this->assertSame(0, Location::query()->where('account_id', $account->id)->inventory()->count());

        $this->artisan('locations:init-inventory', ['--account_id' => $account->id])
            ->expectsOutput('Accounts processed: 1')
            ->expectsOutput('Inventory locations created: 1')
            ->expectsOutput('Accounts already configured: 0')
            ->assertExitCode(0);

        $this->assertSame(1, Location::query()->where('account_id', $account->id)->inventory()->count());

        $this->artisan('locations:init-inventory', ['--account_id' => $account->id])
            ->expectsOutput('Accounts processed: 1')
            ->expectsOutput('Inventory locations created: 0')
            ->expectsOutput('Accounts already configured: 1')
            ->assertExitCode(0);

        $this->assertSame(1, Location::query()->where('account_id', $account->id)->inventory()->count());
    }

    public function test_only_one_inventory_location_can_exist_per_account(): void
    {
        $account = $this->createAccount('Unique Inventory Account');

        $this->expectException(QueryException::class);

        Location::create([
            'account_id' => $account->id,
            'location_name' => 'Second Inventory',
            'address' => null,
            'city' => null,
            'state' => null,
            'zip_code' => null,
            'is_inventory' => true,
        ]);
    }

    public function test_machine_can_be_assigned_to_inventory_and_reassigned_to_a_customer_location_and_filtered_in_the_index(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Machine Inventory Workflow');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Route A');
        $customerLocation = $this->createCustomerLocation($account, $route, 'Customer Stop');
        $inventoryLocation = $account->inventoryLocation()->firstOrFail();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('machines.create'))
            ->assertOk()
            ->assertViewHas('locations', fn ($locations) => $locations->contains('id', $inventoryLocation->id));

        $machine = Machine::create([
            'account_id' => $account->id,
            'location_id' => $inventoryLocation->id,
            'type' => 'snack',
            'serial_number' => 'INV-001',
            'model' => 'Inventory Model',
            'status' => Machine::STATUS_ACTIVE,
            'installed_on' => null,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('machines.edit', $machine))
            ->assertOk()
            ->assertViewHas('locations', fn ($locations) => $locations->contains('id', $inventoryLocation->id) && $locations->contains('id', $customerLocation->id));

        $this->assertSame($inventoryLocation->id, $machine->location_id);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('machines.index', ['location_scope' => 'in_inventory']))
            ->assertOk()
            ->assertSeeText('Inventory')
            ->assertSeeText('INV-001');

        $machine->update(['location_id' => $customerLocation->id]);

        $this->assertSame($customerLocation->id, $machine->fresh()->location_id);
    }

    public function test_inventory_location_is_excluded_from_locations_index_for_every_browseable_role_and_search(): void
    {
        foreach ([
            AccountUser::ROLE_OWNER,
            AccountUser::ROLE_ADMIN,
            AccountUser::ROLE_MANAGER,
            AccountUser::ROLE_VIEWER,
        ] as $role) {
            $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
            $account = $this->createAccount('Locations Index '.strtolower($role).' Account');
            $this->attachUserToAccount($user, $account, $role);

            $route = $this->createRoute($account, $role.' Route');
            $customerLocation = $this->createCustomerLocation($account, $route, $role.' Customer Stop');
            $inventoryLocation = $account->inventoryLocation()->firstOrFail();

            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('locations.index'))
                ->assertOk()
                ->assertSeeText($customerLocation->location_name)
                ->assertDontSee('href="'.route('locations.show', $inventoryLocation).'"', false)
                ->assertDontSeeText('Inventory only');

            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('locations.index', ['search' => $inventoryLocation->location_name]))
                ->assertOk()
                ->assertDontSee('href="'.route('locations.show', $inventoryLocation).'"', false)
                ->assertSeeText('No locations found for this account.');

            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('locations.index', ['search' => $customerLocation->location_name]))
                ->assertOk()
                ->assertSeeText($customerLocation->location_name)
                ->assertDontSee('href="'.route('locations.show', $inventoryLocation).'"', false);
        }
    }

    public function test_inventory_locations_are_hidden_from_delete_and_route_assignment_ui_and_blocked_from_deletion_server_side(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Protected Inventory Location');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);
        $inventoryLocation = $account->inventoryLocation()->firstOrFail();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.show', $inventoryLocation))
            ->assertOk()
            ->assertDontSeeText('Delete Location');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.edit', $inventoryLocation))
            ->assertOk()
            ->assertDontSeeText('Primary Route')
            ->assertSeeText('cannot be assigned to routes');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('locations.destroy', $inventoryLocation))
            ->assertForbidden();

        $this->assertDatabaseHas('tbl_locations', [
            'id' => $inventoryLocation->id,
            'account_id' => $account->id,
        ]);
    }

    public function test_inventory_locations_are_excluded_from_location_service_creation_and_rejected_server_side(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Service Inventory Guard');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Service Route');
        $customerLocation = $this->createCustomerLocation($account, $route, 'Customer Service Stop');
        $inventoryLocation = $account->inventoryLocation()->firstOrFail();
        $warehouse = $this->createWarehouse($account, 'Service Warehouse');

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('services.create'))
            ->assertOk()
            ->assertSeeText($customerLocation->location_name);

        $response->assertViewHas('locations', fn ($locations) => $locations->contains('id', $customerLocation->id) && ! $locations->contains('id', $inventoryLocation->id));

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('services.create'))
            ->post(route('services.store'), [
                'location_id' => $inventoryLocation->id,
                'warehouse_id' => $warehouse->id,
                'service_date' => '2026-07-24',
                'service_type' => Service::TYPE_LOCATION,
                'user_id' => $user->id,
                'notes' => 'Should fail',
            ])
            ->assertRedirect(route('services.create'))
            ->assertSessionHasErrors('location_id');
    }

    public function test_inventory_locations_cannot_be_added_to_routes(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Route Inventory Guard');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Route Guard');
        $customerLocation = $this->createCustomerLocation($account, $route, 'Customer Route Stop');
        $inventoryLocation = $account->inventoryLocation()->firstOrFail();
        $secondRoute = $this->createRoute($account, 'Second Route');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('routes.show', $secondRoute))
            ->assertOk()
            ->assertSeeText($customerLocation->location_name)
            ->assertDontSee('value="'.$inventoryLocation->id.'"', false);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('routes.show', $secondRoute))
            ->post(route('routes.locations.store', $secondRoute), [
                'location_id' => $inventoryLocation->id,
            ])
            ->assertRedirect(route('routes.show', $secondRoute))
            ->assertSessionHasErrors('location_id');
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
            'address' => '123 Service Road',
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

    protected function createWarehouse(Account $account, string $name): Warehouse
    {
        return Warehouse::create([
            'account_id' => $account->id,
            'warehouse_name' => $name,
            'address' => '100 Warehouse Way',
            'city' => 'New York',
            'state' => 'NY',
            'zip_code' => '10001',
        ]);
    }
}
