<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Location;
use App\Models\RouteLocation;
use App\Models\Service;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ServiceMachineIdNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_work_without_a_legacy_machine_id_column(): void
    {
        $this->assertFalse(Schema::hasColumn('tbl_services', 'machine_id'));

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Service Normalization Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Normalization Route');
        $location = $this->createLocation($account, $route, 'Campus Center');
        $warehouse = $this->createWarehouse($account, 'Main Warehouse');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.store'), [
                'location_id' => $location->id,
                'warehouse_id' => $warehouse->id,
                'service_date' => '2026-07-21',
                'service_type' => Service::TYPE_LOCATION,
                'notes' => 'Created without service.machine_id',
            ])
            ->assertRedirect();

        $service = Service::query()->where('account_id', $account->id)->firstOrFail();

        $this->assertSame($location->id, $service->location_id);
        $this->assertNull($service->getAttribute('machine_id'));

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('services.show', $service))
            ->assertOk()
            ->assertSeeText('Campus Center')
            ->assertSeeText('Normalization Route');
    }

    protected function createAccount(string $name): Account
    {
        return Account::create([
            'account_name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'status' => 'active',
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

    protected function createLocation(Account $account, VendingRoute $route, string $name): Location
    {
        $location = Location::create([
            'account_id' => $account->id,
            'location_name' => $name,
            'address' => '123 Service Road',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M1M1M1',
        ]);

        RouteLocation::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_id' => $location->id,
            'stop_order' => 1,
            'is_primary' => true,
        ]);

        return $location;
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
