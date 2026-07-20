<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Bin;
use App\Models\CalendarEvent;
use App\Models\InventoryLedger;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceSale;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-17 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_shows_the_current_sunday_through_saturday_week_with_scheduled_events_only(): void
    {
        // Keep the calendar assertions focused on the scheduled weekly event view.
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Dashboard Account');
        $otherAccount = $this->createAccount('Other Dashboard Account');
        $this->attachUserToAccount($user, $account, 'owner');

        CalendarEvent::create([
            'account_id' => $account->id,
            'event_type' => 'Service',
            'title' => 'Service: Main Office',
            'start_at' => '2026-07-13 09:00:00',
            'status' => CalendarEvent::STATUS_SCHEDULED,
        ]);

        CalendarEvent::create([
            'account_id' => $account->id,
            'event_type' => 'Maintenance',
            'title' => 'Maintenance Check',
            'start_at' => '2026-07-14 10:00:00',
            'status' => CalendarEvent::STATUS_SCHEDULED,
        ]);

        CalendarEvent::create([
            'account_id' => $account->id,
            'event_type' => 'Purchase',
            'title' => 'Vendor Purchase',
            'start_at' => '2026-07-16 11:00:00',
            'status' => CalendarEvent::STATUS_SCHEDULED,
        ]);

        CalendarEvent::create([
            'account_id' => $account->id,
            'event_type' => 'General',
            'title' => 'General Visit',
            'start_at' => '2026-07-18 15:00:00',
            'all_day' => true,
            'status' => CalendarEvent::STATUS_SCHEDULED,
        ]);

        CalendarEvent::create([
            'account_id' => $account->id,
            'event_type' => 'Service',
            'title' => 'Completed Event',
            'start_at' => '2026-07-15 12:00:00',
            'status' => CalendarEvent::STATUS_COMPLETED,
        ]);

        CalendarEvent::create([
            'account_id' => $account->id,
            'event_type' => 'Service',
            'title' => 'Next Week Event',
            'start_at' => '2026-07-20 09:00:00',
            'status' => CalendarEvent::STATUS_SCHEDULED,
        ]);

        CalendarEvent::create([
            'account_id' => $otherAccount->id,
            'event_type' => 'Service',
            'title' => 'Other Account Event',
            'start_at' => '2026-07-13 09:00:00',
            'status' => CalendarEvent::STATUS_SCHEDULED,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Weekly Calendar')
            ->assertSee('07-12-2026 - 07-18-2026')
            ->assertSee('Sunday')
            ->assertSee('Saturday')
            ->assertSee('Service: Main Office')
            ->assertSee('Maintenance Check')
            ->assertSee('Vendor Purchase')
            ->assertSee('General Visit')
            ->assertSee('calendar-event--service')
            ->assertSee('calendar-event--maintenance')
            ->assertSee('calendar-event--purchase')
            ->assertSee('calendar-event--default')
            ->assertSee(route('dashboard', ['date' => '2026-07-05']), false)
            ->assertSee(route('dashboard', ['date' => '2026-07-17']), false)
            ->assertSee(route('dashboard', ['date' => '2026-07-19']), false)
            ->assertDontSee('Upcoming Events')
            ->assertDontSee('Reminders')
            ->assertDontSee('Completed Event')
            ->assertDontSee('Next Week Event')
            ->assertDontSee('Other Account Event')
            ->assertDontSee('All day')
            ->assertDontSee('Assigned User')
            ->assertDontSee('Location');
    }

    public function test_dashboard_shows_the_lowest_main_warehouse_inventory_products_for_the_current_account(): void
    {
        // Seed multiple warehouses and accounts so the dashboard card proves its scope and ordering rules.
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Warehouse Dashboard Account');
        $otherAccount = $this->createAccount('Other Warehouse Dashboard Account');
        $this->attachUserToAccount($user, $account, 'owner');

        $mainWarehouse = $this->createWarehouse($account, 'Main Warehouse');
        $secondaryWarehouse = $this->createWarehouse($account, 'Secondary Warehouse');
        $otherMainWarehouse = $this->createWarehouse($otherAccount, 'Main Warehouse');

        $backorderBars = $this->createProduct($account, 'Backorder Bars', 'BACKORDER');
        $chips = $this->createProduct($account, 'Chips', 'CHIPS');
        $gum = $this->createProduct($account, 'Gum', 'GUM');
        $water = $this->createProduct($account, 'Water', 'WATER');
        $almonds = $this->createProduct($account, 'Almonds', 'ALMONDS');
        $juice = $this->createProduct($account, 'Juice', 'JUICE');
        $crackers = $this->createProduct($account, 'Crackers', 'CRACKERS');
        $nuts = $this->createProduct($account, 'Nuts', 'NUTS');
        $cookies = $this->createProduct($account, 'Cookies', 'COOKIES');
        $tea = $this->createProduct($account, 'Tea', 'TEA');
        $coffee = $this->createProduct($account, 'Coffee', 'COFFEE');
        $pretzels = $this->createProduct($account, 'Pretzels', 'PRETZELS');
        $candy = $this->createProduct($account, 'Candy', 'CANDY');
        $cola = $this->createProduct($account, 'Cola', 'COLA');
        $machineOnlyProduct = $this->createProduct($account, 'Machine Only Item', 'MACHINE-ONLY');
        $otherAccountProduct = $this->createProduct($otherAccount, 'Other Account Soda', 'OTHER-SODA');

        $this->postWarehouseInventory($account, $mainWarehouse, $backorderBars, -2);
        $this->postWarehouseInventory($account, $mainWarehouse, $chips, 0);
        $this->postWarehouseInventory($account, $mainWarehouse, $gum, 2);
        $this->postWarehouseInventory($account, $mainWarehouse, $water, 3);
        $this->postWarehouseInventory($account, $mainWarehouse, $almonds, 4);
        $this->postWarehouseInventory($account, $mainWarehouse, $juice, 4);
        $this->postWarehouseInventory($account, $mainWarehouse, $crackers, 5);
        $this->postWarehouseInventory($account, $mainWarehouse, $nuts, 6);
        $this->postWarehouseInventory($account, $mainWarehouse, $cookies, 7);
        $this->postWarehouseInventory($account, $mainWarehouse, $tea, 8);
        $this->postWarehouseInventory($account, $mainWarehouse, $coffee, 9);
        $this->postWarehouseInventory($account, $mainWarehouse, $pretzels, 10);
        $this->postWarehouseInventory($account, $mainWarehouse, $candy, 12);
        $this->postWarehouseInventory($account, $mainWarehouse, $cola, 18);

        // Add conflicting inventory in another warehouse so the dashboard card must ignore it.
        $this->postWarehouseInventory($account, $secondaryWarehouse, $cola, 1);
        $this->postWarehouseInventory($account, $secondaryWarehouse, $water, 100);

        // Add another account's Main Warehouse inventory so cross-account rows never leak into the card.
        $this->postWarehouseInventory($otherAccount, $otherMainWarehouse, $otherAccountProduct, -10);

        // Create machine-bin current inventory evidence to prove the card does not use machine stock.
        $this->createMachineOnlyInventoryTransaction($account, $machineOnlyProduct, 99);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Sales')
            ->assertSee('Low Inventory — Main Warehouse')
            ->assertSee('grid grid-cols-1 gap-6 lg:grid-cols-12', false)
            ->assertSee('lg:col-span-8', false)
            ->assertSee('lg:col-span-4', false)
            ->assertSee('dashboard-low-inventory-card h-full', false)
            ->assertSee('low-inventory-row', false)
            ->assertSeeInOrder([
                'Sales',
                'Low Inventory — Main Warehouse',
            ])
            ->assertSeeInOrder([
                'Backorder Bars',
                'Chips',
                'Gum',
                'Water',
                'Almonds',
                'Juice',
                'Crackers',
                'Nuts',
                'Cookies',
                'Tea',
            ])
            ->assertSee('-2')
            ->assertSee('0')
            ->assertSee('ALMONDS')
            ->assertSee('JUICE')
            ->assertDontSee('Coffee')
            ->assertDontSee('Pretzels')
            ->assertDontSee('Candy')
            ->assertDontSee('Cola')
            ->assertDontSee('Machine Only Item')
            ->assertDontSee('Other Account Soda')
            ->assertDontSee('SKU')
            ->assertDontSee('View Main Warehouse Inventory')
            ->assertDontSee(route('warehouses.show', $mainWarehouse), false)
            ->assertDontSee(route('products.show', $chips), false);

        $this->assertSame(10, substr_count($response->getContent(), 'class="low-inventory-row"'));
    }

    public function test_dashboard_shows_a_missing_main_warehouse_state_when_the_account_has_no_main_warehouse(): void
    {
        // Surface a clear card state instead of silently choosing another warehouse.
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('No Main Warehouse Account');
        $this->attachUserToAccount($user, $account, 'owner');
        $this->createWarehouse($account, 'Secondary Warehouse');

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Low Inventory — Main Warehouse')
            ->assertSee('Main Warehouse is not configured for this account.');
    }

    public function test_dashboard_shows_a_no_inventory_state_when_main_warehouse_has_no_ledger_history(): void
    {
        // Distinguish an empty ledger from a missing warehouse so the card message stays actionable.
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Empty Main Warehouse Account');
        $this->attachUserToAccount($user, $account, 'owner');
        $this->createWarehouse($account, 'Main Warehouse');

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Low Inventory — Main Warehouse')
            ->assertSee('No inventory data is available for Main Warehouse.');
    }

    public function test_dashboard_shows_sales_chart_data_with_expected_periods_and_excludes_non_calculated_rows(): void
    {
        // Seed calculated and excluded sales rows so the chart can prove its bucketing and scoping rules.
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Sales Dashboard Account');
        $otherAccount = $this->createAccount('Other Sales Dashboard Account');
        $this->attachUserToAccount($user, $account, 'owner');

        $currentProduct = $this->createProduct($account, 'Current Period Product', 'CURRENT-PRODUCT');
        $historicalProduct = $this->createProduct($account, 'Historical Product', 'HISTORICAL-PRODUCT');
        $otherAccountProduct = $this->createProduct($otherAccount, 'Other Account Product', 'OTHER-PRODUCT');

        $this->createServiceSale($account, $currentProduct, '2026-07-01', '20.00');
        $this->createServiceSale($account, $currentProduct, '2026-07-01', '15.00');
        $this->createServiceSale($account, $currentProduct, '2026-07-02', null, ServiceSale::CALCULATION_BASELINE);
        $this->createServiceSale($account, $currentProduct, '2026-07-02', null, ServiceSale::CALCULATION_CALCULATED);
        $this->createServiceSale($account, $currentProduct, '2026-07-03', '12.50');
        $this->createServiceSale($account, $currentProduct, '2026-07-17', '7.25');
        $this->createServiceSale($account, $historicalProduct, '2026-06-14', '40.00');
        $this->createServiceSale($account, $historicalProduct, '2026-04-26', '60.00');
        $this->createServiceSale($account, $historicalProduct, '2026-02-08', '80.00');
        $this->createServiceSale($account, $historicalProduct, '2026-01-05', '100.00');
        $this->createServiceSale($account, $historicalProduct, '2025-08-10', '120.00');
        $this->createServiceSale($otherAccount, $otherAccountProduct, '2026-07-01', '999.00');

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Sales')
            ->assertSee('Sales (USD)')
            ->assertSee('Last 1 Month')
            ->assertSee('data-sales-chart="line"', false)
            ->assertSee('aria-label="Sales by date for the last 1 month"', false)
            ->assertSee('x-text="currentPeriod.x_axis_label"', false)
            ->assertSee('>Date</text>', false)
            ->assertSee('>$0</text>', false)
            ->assertSeeInOrder([
                '1 Month',
                '3 Months',
                '6 Months',
                '1 Year',
            ])
            ->assertSee('data-sales-period="1m"', false)
            ->assertSee('data-sales-period="3m"', false)
            ->assertSee('data-sales-period="6m"', false)
            ->assertSee('data-sales-period="1y"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('Low Inventory — Main Warehouse')
            ->assertViewHas('salesChart', function (array $salesChart) {
                $oneMonth = $salesChart['periods']['1m'] ?? [];
                $threeMonths = $salesChart['periods']['3m'] ?? [];
                $sixMonths = $salesChart['periods']['6m'] ?? [];
                $oneYear = $salesChart['periods']['1y'] ?? [];
                $oneMonthLabels = $oneMonth['labels'] ?? [];
                $oneMonthValues = $oneMonth['values'] ?? [];
                $threeMonthLabels = $threeMonths['labels'] ?? [];
                $threeMonthValues = $threeMonths['values'] ?? [];
                $sixMonthLabels = $sixMonths['labels'] ?? [];
                $sixMonthValues = $sixMonths['values'] ?? [];
                $oneYearLabels = $oneYear['labels'] ?? [];
                $oneYearValues = $oneYear['values'] ?? [];
                $julyFirstIndex = array_search('07-01-2026', $oneMonthLabels, true);
                $julySecondIndex = array_search('07-02-2026', $oneMonthLabels, true);
                $julyThirdIndex = array_search('07-03-2026', $oneMonthLabels, true);
                $julySeventeenthIndex = array_search('07-17-2026', $oneMonthLabels, true);
                $juneTwentyEighthWeekIndex = array_search('06-28-2026', $threeMonthLabels, true);
                $julyTwelfthWeekIndex = array_search('07-12-2026', $threeMonthLabels, true);
                $aprilTwentySixthWeekIndex = array_search('04-26-2026', $threeMonthLabels, true);
                $februaryEighthWeekIndex = array_search('02-08-2026', $sixMonthLabels, true);
                $augustMonthIndex = array_search('08-01-2025', $oneYearLabels, true);
                $januaryMonthIndex = array_search('01-01-2026', $oneYearLabels, true);
                $julyMonthIndex = array_search('07-01-2026', $oneYearLabels, true);

                return $salesChart['default_period'] === '1m'
                    && $oneMonth['label'] === '1 Month'
                    && $oneMonth['title'] === 'Last 1 Month'
                    && $oneMonth['x_axis_label'] === 'Date'
                    && count($oneMonthLabels) === 31
                    && $oneMonth['has_sales'] === true
                    && $julyFirstIndex !== false
                    && $julySecondIndex !== false
                    && $julyThirdIndex !== false
                    && $julySeventeenthIndex !== false
                    && (float) $oneMonthValues[$julyFirstIndex] === 35.0
                    && (float) $oneMonthValues[$julySecondIndex] === 0.0
                    && (float) $oneMonthValues[$julyThirdIndex] === 12.5
                    && (float) $oneMonthValues[$julySeventeenthIndex] === 7.25
                    && $threeMonths['label'] === '3 Months'
                    && $threeMonths['title'] === 'Last 3 Months'
                    && $threeMonths['x_axis_label'] === 'Week'
                    && $threeMonths['has_sales'] === true
                    && $aprilTwentySixthWeekIndex !== false
                    && $juneTwentyEighthWeekIndex !== false
                    && $julyTwelfthWeekIndex !== false
                    && (float) $threeMonthValues[$aprilTwentySixthWeekIndex] === 60.0
                    && (float) $threeMonthValues[$juneTwentyEighthWeekIndex] === 47.5
                    && (float) $threeMonthValues[$julyTwelfthWeekIndex] === 7.25
                    && $sixMonths['label'] === '6 Months'
                    && $sixMonths['title'] === 'Last 6 Months'
                    && $sixMonths['x_axis_label'] === 'Week'
                    && $sixMonths['has_sales'] === true
                    && $februaryEighthWeekIndex !== false
                    && (float) $sixMonthValues[$februaryEighthWeekIndex] === 80.0
                    && $oneYear['label'] === '1 Year'
                    && $oneYear['title'] === 'Last 1 Year'
                    && $oneYear['x_axis_label'] === 'Month'
                    && $oneYear['has_sales'] === true
                    && $augustMonthIndex !== false
                    && $januaryMonthIndex !== false
                    && $julyMonthIndex !== false
                    && (float) $oneYearValues[$augustMonthIndex] === 120.0
                    && (float) $oneYearValues[$januaryMonthIndex] === 100.0
                    && (float) $oneYearValues[$julyMonthIndex] === 54.75;
            });
    }

    public function test_dashboard_marks_sales_periods_without_calculated_revenue_as_empty(): void
    {
        // Keep the chart visible with zero-filled periods even when an account has no finalized sales yet.
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Empty Sales Account');
        $this->attachUserToAccount($user, $account, 'owner');

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Sales')
            ->assertSee('No calculated sales were recorded during this period.')
            ->assertViewHas('salesChart', function (array $salesChart) {
                foreach ($salesChart['periods'] ?? [] as $period) {
                    if (($period['has_sales'] ?? true) !== false) {
                        return false;
                    }

                    foreach ($period['values'] ?? [] as $value) {
                        if ((float) $value !== 0.0) {
                            return false;
                        }
                    }
                }

                return true;
            });
    }

    public function test_dashboard_styles_use_responsive_grid_widths_without_fixed_dashboard_card_sizes_or_low_inventory_font_overrides(): void
    {
        // Read the shared stylesheet directly so the dashboard layout stays responsive without reviving fixed-width or font-size card rules.
        $styles = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($styles);
        $this->assertStringContainsString('.dashboard-low-inventory-card', $styles);
        $this->assertStringContainsString('max-width: none;', $styles);
        $this->assertStringContainsString('.dashboard-sales-chart-container', $styles);
        $this->assertStringContainsString('min-height: 300px;', $styles);
        $this->assertStringContainsString('height: 340px;', $styles);
        $this->assertStringContainsString('@media (max-width: 576px)', $styles);
        $this->assertStringContainsString('height: 280px;', $styles);
        $this->assertStringContainsString('padding: 6px 8px;', $styles);
        $this->assertStringContainsString('line-height: 1.25;', $styles);
        $this->assertStringNotContainsString('.dashboard-card-row', $styles);
        $this->assertStringNotContainsString('.dashboard-sales-wrapper', $styles);
        $this->assertStringNotContainsString('.dashboard-low-inventory-wrapper', $styles);
        $this->assertStringNotContainsString('max-width: 280px;', $styles);
        $this->assertStringNotContainsString('width: 280px;', $styles);
        $this->assertStringNotContainsString('flex: 0 0 280px;', $styles);
        $this->assertStringNotContainsString('.dashboard-low-inventory-card,', $styles);
        $this->assertStringNotContainsString('.dashboard-low-inventory-card *', $styles);
        $this->assertStringNotContainsString('font-size: 10px;', $styles);
        $this->assertStringNotContainsString('font-size: 12px;', $styles);
        $this->assertStringNotContainsString('font-size: 12pt;', $styles);
        $this->assertStringNotContainsString('font-size: 10pt;', $styles);
        $this->assertStringNotContainsString('max-width: 50px;', $styles);
        $this->assertStringNotContainsString('width: 50px;', $styles);
        $this->assertStringNotContainsString('flex-basis: 50px;', $styles);
    }

    public function test_dashboard_sales_chart_script_uses_usd_axis_and_tooltip_formatters(): void
    {
        // Read the Alpine chart script directly so the dashboard keeps explicit U.S. dollar formatting on both axes and tooltips.
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString("style: 'currency'", $script);
        $this->assertStringContainsString("currency: 'USD'", $script);
        $this->assertStringContainsString('minimumFractionDigits: 0', $script);
        $this->assertStringContainsString('maximumFractionDigits: 0', $script);
        $this->assertStringContainsString('minimumFractionDigits: 2', $script);
        $this->assertStringContainsString('maximumFractionDigits: 2', $script);
        $this->assertStringContainsString('formatAxisCurrency', $script);
        $this->assertStringContainsString('formatTooltipCurrency', $script);
        $this->assertStringContainsString('Sales: ${new Intl.NumberFormat(', $script);
        $this->assertStringNotContainsString('toLocaleString(undefined, {', $script);
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

    protected function createWarehouse(Account $account, string $name): Warehouse
    {
        // Use the real warehouse table so dashboard scoping matches production relationships.
        return Warehouse::create([
            'account_id' => $account->id,
            'warehouse_name' => $name,
        ]);
    }

    protected function createProduct(Account $account, string $name, ?string $sku = null): Product
    {
        // Seed products with stable names and SKUs so the dashboard card can assert ordering and display fields.
        return Product::create([
            'account_id' => $account->id,
            'product_name' => $name,
            'sku' => $sku,
        ]);
    }

    protected function postWarehouseInventory(Account $account, Warehouse $warehouse, Product $product, int $quantityDelta): InventoryLedger
    {
        // Write inventory ledger rows directly so the dashboard card exercises the canonical warehouse inventory source.
        return InventoryLedger::create([
            'account_id' => $account->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => InventoryLedger::MOVEMENT_TYPE_ADJUSTMENT,
            'quantity_delta' => $quantityDelta,
            'unit_cost' => 1.0000,
            'total_cost' => (float) $quantityDelta,
            'source_type' => 'test',
            'source_id' => $product->id,
            'movement_at' => now(),
            'notes' => 'Dashboard inventory seed',
        ]);
    }

    protected function createMachineOnlyInventoryTransaction(Account $account, Product $product, int $quantity): Transaction
    {
        // Seed machine inventory without ledger rows so the dashboard test proves warehouse and machine stock stay separate.
        $route = VendingRoute::create([
            'account_id' => $account->id,
            'route_name' => 'Machine Inventory Route',
        ]);

        $location = Location::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_name' => 'Machine Inventory Location',
        ]);

        $machine = Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => 'Snack Machine',
            'serial_number' => 'MACHINE-'.uniqid(),
            'status' => Machine::STATUS_ACTIVE,
        ]);

        $bin = Bin::create([
            'account_id' => $account->id,
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'bin_code' => 'A1',
            'capacity' => 12,
            'price' => 2.00,
        ]);

        $service = Service::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'service_type' => Service::TYPE_LOCATION,
            'service_date' => now()->toDateString(),
            'status' => Service::STATUS_OPEN,
        ]);

        return Transaction::create([
            'account_id' => $account->id,
            'service_id' => $service->id,
            'machine_id' => $machine->id,
            'bin_id' => $bin->id,
            'product_id' => $product->id,
            'transaction_type' => Transaction::TYPE_CURRENT_INVENTORY,
            'quantity' => $quantity,
            'spoilage' => 0,
            'transaction_at' => now(),
            'price' => 2.00,
            'unit_cost' => 1.00,
        ]);
    }

    protected function createServiceSale(
        Account $account,
        Product $product,
        string $salesDate,
        ?string $salesAmount,
        string $calculationStatus = ServiceSale::CALCULATION_CALCULATED,
    ): ServiceSale {
        // Build valid sales rows through the real relationships so dashboard aggregates match production data.
        $route = VendingRoute::create([
            'account_id' => $account->id,
            'route_name' => 'Sales Route '.uniqid(),
        ]);

        $location = Location::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_name' => 'Sales Location '.uniqid(),
        ]);

        $machine = Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => 'Snack Machine',
            'serial_number' => 'SALES-MACHINE-'.uniqid(),
            'status' => Machine::STATUS_ACTIVE,
        ]);

        $bin = Bin::create([
            'account_id' => $account->id,
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'bin_code' => 'BIN-'.uniqid(),
            'capacity' => 20,
            'price' => 2.50,
        ]);

        $service = Service::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'service_type' => Service::TYPE_LOCATION,
            'service_date' => $salesDate,
            'status' => Service::STATUS_COMPLETED,
        ]);

        $previousInventoryTransaction = Transaction::create([
            'account_id' => $account->id,
            'service_id' => $service->id,
            'machine_id' => $machine->id,
            'bin_id' => $bin->id,
            'product_id' => $product->id,
            'transaction_type' => Transaction::TYPE_CURRENT_INVENTORY,
            'quantity' => 20,
            'spoilage' => 0,
            'transaction_at' => $salesDate.' 08:00:00',
            'price' => 2.50,
            'unit_cost' => 1.00,
        ]);

        $countTransaction = Transaction::create([
            'account_id' => $account->id,
            'service_id' => $service->id,
            'machine_id' => $machine->id,
            'bin_id' => $bin->id,
            'product_id' => $product->id,
            'transaction_type' => Transaction::TYPE_COUNT,
            'quantity' => 8,
            'spoilage' => 0,
            'transaction_at' => $salesDate.' 09:00:00',
            'price' => 2.50,
            'unit_cost' => 1.00,
        ]);

        return ServiceSale::create([
            'account_id' => $account->id,
            'service_id' => $service->id,
            'location_id' => $location->id,
            'machine_id' => $machine->id,
            'bin_id' => $bin->id,
            'product_id' => $product->id,
            'previous_inventory_transaction_id' => $calculationStatus === ServiceSale::CALCULATION_BASELINE
                ? null
                : $previousInventoryTransaction->id,
            'count_transaction_id' => $countTransaction->id,
            'calculation_status' => $calculationStatus,
            'calculation_note' => $calculationStatus === ServiceSale::CALCULATION_BASELINE
                ? 'Initial inventory baseline; no previous Current Inventory record was available.'
                : null,
            'sales_date' => $salesDate,
            'opening_quantity' => $calculationStatus === ServiceSale::CALCULATION_BASELINE ? null : 20,
            'spoilage' => 0,
            'counted_quantity' => 8,
            'units_sold' => $calculationStatus === ServiceSale::CALCULATION_BASELINE ? null : 12,
            'unit_price' => '2.50',
            'sales_amount' => $salesAmount,
            'calculation_version' => 'inventory_reconciliation_v1',
            'calculated_at' => $salesDate.' 10:00:00',
        ]);
    }
}
