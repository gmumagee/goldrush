<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Bin;
use App\Models\Expense;
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

class ProfitLossReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_profit_loss_report_sums_revenue_cogs_expenses_and_net_income_correctly(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('P and L Account');
        $otherAccount = $this->createAccount('Foreign P and L Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_MANAGER);

        $route = $this->createRoute($account, 'P and L Route');
        $location = $this->createLocation($account, $route, 'Main Stop');
        $otherRoute = $this->createRoute($otherAccount, 'Foreign Route');
        $otherLocation = $this->createLocation($otherAccount, $otherRoute, 'Foreign Stop');
        $inventoryContext = $this->createInventoryContext($account, $location, 'Main');
        $baselineInventoryContext = $this->createInventoryContext($account, $location, 'Baseline');
        $historicalInventoryContext = $this->createInventoryContext($account, $location, 'History');
        $foreignInventoryContext = $this->createInventoryContext($otherAccount, $otherLocation, 'Foreign');

        $serviceOne = $this->createService($account, $location, [
            'service_date' => '2026-07-10',
            'amount_collected' => '40.00',
            'completed_at' => '2026-07-10 10:00:00',
            'closed_at' => '2026-07-10 11:00:00',
        ]);
        $serviceTwo = $this->createService($account, $location, [
            'service_date' => '2026-07-12',
            'amount_collected' => '20.00',
            'completed_at' => '2026-07-12 10:00:00',
            'closed_at' => '2026-07-12 11:00:00',
        ]);
        $this->createService($account, $location, [
            'service_date' => '2026-06-29',
            'amount_collected' => '100.00',
            'completed_at' => '2026-06-29 10:00:00',
            'closed_at' => '2026-06-29 11:00:00',
        ]);
        $this->createService($account, $location, [
            'service_date' => '2026-07-11',
            'service_type' => Service::TYPE_MAINTENANCE,
            'amount_collected' => '55.00',
        ]);
        $foreignService = $this->createService($otherAccount, $otherLocation, [
            'service_date' => '2026-07-11',
            'amount_collected' => '500.00',
        ]);

        $countTransactionOne = $this->createCountTransaction($account, $serviceOne, $inventoryContext, [
            'unit_cost' => '2.5000',
        ]);
        $countTransactionTwo = $this->createCountTransaction($account, $serviceTwo, $inventoryContext, [
            'unit_cost' => '1.2000',
        ]);
        $baselineCountTransaction = $this->createCountTransaction($account, $serviceOne, $baselineInventoryContext, [
            'unit_cost' => '9.0000',
        ]);
        $foreignCountTransaction = $this->createCountTransaction($otherAccount, $foreignService, $foreignInventoryContext, [
            'unit_cost' => '10.0000',
        ]);

        $this->createServiceSale($account, $serviceOne, $location, $inventoryContext, $countTransactionOne, [
            'sales_date' => '2026-07-10',
            'units_sold' => 4,
            'sales_amount' => '44.00',
            'calculation_status' => ServiceSale::CALCULATION_CALCULATED,
        ]);
        $this->createServiceSale($account, $serviceTwo, $location, $inventoryContext, $countTransactionTwo, [
            'sales_date' => '2026-07-12',
            'units_sold' => 3,
            'sales_amount' => '18.00',
            'calculation_status' => ServiceSale::CALCULATION_CALCULATED,
        ]);
        $this->createServiceSale($account, $serviceOne, $location, $baselineInventoryContext, $baselineCountTransaction, [
            'sales_date' => '2026-07-11',
            'units_sold' => 7,
            'sales_amount' => '70.00',
            'calculation_status' => ServiceSale::CALCULATION_BASELINE,
        ]);
        $historicalCountTransaction = $this->createCountTransaction($account, $serviceOne, $historicalInventoryContext, [
            'unit_cost' => '3.0000',
        ]);
        $this->createServiceSale($account, $serviceOne, $location, $historicalInventoryContext, $historicalCountTransaction, [
            'sales_date' => '2026-06-30',
            'units_sold' => 5,
            'sales_amount' => '50.00',
            'calculation_status' => ServiceSale::CALCULATION_CALCULATED,
        ]);
        $this->createServiceSale($otherAccount, $foreignService, $otherLocation, $foreignInventoryContext, $foreignCountTransaction, [
            'sales_date' => '2026-07-11',
            'units_sold' => 2,
            'sales_amount' => '500.00',
            'calculation_status' => ServiceSale::CALCULATION_CALCULATED,
        ]);

        $this->createExpense($account, [
            'category' => Expense::CATEGORY_FUEL,
            'amount' => 5.00,
            'expense_date' => '2026-07-10',
        ]);
        $this->createExpense($account, [
            'category' => Expense::CATEGORY_RENT,
            'amount' => 7.50,
            'expense_date' => '2026-07-11',
        ]);
        $this->createExpense($account, [
            'category' => Expense::CATEGORY_OTHER,
            'amount' => 2.50,
            'expense_date' => '2026-07-12',
        ]);
        $this->createExpense($account, [
            'category' => Expense::CATEGORY_SUPPLIES,
            'amount' => 99.00,
            'expense_date' => '2026-06-15',
        ]);
        $this->createExpense($otherAccount, [
            'category' => Expense::CATEGORY_FUEL,
            'amount' => 200.00,
            'expense_date' => '2026-07-11',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.profit-loss', [
                'date_from' => '2026-07-10',
                'date_to' => '2026-07-12',
            ]));

        $response
            ->assertOk()
            ->assertSeeText('Profit & Loss')
            ->assertSeeText('Actual Cash Collected')
            ->assertSeeText('$60.00')
            ->assertSeeText('Calculated Sales')
            ->assertSeeText('$62.00')
            ->assertSeeText('Variance (possible shrinkage/overage)')
            ->assertSeeText('$2.00')
            ->assertSeeText('COGS')
            ->assertSeeText('$13.60')
            ->assertSeeText('Gross Profit')
            ->assertSeeText('$46.40')
            ->assertSeeText('Fuel')
            ->assertSeeText('$5.00')
            ->assertSeeText('Rent')
            ->assertSeeText('$7.50')
            ->assertSeeText('Other')
            ->assertSeeText('$2.50')
            ->assertSeeText('Total Expenses')
            ->assertSeeText('$15.00')
            ->assertSeeText('Net Income')
            ->assertSeeText('$31.40')
            ->assertDontSeeText('500.00');

        $response
            ->assertSee('type="date"', false)
            ->assertSee('value="2026-07-10"', false)
            ->assertSee('value="2026-07-12"', false);
    }

    public function test_empty_profit_loss_range_renders_zero_values(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Empty P and L Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.profit-loss', [
                'date_from' => '2026-07-01',
                'date_to' => '2026-07-31',
            ]));

        $response
            ->assertOk()
            ->assertSeeText('No revenue, sales, or expenses were found in this date range. All values are shown as zero.')
            ->assertSeeText('Actual Cash Collected')
            ->assertSeeText('Calculated Sales')
            ->assertSeeText('COGS')
            ->assertSeeText('Gross Profit')
            ->assertSeeText('Total Expenses')
            ->assertSeeText('Net Income');

        $this->assertGreaterThanOrEqual(6, substr_count($response->getContent(), '$0.00'));
    }

    public function test_profit_loss_report_is_forbidden_for_technicians_and_viewers(): void
    {
        $account = $this->createAccount('P and L Permissions');

        foreach ([AccountUser::ROLE_TECHNICIAN, AccountUser::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
            $this->attachUserToAccount($user, $account, $role);

            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('reports.profit-loss'))
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
            'completed_at' => $attributes['completed_at'] ?? null,
            'closed_at' => $attributes['closed_at'] ?? null,
            'amount_collected' => array_key_exists('amount_collected', $attributes)
                ? $attributes['amount_collected']
                : '10.00',
            'status' => $attributes['status'] ?? Service::STATUS_CLOSED,
        ]);
    }

    protected function createInventoryContext(Account $account, Location $location, string $prefix): array
    {
        $product = Product::create([
            'account_id' => $account->id,
            'vendor_id' => null,
            'category' => 'snack',
            'brand' => $prefix.' Brand',
            'sku' => strtolower($prefix).'-sku-'.uniqid(),
            'product_name' => $prefix.' Product',
            'size' => null,
            'package_type' => null,
            'barcode' => null,
            'reorder_point' => null,
        ]);

        $machine = Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => 'snack',
            'serial_number' => strtolower($prefix).'-machine-'.uniqid(),
            'key_number' => null,
            'telemetry_id' => null,
            'model' => $prefix.' Model',
            'status' => Machine::STATUS_ACTIVE,
            'installed_on' => null,
        ]);

        $bin = Bin::create([
            'account_id' => $account->id,
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'bin_code' => strtoupper(substr($prefix, 0, 1)).'1',
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

    protected function createExpense(Account $account, array $attributes): Expense
    {
        $expense = new Expense([
            'location_id' => $attributes['location_id'] ?? null,
            'category' => $attributes['category'] ?? Expense::CATEGORY_OTHER,
            'amount' => $attributes['amount'] ?? 10.00,
            'expense_date' => $attributes['expense_date'] ?? '2026-07-10',
            'vendor' => $attributes['vendor'] ?? null,
            'description' => $attributes['description'] ?? null,
        ]);

        $expense->account_id = $account->id;
        $expense->created_by_user_id = null;
        $expense->save();

        return $expense;
    }
}
