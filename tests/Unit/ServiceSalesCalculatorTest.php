<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Bin;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Product;
use App\Models\RouteLocation;
use App\Models\Service;
use App\Models\ServiceSale;
use App\Models\Transaction;
use App\Models\VendingRoute;
use App\Models\Warehouse;
use App\Services\ServiceSalesCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceSalesCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_units_and_sales_by_subtracting_count_and_spoilage(): void
    {
        [$account, $location, $warehouse] = $this->createLocationFixture();
        $machine = $this->createMachine($account, $location, 'Snack');
        $product = $this->createProduct($account, 'Cola');
        $bin = $this->createBin($account, $machine, $product, 'A1', 20, 2.00);
        $previousService = $this->createService($account, $location, $warehouse, '2026-07-17', Service::STATUS_SERVICE_CLOSED);
        $service = $this->createService($account, $location, $warehouse, '2026-07-18', Service::STATUS_SERVICE_OPEN);

        $this->createTransaction($account, $previousService, $bin, Transaction::TYPE_CURRENT_INVENTORY, 20, '2026-07-17 09:00:00');
        $this->createTransaction($account, $service, $bin, Transaction::TYPE_COUNT, 8, '2026-07-18 09:00:00', 2);

        $result = app(ServiceSalesCalculator::class)->calculate($service);

        $this->assertSame([], $result['errors']);
        $this->assertSame(2000, $result['sales_total_cents']);
        $this->assertCount(1, $result['lines']);
        $this->assertSame(2, $result['lines'][0]['spoilage']);
        $this->assertSame(10, $result['lines'][0]['units_sold']);
        $this->assertSame(2000, $result['lines'][0]['sales_amount_cents']);
    }

    public function test_calculates_multiple_bins_and_spoilage_separately(): void
    {
        [$account, $location, $warehouse] = $this->createLocationFixture();
        $machine = $this->createMachine($account, $location, 'Snack');
        $productA = $this->createProduct($account, 'Cola');
        $productB = $this->createProduct($account, 'Water');
        $binA = $this->createBin($account, $machine, $productA, 'A1', 20, 2.00);
        $binB = $this->createBin($account, $machine, $productB, 'A2', 15, 1.50);
        $previousService = $this->createService($account, $location, $warehouse, '2026-07-17', Service::STATUS_SERVICE_CLOSED);
        $service = $this->createService($account, $location, $warehouse, '2026-07-18', Service::STATUS_SERVICE_OPEN);

        $this->createTransaction($account, $previousService, $binA, Transaction::TYPE_CURRENT_INVENTORY, 20, '2026-07-17 09:00:00');
        $this->createTransaction($account, $previousService, $binB, Transaction::TYPE_CURRENT_INVENTORY, 10, '2026-07-17 09:05:00');

        $this->createTransaction($account, $service, $binA, Transaction::TYPE_COUNT, 8, '2026-07-18 09:00:00');
        $this->createTransaction($account, $service, $binB, Transaction::TYPE_COUNT, 7, '2026-07-18 09:30:00', 1);

        $result = app(ServiceSalesCalculator::class)->calculate($service);

        $this->assertSame([], $result['errors']);
        $this->assertCount(2, $result['lines']);
        $this->assertSame(2700, $result['sales_total_cents']);

        $linesByBin = collect($result['lines'])->keyBy('bin_id');
        $this->assertSame(12, $linesByBin[$binA->id]['units_sold']);
        $this->assertSame(0, $linesByBin[$binA->id]['spoilage']);
        $this->assertSame(2, $linesByBin[$binB->id]['units_sold']);
        $this->assertSame(1, $linesByBin[$binB->id]['spoilage']);
        $this->assertSame(300, $linesByBin[$binB->id]['sales_amount_cents']);
    }

    public function test_post_count_fill_does_not_increase_units_sold_and_spoilage_does_not_enter_closing_inventory(): void
    {
        [$account, $location, $warehouse] = $this->createLocationFixture();
        $machine = $this->createMachine($account, $location, 'Snack');
        $product = $this->createProduct($account, 'Cola');
        $bin = $this->createBin($account, $machine, $product, 'A1', 20, 2.00);
        $previousService = $this->createService($account, $location, $warehouse, '2026-07-17', Service::STATUS_SERVICE_CLOSED);
        $service = $this->createService($account, $location, $warehouse, '2026-07-18', Service::STATUS_SERVICE_OPEN);

        $this->createTransaction($account, $previousService, $bin, Transaction::TYPE_CURRENT_INVENTORY, 20, '2026-07-17 09:00:00');
        $this->createTransaction($account, $service, $bin, Transaction::TYPE_COUNT, 8, '2026-07-18 09:00:00', 2);
        $this->createTransaction($account, $service, $bin, Transaction::TYPE_FILL, 10, '2026-07-18 09:30:00');

        $result = app(ServiceSalesCalculator::class)->calculate($service);

        $this->assertSame([], $result['errors']);
        $this->assertSame(2000, $result['sales_total_cents']);
        $this->assertSame(2, $result['lines'][0]['spoilage']);
        $this->assertSame(10, $result['lines'][0]['units_sold']);
        $this->assertSame(18, $result['lines'][0]['closing_quantity']);
        $this->assertSame(1, $result['lines'][0]['previous_inventory_transaction_id']);
    }

    public function test_missing_opening_inventory_creates_a_baseline_line(): void
    {
        [$account, $location, $warehouse] = $this->createLocationFixture();
        $machine = $this->createMachine($account, $location, 'Snack');
        $product = $this->createProduct($account, 'Cola');
        $bin = $this->createBin($account, $machine, $product, 'A1', 20, 2.00);
        $service = $this->createService($account, $location, $warehouse, '2026-07-18', Service::STATUS_SERVICE_OPEN);

        $this->createTransaction($account, $service, $bin, Transaction::TYPE_COUNT, 8, '2026-07-18 09:00:00', 2);

        $result = app(ServiceSalesCalculator::class)->calculate($service);

        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['lines']);
        $this->assertSame(ServiceSale::CALCULATION_BASELINE, $result['lines'][0]['calculation_status']);
        $this->assertNull($result['lines'][0]['opening_quantity']);
        $this->assertSame(2, $result['lines'][0]['spoilage']);
        $this->assertNull($result['lines'][0]['units_sold']);
        $this->assertNull($result['lines'][0]['sales_amount_cents']);
        $this->assertSame(8, $result['lines'][0]['closing_quantity']);
    }

    public function test_count_plus_spoilage_greater_than_opening_inventory_returns_a_blocking_error(): void
    {
        [$account, $location, $warehouse] = $this->createLocationFixture();
        $machine = $this->createMachine($account, $location, 'Snack');
        $product = $this->createProduct($account, 'Cola');
        $bin = $this->createBin($account, $machine, $product, 'A1', 20, 2.00);
        $previousService = $this->createService($account, $location, $warehouse, '2026-07-17', Service::STATUS_SERVICE_CLOSED);
        $service = $this->createService($account, $location, $warehouse, '2026-07-18', Service::STATUS_SERVICE_OPEN);

        $this->createTransaction($account, $previousService, $bin, Transaction::TYPE_CURRENT_INVENTORY, 5, '2026-07-17 09:00:00');
        $this->createTransaction($account, $service, $bin, Transaction::TYPE_COUNT, 4, '2026-07-18 09:00:00', 2);

        $result = app(ServiceSalesCalculator::class)->calculate($service);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Count plus Spoilage greater than its opening inventory', $result['errors'][0]);
    }

    public function test_mixed_services_sum_only_calculated_lines(): void
    {
        [$account, $location, $warehouse] = $this->createLocationFixture();
        $machine = $this->createMachine($account, $location, 'Snack');
        $productA = $this->createProduct($account, 'Cola');
        $productB = $this->createProduct($account, 'Water');
        $binA = $this->createBin($account, $machine, $productA, 'A1', 20, 2.00);
        $binB = $this->createBin($account, $machine, $productB, 'A2', 15, 1.50);
        $previousService = $this->createService($account, $location, $warehouse, '2026-07-17', Service::STATUS_SERVICE_CLOSED);
        $service = $this->createService($account, $location, $warehouse, '2026-07-18', Service::STATUS_SERVICE_OPEN);

        $this->createTransaction($account, $previousService, $binA, Transaction::TYPE_CURRENT_INVENTORY, 20, '2026-07-17 09:00:00');
        $this->createTransaction($account, $service, $binA, Transaction::TYPE_COUNT, 8, '2026-07-18 09:00:00', 1);
        $this->createTransaction($account, $service, $binB, Transaction::TYPE_COUNT, 7, '2026-07-18 09:05:00', 2);

        $result = app(ServiceSalesCalculator::class)->calculate($service);

        $this->assertSame([], $result['errors']);
        $this->assertCount(2, $result['lines']);
        $this->assertSame(2200, $result['sales_total_cents']);
        $this->assertSame([ServiceSale::CALCULATION_BASELINE, ServiceSale::CALCULATION_CALCULATED], collect($result['lines'])->pluck('calculation_status')->sort()->values()->all());
    }

    public function test_maintenance_services_return_no_sales_lines(): void
    {
        [$account, $location, $warehouse] = $this->createLocationFixture();
        $service = $this->createService($account, $location, $warehouse, '2026-07-18', Service::STATUS_SERVICE_OPEN, Service::TYPE_MAINTENANCE);

        $result = app(ServiceSalesCalculator::class)->calculate($service);

        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['lines']);
        $this->assertNotEmpty($result['warnings']);
    }

    protected function createLocationFixture(): array
    {
        $account = Account::create([
            'account_name' => 'Sales Account',
            'slug' => 'sales-account-'.uniqid(),
            'status' => 'active',
            'billing_email' => 'sales-'.uniqid().'@example.com',
        ]);

        $route = VendingRoute::create([
            'account_id' => $account->id,
            'route_name' => 'North Route',
            'description' => 'North Route description',
        ]);

        $location = Location::create([
            'account_id' => $account->id,
            'location_name' => 'Campus Center',
            'address' => '123 Service Road',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M1M1M1',
            'contact_name' => 'Casey Tech',
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

        $warehouse = Warehouse::create([
            'account_id' => $account->id,
            'warehouse_name' => 'Main Warehouse',
            'address' => '10 Storage Way',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M1M1M1',
        ]);

        return [$account, $location, $warehouse];
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

    protected function createProduct(Account $account, string $name): Product
    {
        return Product::create([
            'account_id' => $account->id,
            'vendor_id' => null,
            'sku' => strtolower($name).'-'.uniqid(),
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

    protected function createService(
        Account $account,
        Location $location,
        Warehouse $warehouse,
        string $serviceDate,
        string $status,
        string $serviceType = Service::TYPE_LOCATION
    ): Service {
        return Service::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => $serviceType === Service::TYPE_LOCATION ? $warehouse->id : null,
            'user_id' => null,
            'service_type' => $serviceType,
            'service_date' => $serviceDate,
            'opened_at' => '2026-07-18 08:00:00',
            'completed_at' => $status === Service::STATUS_SERVICE_CLOSED ? '2026-07-18 09:00:00' : null,
            'closed_at' => $status === Service::STATUS_SERVICE_CLOSED ? '2026-07-18 10:00:00' : null,
            'status' => $status,
        ]);
    }

    protected function createTransaction(
        Account $account,
        Service $service,
        Bin $bin,
        string $type,
        int $quantity,
        string $transactionAt,
        int $spoilage = 0
    ): Transaction {
        return Transaction::create([
            'account_id' => $account->id,
            'service_id' => $service->id,
            'machine_id' => $bin->machine_id,
            'bin_id' => $bin->id,
            'product_id' => $bin->product_id,
            'transaction_type' => $type,
            'quantity' => $quantity,
            'spoilage' => $spoilage,
            'transaction_at' => $transactionAt,
            'price' => $bin->price,
            'unit_cost' => null,
        ]);
    }
}
