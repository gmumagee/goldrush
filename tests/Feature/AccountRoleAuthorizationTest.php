<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\Machine;
use App\Models\RouteLocation;
use App\Models\Service;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_locations_and_machines(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Admin Authorization Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_ADMIN);

        $route = $this->createRoute($account, 'North Route');
        $machineLocation = $this->createLocation($account, $route, 'Machine Home');
        $deletableLocation = $this->createLocation($account, $route, 'Delete Me');
        $machine = $this->createMachine($account, $machineLocation, 'snack');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('machines.destroy', $machine))
            ->assertRedirect(route('machines.index'));

        $this->assertDatabaseMissing('tbl_machines', [
            'id' => $machine->id,
        ]);

        $deletableLocation->routeLocations()->delete();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('locations.destroy', $deletableLocation))
            ->assertRedirect(route('locations.index'));
        $this->assertDatabaseMissing('tbl_locations', [
            'id' => $deletableLocation->id,
        ]);
    }

    public function test_standard_user_cannot_delete_locations_but_can_update_them(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Manager Authorization Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_MANAGER);

        $route = $this->createRoute($account, 'Manager Route');
        $location = $this->createLocation($account, $route, 'Original Name');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('locations.destroy', $location))
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->followingRedirects()
            ->put(route('locations.update', $location), [
                'route_id' => null,
                'location_name' => 'Updated Name',
                'address' => '123 Main St',
                'city' => 'Toronto',
                'state' => 'ON',
                'zip_code' => 'A1A1A1',
            ])
            ->assertOk()
            ->assertSee('Location updated successfully.');

        $this->assertDatabaseHas('tbl_locations', [
            'id' => $location->id,
            'location_name' => 'Updated Name',
        ]);
    }

    public function test_technician_is_redirected_to_services_and_can_only_update_service_records(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Technician Authorization Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_TECHNICIAN);
        $this->seedServiceTypes($account);

        $route = $this->createRoute($account, 'Tech Route');
        $location = $this->createLocation($account, $route, 'Tech Stop');
        $warehouse = $this->createWarehouse($account, 'Tech Warehouse');
        $service = $this->createService($account, $location, $warehouse, $user, [
            'service_type' => Service::TYPE_MAINTENANCE,
            'notes' => 'Before update',
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('dashboard'))
            ->assertRedirect(route('services.index'));

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('services.create'))
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('locations.store'), [
                'location_name' => 'Forbidden Stop',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->followingRedirects()
            ->put(route('services.update', $service), [
                'location_id' => $location->id,
                'warehouse_id' => null,
                'service_date' => '2026-07-20',
                'service_type' => Service::TYPE_MAINTENANCE,
                'notes' => 'Technician updated note',
            ])
            ->assertOk()
            ->assertSee('Service updated successfully.');

        $this->assertDatabaseHas('tbl_services', [
            'id' => $service->id,
            'notes' => 'Technician updated note',
        ]);
    }

    public function test_viewer_gets_forbidden_on_writes(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Viewer Authorization Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_VIEWER);
        $this->seedServiceTypes($account);

        $route = $this->createRoute($account, 'Viewer Route');
        $location = $this->createLocation($account, $route, 'Viewer Stop');
        $warehouse = $this->createWarehouse($account, 'Viewer Warehouse');
        $service = $this->createService($account, $location, $warehouse, $user, [
            'service_type' => Service::TYPE_MAINTENANCE,
        ]);

        $session = ['current_account_id' => $account->id];

        $this->actingAs($user)->withSession($session)->post(route('locations.store'), [
            'location_name' => 'Nope',
        ])->assertForbidden();

        $this->actingAs($user)->withSession($session)->put(route('locations.update', $location), [
            'route_id' => null,
            'location_name' => 'Blocked Update',
            'address' => null,
            'city' => null,
            'state' => null,
            'zip_code' => null,
        ])->assertForbidden();

        $this->actingAs($user)->withSession($session)->delete(route('locations.destroy', $location))
            ->assertForbidden();

        $this->actingAs($user)->withSession($session)->put(route('services.update', $service), [
            'location_id' => $location->id,
            'warehouse_id' => null,
            'service_date' => '2026-07-20',
            'service_type' => Service::TYPE_MAINTENANCE,
            'notes' => 'Blocked viewer update',
        ])->assertForbidden();
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

    protected function createRoute(Account $account, string $name): VendingRoute
    {
        return VendingRoute::create([
            'account_id' => $account->id,
            'route_name' => $name,
        ]);
    }

    protected function createLocation(Account $account, VendingRoute $route, string $name): Location
    {
        $location = Location::create([
            'account_id' => $account->id,
            'location_name' => $name,
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

    protected function createMachine(Account $account, Location $location, string $type): Machine
    {
        return Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => $type,
            'status' => Machine::STATUS_ACTIVE,
        ]);
    }

    protected function createWarehouse(Account $account, string $name): Warehouse
    {
        return Warehouse::create([
            'account_id' => $account->id,
            'warehouse_name' => $name,
        ]);
    }

    protected function createService(
        Account $account,
        Location $location,
        Warehouse $warehouse,
        User $user,
        array $attributes = [],
    ): Service {
        return Service::create(array_merge([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'service_type' => Service::TYPE_LOCATION,
            'service_date' => '2026-07-19',
            'status' => Service::STATUS_AWAITING,
        ], $attributes));
    }

    protected function seedServiceTypes(Account $account): void
    {
        foreach ([
            [Service::TYPE_LOCATION, 'Location Service'],
            [Service::TYPE_MAINTENANCE, 'Maintenance Service'],
        ] as [$value, $label]) {
            DataDictionary::create([
                'account_id' => null,
                'name' => DataDictionary::GROUP_SERVICE_TYPE,
                'value' => $value,
                'label' => $label,
                'sort_order' => 10,
                'is_active' => true,
            ]);
        }
    }
}
