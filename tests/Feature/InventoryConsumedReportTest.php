<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Bin;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Product;
use App\Models\RouteLocation;
use App\Models\Service;
use App\Models\ServiceSale;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendingRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryConsumedReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_consumed_report_groups_calculated_units_by_category_and_product(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Inventory Consumed Account');
        $otherAccount = $this->createAccount('Foreign Inventory Consumed Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_MANAGER);

        $route = $this->createRoute($account, 'Inventory Route');
        $location = $this->createLocation($account, $route, 'Main Stop');
        $otherRoute = $this->createRoute($otherAccount, 'Foreign Route');
        $otherLocation = $this->createLocation($otherAccount, $otherRoute, 'Foreign Stop');

        $colaContext = $this->createInventoryContext($account, $location, 'beverage', 'Cola', 'cola');
        $waterContext = $this->createInventoryContext($account, $location, 'beverage', 'Water', 'water');
        $chipsContext = $this->createInventoryContext($account, $location, 'snack', 'Chips', 'chips');
        $candyContext = $this->createInventoryContext($account, $location, 'snack', 'Candy', 'candy');
        $frozenContext = $this->createInventoryContext($account, $location, 'frozen', 'Ice Cream', 'ice-cream');
        $foreignContext = $this->createInventoryContext($otherAccount, $otherLocation, 'beverage', 'Foreign Cola', 'foreign-cola');

        $serviceOne = $this->createService($account, $location, [
            'service_date' => '2026-07-10',
        ]);
        $serviceTwo = $this->createService($account, $location, [
            'service_date' => '2026-07-12',
        ]);
        $historicalService = $this->createService($account, $location, [
            'service_date' => '2026-06-30',
        ]);
        $foreignService = $this->createService($otherAccount, $otherLocation, [
            'service_date' => '2026-07-11',
        ]);

        $colaCountTransaction = $this->createCountTransaction($account, $serviceOne, $colaContext);
        $waterCountTransaction = $this->createCountTransaction($account, $serviceTwo, $waterContext);
        $chipsCountTransaction = $this->createCountTransaction($account, $serviceTwo, $chipsContext);
        $candyBaselineCountTransaction = $this->createCountTransaction($account, $serviceOne, $candyContext);
        $historicalColaCountTransaction = $this->createCountTransaction($account, $historicalService, $colaContext, [
            'transaction_at' => '2026-06-30 12:00:00',
        ]);
        $foreignCountTransaction = $this->createCountTransaction($otherAccount, $foreignService, $foreignContext);

        $this->createServiceSale($account, $serviceOne, $location, $colaContext, $colaCountTransaction, [
            'sales_date' => '2026-07-10',
            'units_sold' => 4,
            'sales_amount' => '8.00',
            'calculation_status' => ServiceSale::CALCULATION_CALCULATED,
        ]);
        $this->createServiceSale($account, $serviceTwo, $location, $waterContext, $waterCountTransaction, [
            'sales_date' => '2026-07-12',
            'units_sold' => 3,
            'sales_amount' => '6.00',
            'calculation_status' => ServiceSale::CALCULATION_CALCULATED,
        ]);
        $this->createServiceSale($account, $serviceTwo, $location, $chipsContext, $chipsCountTransaction, [
            'sales_date' => '2026-07-11',
            'units_sold' => 5,
            'sales_amount' => '10.00',
            'calculation_status' => ServiceSale::CALCULATION_CALCULATED,
        ]);
        $this->createServiceSale($account, $serviceOne, $location, $candyContext, $candyBaselineCountTransaction, [
            'sales_date' => '2026-07-10',
            'units_sold' => 9,
            'sales_amount' => '18.00',
            'calculation_status' => ServiceSale::CALCULATION_BASELINE,
        ]);
        $this->createServiceSale($account, $historicalService, $location, $colaContext, $historicalColaCountTransaction, [
            'sales_date' => '2026-06-30',
            'units_sold' => 6,
            'sales_amount' => '12.00',
            'calculation_status' => ServiceSale::CALCULATION_CALCULATED,
        ]);
        $this->createServiceSale($otherAccount, $foreignService, $otherLocation, $foreignContext, $foreignCountTransaction, [
            'sales_date' => '2026-07-11',
            'units_sold' => 20,
            'sales_amount' => '40.00',
            'calculation_status' => ServiceSale::CALCULATION_CALCULATED,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.inventory-consumed', [
                'date_from' => '2026-07-10',
                'date_to' => '2026-07-12',
            ]));

        $response
            ->assertOk()
            ->assertSeeText('Inventory Consumed')
            ->assertSeeTextInOrder(['Beverage', 'Cola', 'Water', 'Snack', 'Chips'])
            ->assertSeeText('Category total: 7 units')
            ->assertSeeText('Category total: 5 units')
            ->assertSeeText('Grand Total Units')
            ->assertSeeText('12')
            ->assertDontSeeText('Candy')
            ->assertDontSeeText('Frozen')
            ->assertDontSeeText('Ice Cream')
            ->assertDontSeeText('Foreign Cola');

        $response
            ->assertSee('type="date"', false)
            ->assertSee('value="2026-07-10"', false)
            ->assertSee('value="2026-07-12"', false);
    }

    public function test_empty_inventory_consumed_range_shows_a_clear_message(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Empty Inventory Consumed Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.inventory-consumed', [
                'date_from' => '2026-07-01',
                'date_to' => '2026-07-31',
            ]));

        $response
            ->assertOk()
            ->assertSeeText('No inventory consumption in this date range.')
            ->assertSeeText('Grand Total Units')
            ->assertSeeText('0');
    }

    public function test_inventory_consumed_report_is_forbidden_for_technicians_and_viewers(): void
    {
        $account = $this->createAccount('Inventory Consumed Permissions');

        foreach ([AccountUser::ROLE_TECHNICIAN, AccountUser::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
            $this->attachUserToAccount($user, $account, $role);

            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('reports.inventory-consumed'))
                ->assertForbidden();
        }
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

    protected function createLocation(Account $account, VendingRoute $route, string $name): Location
    {
        $location = Location::create([
            'account_id' => $account->id,
            'location_name' => $name,
            'address' => '123 Main Street',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M1M1M1',
            'is_inventory' => null,
        ]);

        $nextStopOrder = ((int) RouteLocation::query()
            ->where('account_id', $account->id)
            ->where('route_id', $route->id)
            ->max('stop_order')) + 1;

        RouteLocation::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_id' => $location->id,
            'stop_order' => $nextStopOrder,
            'is_primary' => true,
        ]);

        return $location;
    }

    protected function createService(Account $account, Location $location, array $attributes = []): Service
    {
        return Service::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => null,
            'user_id' => null,
            'created_by_user_id' => null,
            'closed_by_user_id' => null,
            'service_type' => $attributes['service_type'] ?? Service::TYPE_LOCATION,
            'notes' => null,
            'service_date' => $attributes['service_date'] ?? '2026-07-10',
            'scheduled_at' => null,
            'opened_at' => null,
            'completed_at' => null,
            'closed_at' => null,
            'amount_collected' => array_key_exists('amount_collected', $attributes)
                ? $attributes['amount_collected']
                : '10.00',
            'status' => Service::STATUS_CLOSED,
        ]);
    }

    protected function createInventoryContext(Account $account, Location $location, string $category, string $productName, string $skuPrefix): array
    {
        $product = Product::create([
            'account_id' => $account->id,
            'vendor_id' => null,
            'category' => $category,
            'brand' => $productName.' Brand',
            'sku' => $skuPrefix.'-sku-'.uniqid(),
            'product_name' => $productName,
            'size' => null,
            'package_type' => null,
            'barcode' => null,
            'reorder_point' => null,
        ]);

        $machine = Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => 'snack',
            'serial_number' => $skuPrefix.'-machine-'.uniqid(),
            'key_number' => null,
            'telemetry_id' => null,
            'model' => $productName.' Model',
            'status' => Machine::STATUS_ACTIVE,
            'installed_on' => null,
        ]);

        $bin = Bin::create([
            'account_id' => $account->id,
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'bin_code' => strtoupper(substr($skuPrefix, 0, 1)).'1',
            'capacity' => 20,
            'price' => '2.50',
        ]);

        return [
            'product' => $product,
            'machine' => $machine,
            'bin' => $bin,
        ];
    }

    protected function createCountTransaction(Account $account, Service $service, array $inventoryContext, array $attributes = []): Transaction
    {
        /** @var \App\Models\Machine $machine */
        $machine = $inventoryContext['machine'];
        /** @var \App\Models\Bin $bin */
        $bin = $inventoryContext['bin'];
        /** @var \App\Models\Product $product */
        $product = $inventoryContext['product'];

        return Transaction::create([
            'account_id' => $account->id,
            'service_id' => $service->id,
            'machine_id' => $machine->id,
            'bin_id' => $bin->id,
            'product_id' => $product->id,
            'transaction_type' => Transaction::TYPE_COUNT,
            'quantity' => $attributes['quantity'] ?? 0,
            'spoilage' => $attributes['spoilage'] ?? 0,
            'transaction_at' => $attributes['transaction_at'] ?? '2026-07-10 12:00:00',
            'price' => $attributes['price'] ?? $bin->price,
            'unit_cost' => $attributes['unit_cost'] ?? '1.0000',
        ]);
    }

    protected function createServiceSale(Account $account, Service $service, Location $location, array $inventoryContext, Transaction $countTransaction, array $attributes = []): ServiceSale
    {
        /** @var \App\Models\Machine $machine */
        $machine = $inventoryContext['machine'];
        /** @var \App\Models\Bin $bin */
        $bin = $inventoryContext['bin'];
        /** @var \App\Models\Product $product */
        $product = $inventoryContext['product'];

        return ServiceSale::create([
            'account_id' => $account->id,
            'service_id' => $service->id,
            'location_id' => $location->id,
            'machine_id' => $machine->id,
            'bin_id' => $bin->id,
            'product_id' => $product->id,
            'previous_inventory_transaction_id' => null,
            'count_transaction_id' => $countTransaction->id,
            'calculation_status' => $attributes['calculation_status'] ?? ServiceSale::CALCULATION_CALCULATED,
            'calculation_note' => null,
            'sales_date' => $attributes['sales_date'] ?? '2026-07-10',
            'opening_quantity' => 10,
            'spoilage' => 0,
            'counted_quantity' => 5,
            'units_sold' => $attributes['units_sold'] ?? 1,
            'unit_price' => $attributes['unit_price'] ?? '1.00',
            'sales_amount' => $attributes['sales_amount'] ?? '1.00',
            'calculation_version' => 1,
            'calculated_at' => '2026-07-10 13:00:00',
        ]);
    }
}
