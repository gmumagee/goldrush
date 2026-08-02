<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Expense;
use App\Models\Location;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\RouteLocation;
use App\Models\Service;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashFlowReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_flow_report_sums_cash_in_expenses_purchases_and_commissions_correctly(): void
    {
        $manager = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Cash Flow Account');
        $otherAccount = $this->createAccount('Foreign Cash Flow Account');
        $this->attachUserToAccount($manager, $account, AccountUser::ROLE_MANAGER);

        $route = $this->createRoute($account, 'Cash Flow Route');
        $grossLocation = $this->createLocation($account, $route, 'Gross Stop', [
            'commission_rate' => '0.1000',
            'commission_on_net' => false,
        ]);
        $netLocation = $this->createLocation($account, $route, 'Net Stop', [
            'commission_rate' => '0.2000',
            'commission_on_net' => true,
        ]);
        $floorLocation = $this->createLocation($account, $route, 'Floor Stop', [
            'commission_rate' => '0.1500',
            'commission_on_net' => true,
        ]);
        $noCommissionLocation = $this->createLocation($account, $route, 'No Commission Stop');

        $otherRoute = $this->createRoute($otherAccount, 'Foreign Route');
        $foreignLocation = $this->createLocation($otherAccount, $otherRoute, 'Foreign Stop', [
            'commission_rate' => '0.5000',
            'commission_on_net' => false,
        ]);

        $this->createService($account, $grossLocation, [
            'service_date' => '2026-07-10',
            'amount_collected' => '100.00',
        ]);
        $this->createService($account, $netLocation, [
            'service_date' => '2026-07-11',
            'amount_collected' => '80.00',
        ]);
        $this->createService($account, $floorLocation, [
            'service_date' => '2026-07-12',
            'amount_collected' => '10.00',
        ]);
        $this->createService($account, $noCommissionLocation, [
            'service_date' => '2026-07-12',
            'amount_collected' => '40.00',
        ]);
        $this->createService($account, $grossLocation, [
            'service_date' => '2026-06-30',
            'amount_collected' => '999.00',
        ]);
        $this->createService($otherAccount, $foreignLocation, [
            'service_date' => '2026-07-11',
            'amount_collected' => '500.00',
        ]);

        $this->createExpense($account, [
            'location_id' => $grossLocation->id,
            'category' => Expense::CATEGORY_MAINTENANCE,
            'amount' => 5.00,
            'expense_date' => '2026-07-10',
        ]);
        $this->createExpense($account, [
            'location_id' => $netLocation->id,
            'category' => Expense::CATEGORY_SUPPLIES,
            'amount' => 30.00,
            'expense_date' => '2026-07-11',
        ]);
        $this->createExpense($account, [
            'location_id' => $floorLocation->id,
            'category' => Expense::CATEGORY_RENT,
            'amount' => 25.00,
            'expense_date' => '2026-07-12',
        ]);
        $this->createExpense($account, [
            'location_id' => null,
            'category' => Expense::CATEGORY_FUEL,
            'amount' => 20.00,
            'expense_date' => '2026-07-12',
        ]);
        $this->createExpense($account, [
            'location_id' => $grossLocation->id,
            'category' => Expense::CATEGORY_OTHER,
            'amount' => 99.00,
            'expense_date' => '2026-06-20',
        ]);
        $this->createExpense($otherAccount, [
            'location_id' => $foreignLocation->id,
            'category' => Expense::CATEGORY_FUEL,
            'amount' => 200.00,
            'expense_date' => '2026-07-11',
        ]);

        $warehouse = $this->createWarehouse($account, 'Main Warehouse');
        $product = $this->createProduct($account, 'Cola');
        $postedPurchase = $this->createPurchase($account, $warehouse, [
            'purchase_date' => '2026-07-10',
            'status' => Purchase::STATUS_POSTED,
        ]);
        $this->createPurchaseItem($account, $postedPurchase, $product, [
            'quantity' => 10,
            'line_total' => 50.00,
        ]);
        $voidedPurchase = $this->createPurchase($account, $warehouse, [
            'purchase_date' => '2026-07-11',
            'status' => Purchase::STATUS_VOIDED,
        ]);
        $this->createPurchaseItem($account, $voidedPurchase, $product, [
            'quantity' => 5,
            'line_total' => 25.00,
        ]);
        $outOfRangePurchase = $this->createPurchase($account, $warehouse, [
            'purchase_date' => '2026-06-30',
            'status' => Purchase::STATUS_POSTED,
        ]);
        $this->createPurchaseItem($account, $outOfRangePurchase, $product, [
            'quantity' => 3,
            'line_total' => 15.00,
        ]);
        $foreignWarehouse = $this->createWarehouse($otherAccount, 'Foreign Warehouse');
        $foreignProduct = $this->createProduct($otherAccount, 'Foreign Cola');
        $foreignPurchase = $this->createPurchase($otherAccount, $foreignWarehouse, [
            'purchase_date' => '2026-07-11',
            'status' => Purchase::STATUS_POSTED,
        ]);
        $this->createPurchaseItem($otherAccount, $foreignPurchase, $foreignProduct, [
            'quantity' => 8,
            'line_total' => 80.00,
        ]);

        $response = $this->actingAs($manager)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.cash-flow', [
                'date_from' => '2026-07-10',
                'date_to' => '2026-07-12',
            ]));

        $response
            ->assertOk()
            ->assertSeeText('Cash Flow')
            ->assertSeeText('Cash In')
            ->assertSeeText('$230.00')
            ->assertSeeText('Less: Expenses')
            ->assertSeeText('$80.00')
            ->assertSeeText('Less: Inventory Purchases')
            ->assertSeeText('$50.00')
            ->assertSeeText('Less: Commissions')
            ->assertSeeText('$20.00')
            ->assertSeeText('Net Cash Flow')
            ->assertSeeText('$80.00')
            ->assertSeeText('Gross Stop')
            ->assertSeeText('Net Stop')
            ->assertSeeText('Floor Stop')
            ->assertSeeText('$10.00')
            ->assertSeeText('$10.00')
            ->assertSeeText('$0.00')
            ->assertSeeText('Basis floored at $0.00 after expenses exceeded sales.')
            ->assertDontSeeText('500.00');
    }

    public function test_empty_cash_flow_report_renders_zero_values(): void
    {
        $manager = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Empty Cash Flow Account');
        $this->attachUserToAccount($manager, $account, AccountUser::ROLE_OWNER);

        $response = $this->actingAs($manager)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.cash-flow', [
                'date_from' => '2026-07-01',
                'date_to' => '2026-07-31',
            ]));

        $response
            ->assertOk()
            ->assertSeeText('No cash activity was found in this date range. All values are shown as zero.')
            ->assertSeeText('Cash In')
            ->assertSeeText('Less: Expenses')
            ->assertSeeText('Less: Inventory Purchases')
            ->assertSeeText('Less: Commissions')
            ->assertSeeText('Net Cash Flow');

        $this->assertGreaterThanOrEqual(5, substr_count($response->getContent(), '$0.00'));
    }

    public function test_cash_flow_report_is_forbidden_for_technicians_and_viewers(): void
    {
        $account = $this->createAccount('Cash Flow Permissions');

        foreach ([AccountUser::ROLE_TECHNICIAN, AccountUser::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
            $this->attachUserToAccount($user, $account, $role);

            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('reports.cash-flow'))
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

    protected function createLocation(Account $account, VendingRoute $route, string $name, array $attributes = []): Location
    {
        $location = Location::create([
            'account_id' => $account->id,
            'location_name' => $name,
            'address' => '123 Main Street',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M1M1M1',
            'service_interval_days' => null,
            'sales_tax_rate' => null,
            'commission_rate' => $attributes['commission_rate'] ?? null,
            'commission_on_net' => $attributes['commission_on_net'] ?? false,
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

    protected function createWarehouse(Account $account, string $name): Warehouse
    {
        return Warehouse::create([
            'account_id' => $account->id,
            'warehouse_name' => $name,
            'address' => null,
            'city' => null,
            'state' => null,
            'zip_code' => null,
        ]);
    }

    protected function createProduct(Account $account, string $name): Product
    {
        return Product::create([
            'account_id' => $account->id,
            'vendor_id' => null,
            'category' => 'beverage',
            'brand' => $name.' Brand',
            'sku' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'product_name' => $name,
            'size' => null,
            'package_type' => null,
            'barcode' => null,
            'reorder_point' => null,
        ]);
    }

    protected function createPurchase(Account $account, Warehouse $warehouse, array $attributes = []): Purchase
    {
        return Purchase::create([
            'account_id' => $account->id,
            'vendor_id' => null,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => $attributes['invoice_number'] ?? null,
            'purchase_date' => $attributes['purchase_date'] ?? '2026-07-10',
            'status' => $attributes['status'] ?? Purchase::STATUS_POSTED,
            'notes' => null,
        ]);
    }

    protected function createPurchaseItem(Account $account, Purchase $purchase, Product $product, array $attributes = []): PurchaseItem
    {
        return PurchaseItem::create([
            'account_id' => $account->id,
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => $attributes['quantity'] ?? 1,
            'line_total' => $attributes['line_total'] ?? 10.00,
            'unit_cost' => $attributes['unit_cost'] ?? '1.0000',
        ]);
    }
}
