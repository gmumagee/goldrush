<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Bin;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendingRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MachineInventoryViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_machine_detail_shows_current_inventory_from_latest_count_and_later_transactions(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Alpha Vending');
        $this->attachUserToAccount($user, $account, 'owner');

        $route = $this->createRoute($account, 'North Route');
        $location = $this->createLocation($account, $route, 'Campus Center');
        $machine = $this->createMachine($account, $location, 'Snack');
        $service = $this->createService($account, $location);

        $cola = $this->createProduct($account, 'Coca-Cola 12 oz');
        $snickers = $this->createProduct($account, 'Snickers 1.86 oz');

        $binA1 = $this->createBin($account, $machine, $cola, 'A1', 30, 1.75);
        $binA2 = $this->createBin($account, $machine, $snickers, 'A2', 10, 1.50);

        $this->createTransaction($account, $service, $binA1, 'count', 2, '2026-07-10 09:00:00');
        $this->createTransaction($account, $service, $binA1, 'count', 4, '2026-07-10 10:00:00');
        $this->createTransaction($account, $service, $binA1, 'fill', 8, '2026-07-10 11:00:00');

        $this->createTransaction($account, $service, $binA2, 'fill', 8, '2026-07-10 09:30:00');
        $this->createTransaction($account, $service, $binA2, 'waste', 2, '2026-07-10 10:30:00');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('machines.show', $machine->id))
            ->assertOk()
            ->assertSeeText('Current Inventory')
            ->assertSeeTextInOrder(['A1', 'Coca-Cola 12 oz', '30', '1.75', '12'])
            ->assertSeeTextInOrder(['A2', 'Snickers 1.86 oz', '10', '1.50', '6'])
            ->assertDontSeeText('14');
    }

    public function test_machine_detail_inventory_ignores_transactions_from_other_accounts(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $accountA = $this->createAccount('Account A');
        $accountB = $this->createAccount('Account B');
        $this->attachUserToAccount($user, $accountA, 'owner');

        $routeA = $this->createRoute($accountA, 'North Route');
        $locationA = $this->createLocation($accountA, $routeA, 'Campus Center');
        $machineA = $this->createMachine($accountA, $locationA, 'Snack');
        $serviceA = $this->createService($accountA, $locationA);

        $routeB = $this->createRoute($accountB, 'South Route');
        $locationB = $this->createLocation($accountB, $routeB, 'Remote Stop');
        $serviceB = $this->createService($accountB, $locationB);

        $productA = $this->createProduct($accountA, 'Trail Mix');
        $binA1 = $this->createBin($accountA, $machineA, $productA, 'A1', 20, 2.25);

        $this->createTransaction($accountA, $serviceA, $binA1, 'count', 4, '2026-07-10 09:00:00');
        $this->createTransaction($accountA, $serviceA, $binA1, 'fill', 3, '2026-07-10 10:00:00');

        Transaction::create([
            'account_id' => $accountB->id,
            'service_id' => $serviceB->id,
            'machine_id' => $binA1->machine_id,
            'bin_id' => $binA1->id,
            'product_id' => $productA->id,
            'transaction_type' => 'fill',
            'quantity' => 99,
            'transaction_at' => '2026-07-10 11:00:00',
            'price' => $binA1->price,
            'unit_cost' => null,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $accountA->id])
            ->get(route('machines.show', $machineA->id))
            ->assertOk()
            ->assertSeeTextInOrder(['A1', 'Trail Mix', '20', '2.25', '7'])
            ->assertDontSeeText('106');
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
        ]);
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

    protected function createMachine(Account $account, Location $location, string $type): Machine
    {
        return Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => $type,
            'serial_number' => $type.'-'.uniqid(),
            'model' => $type.' Model',
            'status' => 'active',
            'installed_on' => '2026-07-01',
        ]);
    }

    protected function createService(Account $account, Location $location): Service
    {
        return Service::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'user_id' => null,
            'service_type' => Service::TYPE_LOCATION_SERVICE,
            'service_date' => '2026-07-10',
            'opened_at' => '2026-07-10 08:00:00',
            'closed_at' => null,
            'status' => Service::STATUS_SERVICE_OPEN,
        ]);
    }

    protected function createProduct(Account $account, string $name): Product
    {
        return Product::create([
            'account_id' => $account->id,
            'vendor_id' => null,
            'sku' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'product_name' => $name,
            'barcode' => uniqid(),
        ]);
    }

    protected function createBin(Account $account, Machine $machine, Product $product, string $code, int $capacity, float $price): Bin
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

    protected function createTransaction(
        Account $account,
        Service $service,
        Bin $bin,
        string $type,
        int $quantity,
        string $transactionAt
    ): Transaction {
        return Transaction::create([
            'account_id' => $account->id,
            'service_id' => $service->id,
            'machine_id' => $bin->machine_id,
            'bin_id' => $bin->id,
            'product_id' => $bin->product_id,
            'transaction_type' => $type,
            'quantity' => $quantity,
            'transaction_at' => $transactionAt,
            'price' => $bin->price,
            'unit_cost' => null,
        ]);
    }
}
