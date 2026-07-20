<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Contact;
use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\LocationContact;
use App\Models\LocationDocument;
use App\Models\Machine;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceSale;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationServicesAccordionTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_detail_page_shows_only_account_scoped_services_for_the_location_in_newest_first_order(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE, 'name' => 'Technician User']);
        $closedBy = User::factory()->create(['status' => User::STATUS_ACTIVE, 'name' => 'Closer User']);
        $account = $this->createAccount('Alpha Vending');
        $otherAccount = $this->createAccount('Beta Vending');

        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);
        $this->attachUserToAccount($closedBy, $account, AccountUser::ROLE_MANAGER);

        DataDictionary::create([
            'account_id' => null,
            'name' => 'service_type',
            'value' => Service::TYPE_LOCATION_SERVICE,
            'label' => 'Location Service',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        DataDictionary::create([
            'account_id' => null,
            'name' => 'service_type',
            'value' => Service::TYPE_MAINTENANCE,
            'label' => 'Maintenance Service',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $route = $this->createRoute($account, 'North Route');
        $location = $this->createLocation($account, $route, 'Main Office');
        $otherLocation = $this->createLocation($account, $route, 'Warehouse Annex');
        $foreignLocation = $this->createLocation($otherAccount, $this->createRoute($otherAccount, 'South Route'), 'Foreign Stop');
        $warehouse = $this->createWarehouse($account, 'Main Warehouse');

        $newerService = $this->createService($account, $location, $warehouse, $user, [
            'service_date' => '2026-07-20',
            'status' => Service::STATUS_AWAITING_SERVICE,
        ]);

        $olderService = $this->createService($account, $location, $warehouse, $user, [
            'service_date' => '2026-07-18',
            'status' => Service::STATUS_SERVICE_CLOSED,
            'opened_at' => '2026-07-18 08:00:00',
            'completed_at' => '2026-07-18 09:00:00',
            'closed_at' => '2026-07-18 09:15:00',
            'closed_by_user_id' => $closedBy->id,
            'amount_collected' => 123.45,
        ]);

        $maintenanceService = $this->createService($account, $location, null, $user, [
            'service_type' => Service::TYPE_MAINTENANCE,
            'service_date' => '2026-07-17',
            'status' => Service::STATUS_SERVICE_CLOSED,
            'opened_at' => '2026-07-17 08:00:00',
            'closed_at' => '2026-07-17 08:45:00',
        ]);

        $excludedDifferentLocation = $this->createService($account, $otherLocation, $warehouse, $user, [
            'service_date' => '2026-07-19',
            'status' => Service::STATUS_SERVICE_OPEN,
        ]);

        $excludedDifferentAccount = $this->createService($otherAccount, $foreignLocation, null, null, [
            'service_date' => '2026-07-21',
            'status' => Service::STATUS_AWAITING_SERVICE,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.show', $location));

        $response->assertOk()
            ->assertSeeText('Services')
            ->assertSeeText('Create Service')
            ->assertSee('href="'.route('services.create', ['location_id' => $location->id]).'"', false)
            ->assertSeeTextInOrder(['07-20-2026', '07-18-2026', '07-17-2026'])
            ->assertSeeText('Awaiting Service')
            ->assertSeeText('Service Closed')
            ->assertSeeText('Technician User')
            ->assertSeeText('Service Type')
            ->assertSeeText('Location Service')
            ->assertSeeText('Maintenance Service')
            ->assertSeeText('Assigned Technician')
            ->assertSeeText('Opened At')
            ->assertSeeText('Completed At')
            ->assertSeeText('Closed At')
            ->assertSeeText('Closed By')
            ->assertSeeText('Closer User')
            ->assertSeeText('Amount Collected')
            ->assertSeeText('$123.45')
            ->assertSeeText('N/A')
            ->assertSee('href="'.route('services.show', $newerService).'"', false)
            ->assertSee('href="'.route('services.show', $olderService).'"', false)
            ->assertSee('href="'.route('services.show', $maintenanceService).'"', false)
            ->assertSee('service-accordion--maintenance', false)
            ->assertDontSeeText('Warehouse')
            ->assertDontSeeText('Transactions')
            ->assertDontSeeText('location_service')
            ->assertDontSeeText($otherLocation->location_name)
            ->assertDontSeeText($foreignLocation->location_name)
            ->assertDontSeeText('July 19, 2026')
            ->assertDontSeeText('July 21, 2026');
    }

    public function test_service_create_form_preselects_only_locations_from_the_current_account(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Gamma Vending');
        $otherAccount = $this->createAccount('Delta Vending');

        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $location = $this->createLocation($account, $this->createRoute($account, 'East Route'), 'Selected Stop');
        $foreignLocation = $this->createLocation($otherAccount, $this->createRoute($otherAccount, 'West Route'), 'Foreign Stop');
        $this->createWarehouse($account, 'Main Warehouse');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('services.create', ['location_id' => $location->id]))
            ->assertOk()
            ->assertSee('option value="'.$location->id.'" selected', false);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('services.create', ['location_id' => $foreignLocation->id]))
            ->assertOk()
            ->assertDontSee('option value="'.$foreignLocation->id.'" selected', false);
    }

    public function test_location_detail_uses_initial_installation_wording_for_baseline_only_services(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE, 'name' => 'Owner User']);
        $account = $this->createAccount('Initial Installation Label Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        DataDictionary::create([
            'account_id' => null,
            'name' => 'service_type',
            'value' => Service::TYPE_LOCATION_SERVICE,
            'label' => 'Location Service',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $route = $this->createRoute($account, 'Label Route');
        $location = $this->createLocation($account, $route, 'Label Stop');
        $warehouse = $this->createWarehouse($account, 'Label Warehouse');
        $machine = Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => 'Label Machine',
            'serial_number' => 'LBL-1001',
            'model' => 'Label Model',
            'status' => 'active',
            'installed_on' => '2026-07-01',
        ]);
        $product = Product::create([
            'account_id' => $account->id,
            'vendor_id' => null,
            'sku' => 'label-product',
            'product_name' => 'Label Product',
            'barcode' => '999999',
        ]);
        $bin = \App\Models\Bin::create([
            'account_id' => $account->id,
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'bin_code' => 'A1',
            'capacity' => 12,
            'price' => 2.50,
        ]);
        $service = $this->createService($account, $location, $warehouse, $user, [
            'service_date' => '2026-07-18',
            'status' => Service::STATUS_SERVICE_COMPLETED,
            'completed_at' => '2026-07-18 09:00:00',
        ]);
        $countTransaction = Transaction::create([
            'account_id' => $account->id,
            'service_id' => $service->id,
            'machine_id' => $machine->id,
            'bin_id' => $bin->id,
            'product_id' => $product->id,
            'transaction_type' => Transaction::TYPE_COUNT,
            'quantity' => 6,
            'spoilage' => 0,
            'price' => 2.50,
            'unit_cost' => 0.50,
            'transaction_at' => '2026-07-18 09:00:00',
        ]);

        ServiceSale::create([
            'account_id' => $account->id,
            'service_id' => $service->id,
            'location_id' => $location->id,
            'machine_id' => $machine->id,
            'bin_id' => $bin->id,
            'product_id' => $product->id,
            'previous_inventory_transaction_id' => null,
            'count_transaction_id' => $countTransaction->id,
            'calculation_status' => ServiceSale::CALCULATION_BASELINE,
            'calculation_note' => 'Initial inventory baseline; no previous Current Inventory record was available.',
            'sales_date' => '2026-07-18',
            'opening_quantity' => null,
            'spoilage' => 0,
            'counted_quantity' => 6,
            'units_sold' => null,
            'unit_price' => 2.50,
            'sales_amount' => null,
            'calculation_version' => 'inventory_reconciliation_v1',
            'calculated_at' => '2026-07-18 09:00:00',
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.show', $location))
            ->assertOk()
            ->assertSeeText('Initial Installation')
            ->assertDontSeeText('Baseline');
    }

    public function test_contacts_and_documents_render_as_independent_collapsed_accordions_with_counts(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE, 'name' => 'Owner User']);
        $account = $this->createAccount('Accordion Vending');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $location = $this->createLocation($account, $this->createRoute($account, 'Accordion Route'), 'Accordion Stop');
        $contact = Contact::create([
            'account_id' => $account->id,
            'first_name' => 'Jamie',
            'last_name' => 'Contact',
            'email' => 'jamie@example.com',
        ]);

        LocationContact::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'is_primary' => true,
        ]);

        LocationDocument::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'document_type' => 'Contract',
            'title' => 'Signed Agreement',
            'original_filename' => 'agreement.pdf',
            'stored_filename' => 'agreement-stored.pdf',
            'storage_disk' => 'private',
            'storage_path' => 'location-documents/'.$account->id.'/'.$location->id.'/agreement-stored.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.show', $location))
            ->assertOk()
            ->assertSeeTextInOrder(['Location Summary', 'Contacts', 'Documents', 'Machines', 'Services'])
            ->assertSee('id="locationContactsAccordion"', false)
            ->assertSee('aria-controls="locationContactsCollapse"', false)
            ->assertSee('id="locationContactsCollapse"', false)
            ->assertSee('id="locationDocumentsAccordion"', false)
            ->assertSee('aria-controls="locationDocumentsCollapse"', false)
            ->assertSee('id="locationDocumentsCollapse"', false)
            ->assertSee('id="locationMachinesAccordion"', false)
            ->assertSee('aria-controls="locationMachinesCollapse"', false)
            ->assertSee('id="locationMachinesCollapse"', false)
            ->assertSeeText('Contacts')
            ->assertSeeText('Documents')
            ->assertSeeText('Machines')
            ->assertSeeText('1')
            ->assertSeeText('Attach Existing Contact')
            ->assertSeeText('Add Contact')
            ->assertSeeText('Upload Document')
            ->assertSeeText('Download')
            ->assertSeeText('Add Machine')
            ->assertSee('href="'.route('machines.create', ['location_id' => $location->id]).'"', false)
            ->assertSee(':aria-expanded="open.toString()"', false)
            ->assertSee('x-data="{ open: false }"', false);
    }

    public function test_location_detail_groups_machine_inventory_into_nested_accordions_from_latest_current_inventory_snapshots(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE, 'name' => 'Owner User']);
        $account = $this->createAccount('Inventory Vending');
        $otherAccount = $this->createAccount('Foreign Inventory');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $location = $this->createLocation($account, $this->createRoute($account, 'Inventory Route'), 'Inventory Stop');
        $warehouse = $this->createWarehouse($account, 'Inventory Warehouse');
        $otherLocation = $this->createLocation($otherAccount, $this->createRoute($otherAccount, 'Foreign Route'), 'Foreign Stop');
        $service = $this->createService($account, $location, $warehouse, $user, [
            'service_date' => '2026-07-19',
            'status' => Service::STATUS_SERVICE_OPEN,
        ]);
        $foreignService = $this->createService($otherAccount, $otherLocation, null, null, [
            'service_date' => '2026-07-19',
            'status' => Service::STATUS_SERVICE_OPEN,
        ]);

        $machineOne = Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => 'Combo Machine',
            'serial_number' => 'CM-1001',
            'model' => 'Combo Model',
            'status' => 'active',
            'installed_on' => '2026-07-01',
        ]);

        $machineTwo = Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => 'Snack Machine',
            'serial_number' => 'SM-2002',
            'model' => 'Snack Model',
            'status' => 'repair',
            'installed_on' => '2026-07-02',
        ]);

        $machineWithoutBins = Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => 'Soda Machine',
            'serial_number' => 'SD-3003',
            'model' => 'Soda Model',
            'status' => 'inactive',
            'installed_on' => '2026-07-03',
        ]);

        $cola = Product::create([
            'account_id' => $account->id,
            'vendor_id' => null,
            'sku' => 'cola-1001',
            'product_name' => 'Cola',
            'barcode' => '111111',
        ]);

        $chips = Product::create([
            'account_id' => $account->id,
            'vendor_id' => null,
            'sku' => 'chips-1002',
            'product_name' => 'Chips',
            'barcode' => '222222',
        ]);

        $oldProduct = Product::create([
            'account_id' => $account->id,
            'vendor_id' => null,
            'sku' => 'legacy-1003',
            'product_name' => 'Legacy Product',
            'barcode' => '333333',
        ]);

        $binA1 = \App\Models\Bin::create([
            'account_id' => $account->id,
            'machine_id' => $machineOne->id,
            'product_id' => $cola->id,
            'bin_code' => 'A1',
            'capacity' => 23,
            'price' => 2.15,
        ]);

        $binA2 = \App\Models\Bin::create([
            'account_id' => $account->id,
            'machine_id' => $machineOne->id,
            'product_id' => $chips->id,
            'bin_code' => 'A2',
            'capacity' => 12,
            'price' => 1.80,
        ]);

        $binB1 = \App\Models\Bin::create([
            'account_id' => $account->id,
            'machine_id' => $machineTwo->id,
            'product_id' => $chips->id,
            'bin_code' => 'B1',
            'capacity' => 10,
            'price' => 1.25,
        ]);

        // Seed multiple snapshot and non-snapshot rows so the page must choose the newest current-inventory record only.
        Transaction::create([
            'account_id' => $account->id,
            'service_id' => $service->id,
            'machine_id' => $machineOne->id,
            'bin_id' => $binA1->id,
            'product_id' => $cola->id,
            'transaction_type' => Transaction::TYPE_CURRENT_INVENTORY,
            'quantity' => 4,
            'transaction_at' => '2026-07-19 08:00:00',
            'price' => 9.99,
            'unit_cost' => 0.35,
        ]);

        Transaction::create([
            'account_id' => $account->id,
            'service_id' => $service->id,
            'machine_id' => $machineOne->id,
            'bin_id' => $binA1->id,
            'product_id' => $cola->id,
            'transaction_type' => Transaction::TYPE_CURRENT_INVENTORY,
            'quantity' => 17,
            'transaction_at' => '2026-07-19 09:00:00',
            'price' => 9.99,
            'unit_cost' => 0.35,
        ]);

        Transaction::create([
            'account_id' => $account->id,
            'service_id' => $service->id,
            'machine_id' => $machineOne->id,
            'bin_id' => $binA1->id,
            'product_id' => $cola->id,
            'transaction_type' => Transaction::TYPE_COUNT,
            'quantity' => 9,
            'transaction_at' => '2026-07-19 10:00:00',
            'price' => 2.15,
            'unit_cost' => 0.35,
        ]);

        Transaction::create([
            'account_id' => $account->id,
            'service_id' => $service->id,
            'machine_id' => $machineOne->id,
            'bin_id' => $binA1->id,
            'product_id' => $cola->id,
            'transaction_type' => Transaction::TYPE_FILL,
            'quantity' => 5,
            'transaction_at' => '2026-07-19 11:00:00',
            'price' => 2.15,
            'unit_cost' => 0.35,
        ]);

        Transaction::create([
            'account_id' => $account->id,
            'service_id' => $service->id,
            'machine_id' => $machineOne->id,
            'bin_id' => $binA2->id,
            'product_id' => $oldProduct->id,
            'transaction_type' => Transaction::TYPE_CURRENT_INVENTORY,
            'quantity' => 13,
            'transaction_at' => '2026-07-19 12:00:00',
            'price' => 4.50,
            'unit_cost' => 0.50,
        ]);

        Transaction::create([
            'account_id' => $account->id,
            'service_id' => $service->id,
            'machine_id' => $machineTwo->id,
            'bin_id' => $binB1->id,
            'product_id' => $chips->id,
            'transaction_type' => Transaction::TYPE_CURRENT_INVENTORY,
            'quantity' => 0,
            'transaction_at' => '2026-07-19 12:15:00',
            'price' => 8.75,
            'unit_cost' => 0.20,
        ]);

        // Cross-account rows that point at the same machine and bin IDs must never leak into the location detail page.
        Transaction::create([
            'account_id' => $otherAccount->id,
            'service_id' => $foreignService->id,
            'machine_id' => $machineOne->id,
            'bin_id' => $binA1->id,
            'product_id' => $cola->id,
            'transaction_type' => Transaction::TYPE_CURRENT_INVENTORY,
            'quantity' => 99,
            'transaction_at' => '2026-07-19 13:00:00',
            'price' => 99.99,
            'unit_cost' => 9.99,
        ]);

        DB::enableQueryLog();

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.show', $location));

        $response->assertOk()
            ->assertSee('id="locationMachinesAccordion"', false)
            ->assertSee('id="locationMachinesCollapse"', false)
            ->assertSee('x-data="{ open: false }"', false)
            ->assertSee('id="location-machine-heading-'.$location->id.'-'.$machineOne->id.'"', false)
            ->assertSee('id="location-machine-collapse-'.$location->id.'-'.$machineOne->id.'"', false)
            ->assertSee('id="location-machine-heading-'.$location->id.'-'.$machineTwo->id.'"', false)
            ->assertSee('id="location-machine-collapse-'.$location->id.'-'.$machineTwo->id.'"', false)
            ->assertSeeText('Combo Machine')
            ->assertSeeText('CM-1001')
            ->assertSeeText('Snack Machine')
            ->assertSeeText('SM-2002')
            ->assertSeeText('Soda Machine')
            ->assertSeeText('SD-3003')
            ->assertSeeText('2 bins')
            ->assertSeeText('17 items')
            ->assertSeeText('Inventory unavailable')
            ->assertSeeText('Bin')
            ->assertSeeText('Product')
            ->assertSeeText('Capacity')
            ->assertSeeText('Current Inventory')
            ->assertSeeText('Selling Price')
            ->assertSeeText('Inventory As Of')
            ->assertSeeTextInOrder(['A1', 'Cola', '23', '17', '$2.15'])
            ->assertSeeTextInOrder(['B1', 'Chips', '10', '0', '$1.25'])
            ->assertSeeText('07-19-2026')
            ->assertSeeText('09:00:00')
            ->assertSeeText('12:15:00')
            ->assertSeeText('Machine Total')
            ->assertSeeText('No bins are configured for this machine.')
            ->assertSeeText('View Machine')
            ->assertSee('href="'.route('machines.show', $machineOne).'"', false)
            ->assertDontSeeText('Edit Machine')
            ->assertDontSeeText('Manage Bins')
            ->assertDontSeeText('Add Bin')
            ->assertDontSeeText('Add Bins')
            ->assertDontSee('href="'.route('machines.edit', $machineOne).'"', false)
            ->assertDontSee('href="'.route('machines.bins.edit', $machineOne).'"', false)
            ->assertDontSee('href="'.route('machines.bins.create', $machineOne).'"', false)
            ->assertDontSeeText('Available Capacity')
            ->assertDontSeeText('Available Inventory')
            ->assertDontSeeText('Inventory Value')
            ->assertDontSeeText('Legacy Product')
            ->assertDontSeeText('13')
            ->assertDontSeeText('99')
            ->assertDontSeeText('11:00:00')
            ->assertDontSeeText('10:00:00');

        $inventoryQueries = collect(DB::getQueryLog())
            ->filter(function (array $query) {
                $sql = strtolower($query['query']);

                return (str_contains($sql, 'from "tbl_transactions"') || str_contains($sql, 'from `tbl_transactions`'))
                    && in_array(Transaction::TYPE_CURRENT_INVENTORY, $query['bindings'], true);
            });

        $this->assertCount(1, $inventoryQueries);
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
            'description' => $name.' description',
        ]);
    }

    protected function createLocation(Account $account, VendingRoute $route, string $name): Location
    {
        return Location::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_name' => $name,
            'address' => '123 Main Street',
            'city' => 'Arlington',
            'state' => 'VA',
            'zip_code' => '22201',
        ]);
    }

    protected function createWarehouse(Account $account, string $name): Warehouse
    {
        return Warehouse::create([
            'account_id' => $account->id,
            'warehouse_name' => $name,
            'address' => '10 Storage Way',
            'city' => 'Arlington',
            'state' => 'VA',
            'zip_code' => '22201',
        ]);
    }

    protected function createService(Account $account, Location $location, ?Warehouse $warehouse, ?User $user, array $overrides = []): Service
    {
        return Service::create(array_merge([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => $warehouse?->id,
            'user_id' => $user?->id,
            'closed_by_user_id' => null,
            'service_type' => Service::TYPE_LOCATION_SERVICE,
            'service_date' => '2026-07-18',
            'scheduled_at' => null,
            'opened_at' => null,
            'completed_at' => null,
            'closed_at' => null,
            'amount_collected' => null,
            'status' => Service::STATUS_AWAITING_SERVICE,
        ], $overrides));
    }
}
