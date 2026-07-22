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
use Illuminate\Support\Collection;
use Tests\TestCase;

class ServiceIndexScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_technician_sees_only_their_assigned_services_in_all_services(): void
    {
        $technician = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $coworker = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Technician Service Index Account');

        $this->attachUserToAccount($technician, $account, AccountUser::ROLE_TECHNICIAN);
        $this->attachUserToAccount($coworker, $account, AccountUser::ROLE_MANAGER);

        $route = $this->createRoute($account, 'Technician Index Route');
        $location = $this->createLocation($account, $route, 'Technician Index Stop');
        $warehouse = $this->createWarehouse($account, 'Technician Index Warehouse');

        $techOpenService = $this->createService($account, $location, $warehouse, $technician, [
            'status' => Service::STATUS_OPEN,
            'service_date' => '2026-07-21',
        ]);

        $techClosedService = $this->createService($account, $location, $warehouse, $technician, [
            'status' => Service::STATUS_CLOSED,
            'service_date' => '2026-07-20',
            'closed_at' => '2026-07-20 09:00:00',
            'amount_collected' => 10.00,
        ]);

        $otherPendingService = $this->createService($account, $location, $warehouse, $coworker, [
            'status' => Service::STATUS_AWAITING,
            'service_date' => '2026-07-22',
        ]);

        $otherAwaitingMoneyService = $this->createService($account, $location, $warehouse, $coworker, [
            'status' => Service::STATUS_COMPLETED,
            'service_date' => '2026-07-19',
            'completed_at' => '2026-07-19 10:00:00',
            'amount_collected' => null,
        ]);

        $response = $this->actingAs($technician)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('services.index'));

        $response->assertOk()
            ->assertViewHas('allServicesCount', 2)
            ->assertViewHas('pendingServicesCount', 1)
            ->assertViewHas('completedServicesCount', 1);

        $serviceIds = $this->serviceIdsFromGroupedViewData($response->viewData('allServicesByLocation'));

        $this->assertSame([$techOpenService->id, $techClosedService->id], $serviceIds);
        $this->assertNotContains($otherPendingService->id, $serviceIds);
        $this->assertNotContains($otherAwaitingMoneyService->id, $serviceIds);
    }

    public function test_non_technician_roles_still_see_all_account_services_in_all_services(): void
    {
        foreach ([
            AccountUser::ROLE_ADMIN,
            AccountUser::ROLE_OWNER,
            AccountUser::ROLE_MANAGER,
            AccountUser::ROLE_VIEWER,
        ] as $role) {
            $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
            $assignedUser = User::factory()->create(['status' => User::STATUS_ACTIVE]);
            $account = $this->createAccount($role.' Service Index Account');

            $this->attachUserToAccount($user, $account, $role);
            $this->attachUserToAccount($assignedUser, $account, AccountUser::ROLE_TECHNICIAN);

            $route = $this->createRoute($account, $role.' Index Route');
            $location = $this->createLocation($account, $route, $role.' Index Stop');
            $warehouse = $this->createWarehouse($account, $role.' Index Warehouse');

            $serviceOne = $this->createService($account, $location, $warehouse, $user, [
                'status' => Service::STATUS_AWAITING,
                'service_date' => '2026-07-22',
            ]);

            $serviceTwo = $this->createService($account, $location, $warehouse, $assignedUser, [
                'status' => Service::STATUS_OPEN,
                'service_date' => '2026-07-21',
            ]);

            $serviceThree = $this->createService($account, $location, $warehouse, $assignedUser, [
                'status' => Service::STATUS_CLOSED,
                'service_date' => '2026-07-20',
                'closed_at' => '2026-07-20 09:00:00',
                'amount_collected' => 12.00,
            ]);

            $response = $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('services.index'));

            $response->assertOk()
                ->assertViewHas('allServicesCount', 3);

            $this->assertSame(
                [$serviceOne->id, $serviceTwo->id, $serviceThree->id],
                $this->serviceIdsFromGroupedViewData($response->viewData('allServicesByLocation'))
            );
        }
    }

    public function test_technician_with_no_assigned_services_gets_empty_all_services_without_affecting_other_sections(): void
    {
        $technician = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $assignedUser = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Empty Technician Service Index Account');

        $this->attachUserToAccount($technician, $account, AccountUser::ROLE_TECHNICIAN);
        $this->attachUserToAccount($assignedUser, $account, AccountUser::ROLE_MANAGER);

        $route = $this->createRoute($account, 'Empty Technician Route');
        $location = $this->createLocation($account, $route, 'Empty Technician Stop');
        $warehouse = $this->createWarehouse($account, 'Empty Technician Warehouse');

        $this->createService($account, $location, $warehouse, $assignedUser, [
            'status' => Service::STATUS_AWAITING,
            'service_date' => '2026-07-22',
        ]);

        $this->createService($account, $location, $warehouse, $assignedUser, [
            'status' => Service::STATUS_COMPLETED,
            'service_date' => '2026-07-21',
            'completed_at' => '2026-07-21 10:00:00',
            'amount_collected' => null,
        ]);

        $response = $this->actingAs($technician)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('services.index'));

        $response->assertOk()
            ->assertViewHas('allServicesCount', 0)
            ->assertViewHas('pendingServicesCount', 1)
            ->assertViewHas('completedServicesCount', 1);

        $this->assertTrue($response->viewData('allServicesByLocation')->isEmpty());
    }

    private function createAccount(string $name): Account
    {
        return Account::create([
            'account_name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'status' => 'active',
            'billing_email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
        ]);
    }

    private function attachUserToAccount(User $user, Account $account, string $role): void
    {
        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => AccountUser::STATUS_ACTIVE,
        ]);
    }

    private function createRoute(Account $account, string $name): VendingRoute
    {
        return VendingRoute::create([
            'account_id' => $account->id,
            'route_name' => $name,
        ]);
    }

    private function createLocation(Account $account, VendingRoute $route, string $name): Location
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

    private function createWarehouse(Account $account, string $name): Warehouse
    {
        return Warehouse::create([
            'account_id' => $account->id,
            'warehouse_name' => $name,
        ]);
    }

    private function createService(Account $account, Location $location, Warehouse $warehouse, User $user, array $attributes = []): Service
    {
        return Service::create(array_merge([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'service_type' => Service::TYPE_LOCATION,
            'service_date' => '2026-07-19',
            'status' => Service::STATUS_AWAITING,
            'notes' => null,
        ], $attributes));
    }

    private function serviceIdsFromGroupedViewData(Collection $servicesByLocation): array
    {
        $serviceIds = $servicesByLocation
            ->flatMap(fn (Collection $services) => $services->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        sort($serviceIds);

        return $serviceIds;
    }
}
