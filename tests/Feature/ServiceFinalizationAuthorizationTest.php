<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Bin;
use App\Models\DataDictionary;
use App\Models\InventoryLedger;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Product;
use App\Models\RouteLocation;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceFinalizationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_technician_cannot_finalize_services_but_can_still_open_count_and_fill(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Technician Finalization Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_TECHNICIAN);
        $this->seedServiceTypes();

        $route = $this->createRoute($account, 'Technician Route');
        $location = $this->createLocation($account, $route, 'Technician Stop');
        $warehouse = $this->createWarehouse($account, 'Technician Warehouse');
        $machine = $this->createMachine($account, $location, 'Snack');
        $product = $this->createProduct($account, 'Technician Product');
        $bin = $this->createBin($account, $machine, $product, 'A1', 10, 1.50);
        $this->postWarehouseInventory($account, $warehouse, $product, 10);

        $awaitingLocationService = $this->createService($account, $location, $warehouse, $user, [
            'service_type' => Service::TYPE_LOCATION,
            'status' => Service::STATUS_AWAITING,
            'service_date' => '2026-07-22',
        ]);

        $awaitingMaintenanceService = $this->createService($account, $location, $warehouse, $user, [
            'service_type' => Service::TYPE_MAINTENANCE,
            'warehouse_id' => null,
            'status' => Service::STATUS_AWAITING,
            'service_date' => '2026-07-23',
        ]);

        $openLocationService = $this->createService($account, $location, $warehouse, $user, [
            'service_type' => Service::TYPE_LOCATION,
            'status' => Service::STATUS_OPEN,
            'opened_at' => '2026-07-22 08:00:00',
            'service_date' => '2026-07-22',
        ]);

        $completedLocationService = $this->createService($account, $location, $warehouse, $user, [
            'service_type' => Service::TYPE_LOCATION,
            'status' => Service::STATUS_COMPLETED,
            'opened_at' => '2026-07-22 08:00:00',
            'completed_at' => '2026-07-22 10:00:00',
            'service_date' => '2026-07-22',
        ]);

        $openMaintenanceService = $this->createService($account, $location, $warehouse, $user, [
            'service_type' => Service::TYPE_MAINTENANCE,
            'warehouse_id' => null,
            'status' => Service::STATUS_OPEN,
            'opened_at' => '2026-07-22 08:00:00',
            'service_date' => '2026-07-24',
            'notes' => 'Maintenance in progress',
        ]);

        $session = ['current_account_id' => $account->id];

        $this->actingAs($user)->withSession($session)
            ->post(route('services.open', $awaitingLocationService))
            ->assertRedirect(route('services.show', $awaitingLocationService));

        $this->actingAs($user)->withSession($session)
            ->post(route('services.maintenance.open', $awaitingMaintenanceService))
            ->assertRedirect(route('services.show', $awaitingMaintenanceService));

        $this->actingAs($user)->withSession($session)
            ->get(route('services.machines.count', [$openLocationService, $machine]))
            ->assertOk();

        $this->actingAs($user)->withSession($session)
            ->post(route('services.machines.count.store', [$openLocationService, $machine]), [
                'counts' => [
                    $bin->id => [
                        'quantity' => 4,
                        'spoilage' => 1,
                    ],
                ],
            ])
            ->assertRedirect(route('services.show', $openLocationService));

        $this->actingAs($user)->withSession($session)
            ->get(route('services.machines.fill', [$openLocationService, $machine]))
            ->assertOk();

        $this->actingAs($user)->withSession($session)
            ->post(route('services.machines.fill.store', [$openLocationService, $machine]), [
                'quantities' => [
                    $bin->id => 2,
                ],
            ])
            ->assertRedirect(route('services.show', $openLocationService));

        $this->actingAs($user)->withSession($session)
            ->get(route('services.amount-collected.edit', $completedLocationService))
            ->assertForbidden();

        $this->actingAs($user)->withSession($session)
            ->post(route('services.amount-collected.update', $completedLocationService), [
                'amount_collected' => '12.50',
            ])
            ->assertForbidden();

        $this->actingAs($user)->withSession($session)
            ->post(route('services.complete', $openLocationService))
            ->assertForbidden();

        $this->actingAs($user)->withSession($session)
            ->put(route('services.maintenance.close', $openMaintenanceService), [
                'notes' => 'Closed by technician attempt',
            ])
            ->assertForbidden();

        $this->actingAs($user)->withSession($session)
            ->get(route('services.show', $openLocationService))
            ->assertOk()
            ->assertDontSeeText('Complete Service');

        $this->actingAs($user)->withSession($session)
            ->get(route('services.show', $completedLocationService))
            ->assertOk()
            ->assertDontSeeText('Enter Amount Collected');

        $this->actingAs($user)->withSession($session)
            ->get(route('services.index'))
            ->assertOk()
            ->assertDontSeeText('Enter Amount Collected');

        $this->actingAs($user)->withSession($session)
            ->get(route('services.show', $openMaintenanceService))
            ->assertOk()
            ->assertDontSeeText('Close Maintenance Service');

        $this->assertDatabaseHas('tbl_transactions', [
            'service_id' => $openLocationService->id,
            'bin_id' => $bin->id,
            'transaction_type' => Transaction::TYPE_COUNT,
            'quantity' => 4,
            'spoilage' => 1,
        ]);

        $this->assertDatabaseHas('tbl_transactions', [
            'service_id' => $openLocationService->id,
            'bin_id' => $bin->id,
            'transaction_type' => Transaction::TYPE_FILL,
            'quantity' => 2,
        ]);
    }

    public function test_manage_tier_users_can_still_finalize_services_and_view_finalize_controls(): void
    {
        foreach ([AccountUser::ROLE_MANAGER, AccountUser::ROLE_ADMIN, AccountUser::ROLE_OWNER] as $role) {
            $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
            $account = $this->createAccount('Finalize '.strtolower($role).' Account');
            $this->attachUserToAccount($user, $account, $role);
            $this->seedServiceTypes();

            $route = $this->createRoute($account, $role.' Route');
            $location = $this->createLocation($account, $route, $role.' Stop');
            $warehouse = $this->createWarehouse($account, $role.' Warehouse');

            $openLocationService = $this->createService($account, $location, $warehouse, $user, [
                'service_type' => Service::TYPE_LOCATION,
                'status' => Service::STATUS_OPEN,
                'opened_at' => '2026-07-22 08:00:00',
                'service_date' => '2026-07-22',
            ]);

            $completedLocationService = $this->createService($account, $location, $warehouse, $user, [
                'service_type' => Service::TYPE_LOCATION,
                'status' => Service::STATUS_COMPLETED,
                'opened_at' => '2026-07-22 08:00:00',
                'completed_at' => '2026-07-22 10:00:00',
                'service_date' => '2026-07-23',
            ]);

            $openMaintenanceService = $this->createService($account, $location, $warehouse, $user, [
                'service_type' => Service::TYPE_MAINTENANCE,
                'warehouse_id' => null,
                'status' => Service::STATUS_OPEN,
                'opened_at' => '2026-07-22 08:00:00',
                'service_date' => '2026-07-24',
                'notes' => 'Needs close',
            ]);

            $session = ['current_account_id' => $account->id];

            $this->actingAs($user)->withSession($session)
                ->get(route('services.show', $openLocationService))
                ->assertOk()
                ->assertSeeText('Complete Service');

            $this->actingAs($user)->withSession($session)
                ->get(route('services.show', $completedLocationService))
                ->assertOk()
                ->assertSeeText('Enter Amount Collected');

            $this->actingAs($user)->withSession($session)
                ->get(route('services.index'))
                ->assertOk()
                ->assertSeeText('Enter Amount Collected');

            $this->actingAs($user)->withSession($session)
                ->get(route('services.show', $openMaintenanceService))
                ->assertOk()
                ->assertSeeText('Close Maintenance Service');

            $this->actingAs($user)->withSession($session)
                ->post(route('services.complete', $openLocationService))
                ->assertRedirect(route('services.show', $openLocationService));

            $this->assertDatabaseHas('tbl_services', [
                'id' => $openLocationService->id,
                'status' => Service::STATUS_COMPLETED,
            ]);

            $this->actingAs($user)->withSession($session)
                ->get(route('services.amount-collected.edit', $completedLocationService))
                ->assertOk();

            $this->actingAs($user)->withSession($session)
                ->post(route('services.amount-collected.update', $completedLocationService), [
                    'amount_collected' => '25.75',
                ])
                ->assertRedirect(route('services.show', $completedLocationService));

            $this->assertDatabaseHas('tbl_services', [
                'id' => $completedLocationService->id,
                'status' => Service::STATUS_CLOSED,
                'amount_collected' => 25.75,
                'closed_by_user_id' => $user->id,
            ]);

            $this->actingAs($user)->withSession($session)
                ->put(route('services.maintenance.close', $openMaintenanceService), [
                    'notes' => 'Closed by '.$role,
                ])
                ->assertRedirect(route('services.show', $openMaintenanceService));

            $this->assertDatabaseHas('tbl_services', [
                'id' => $openMaintenanceService->id,
                'status' => Service::STATUS_CLOSED,
                'notes' => 'Closed by '.$role,
                'closed_by_user_id' => $user->id,
            ]);
        }
    }

    public function test_viewer_remains_unable_to_finalize_services(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Viewer Finalization Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_VIEWER);
        $this->seedServiceTypes();

        $route = $this->createRoute($account, 'Viewer Finalization Route');
        $location = $this->createLocation($account, $route, 'Viewer Finalization Stop');
        $warehouse = $this->createWarehouse($account, 'Viewer Finalization Warehouse');

        $openLocationService = $this->createService($account, $location, $warehouse, $user, [
            'service_type' => Service::TYPE_LOCATION,
            'status' => Service::STATUS_OPEN,
            'opened_at' => '2026-07-22 08:00:00',
        ]);

        $openMaintenanceService = $this->createService($account, $location, $warehouse, $user, [
            'service_type' => Service::TYPE_MAINTENANCE,
            'warehouse_id' => null,
            'status' => Service::STATUS_OPEN,
            'opened_at' => '2026-07-22 08:00:00',
            'service_date' => '2026-07-23',
        ]);

        $completedLocationService = $this->createService($account, $location, $warehouse, $user, [
            'service_type' => Service::TYPE_LOCATION,
            'status' => Service::STATUS_COMPLETED,
            'opened_at' => '2026-07-22 08:00:00',
            'completed_at' => '2026-07-22 10:00:00',
            'service_date' => '2026-07-24',
        ]);

        $session = ['current_account_id' => $account->id];

        $this->actingAs($user)->withSession($session)
            ->post(route('services.complete', $openLocationService))
            ->assertForbidden();

        $this->actingAs($user)->withSession($session)
            ->get(route('services.amount-collected.edit', $completedLocationService))
            ->assertForbidden();

        $this->actingAs($user)->withSession($session)
            ->post(route('services.amount-collected.update', $completedLocationService), [
                'amount_collected' => '15.00',
            ])
            ->assertForbidden();

        $this->actingAs($user)->withSession($session)
            ->put(route('services.maintenance.close', $openMaintenanceService), [
                'notes' => 'Viewer close attempt',
            ])
            ->assertForbidden();
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

    private function seedServiceTypes(): void
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

    private function createMachine(Account $account, Location $location, string $type): Machine
    {
        return Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => $type,
            'serial_number' => $type.'-'.uniqid(),
            'model' => $type.' Model',
            'status' => Machine::STATUS_ACTIVE,
            'installed_on' => '2026-07-01',
        ]);
    }

    private function createProduct(Account $account, string $name): Product
    {
        return Product::create([
            'account_id' => $account->id,
            'vendor_id' => null,
            'sku' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'product_name' => $name,
            'barcode' => uniqid(),
        ]);
    }

    private function createBin(Account $account, Machine $machine, Product $product, string $code, int $capacity, float $price): Bin
    {
        return Bin::create([
            'account_id' => $account->id,
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'bin_code' => $code,
            'capacity' => $capacity,
            'price' => $price,
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

    private function postWarehouseInventory(Account $account, Warehouse $warehouse, Product $product, int $quantityDelta): InventoryLedger
    {
        return InventoryLedger::create([
            'account_id' => $account->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => InventoryLedger::MOVEMENT_TYPE_ADJUSTMENT,
            'quantity_delta' => $quantityDelta,
            'unit_cost' => 1.0000,
            'total_cost' => round($quantityDelta * 1.0000, 4),
            'source_type' => 'test_adjustment',
            'source_id' => null,
            'movement_at' => now(),
            'notes' => 'Seeded test inventory.',
        ]);
    }
}
