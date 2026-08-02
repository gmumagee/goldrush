<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\Location;
use App\Models\LocationContact;
use App\Models\Machine;
use App\Models\Product;
use App\Models\RouteLocation;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Vendor;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_import_export_screen_lists_exportable_entities_and_import_tools_for_manage_roles(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Import Export Screen Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('import-export.index'));

        $response
            ->assertOk()
            ->assertSeeText('Import / Export')
            ->assertSeeText('Export')
            ->assertSeeText('Import')
            ->assertSeeText('Products')
            ->assertSeeText('Machines')
            ->assertSeeText('Locations')
            ->assertSeeText('Contacts')
            ->assertSeeText('Expenses')
            ->assertSeeText('Import order matters')
            ->assertSee('type="file"', false)
            ->assertSeeText('Analyze')
            ->assertSeeText('The import file format matches export');
    }

    public function test_import_export_screen_shows_import_unavailable_message_for_export_only_roles(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Import Export Viewer Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_VIEWER);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('import-export.index'));

        $response
            ->assertOk()
            ->assertSeeText('Import unavailable')
            ->assertDontSee('type="file"', false);
    }

    public function test_technician_cannot_access_import_export_screen_or_direct_exports(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Import Export Authorization Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_TECHNICIAN);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('import-export.index'))
            ->assertForbidden();

        foreach (['products', 'machines', 'locations', 'contacts', 'expenses'] as $entity) {
            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('import-export.export', ['entity' => $entity]))
                ->assertForbidden();
        }
    }

    public function test_products_export_streams_account_scoped_csv_with_human_readable_vendor_names_and_nulls(): void
    {
        Carbon::setTestNow('2026-07-27 09:00:00');
        CarbonImmutable::setTestNow('2026-07-27 09:00:00');

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Export Product Account');
        $otherAccount = $this->createAccount('Export Product Foreign');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $vendor = $this->createVendor($account, 'North Soda Supply');
        $foreignVendor = $this->createVendor($otherAccount, 'Foreign Supplier');

        $product = $this->createProduct($account, [
            'vendor_id' => $vendor->id,
            'category' => 'Beverage',
            'brand' => 'Acme',
            'sku' => 'EXP-SKU-100',
            'product_name' => 'Cola',
            'size' => '12 oz',
            'package_type' => 'Can',
            'barcode' => '1234567890',
            'reorder_point' => 18,
        ]);
        $blankProduct = $this->createProduct($account, [
            'vendor_id' => null,
            'category' => 'Snack',
            'brand' => null,
            'sku' => 'EXP-SKU-200',
            'product_name' => 'Pretzels',
            'size' => null,
            'package_type' => null,
            'barcode' => null,
            'reorder_point' => null,
        ]);
        $foreignProduct = $this->createProduct($otherAccount, [
            'vendor_id' => $foreignVendor->id,
            'sku' => 'SKU-300',
            'product_name' => 'Foreign Cola',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('import-export.export', ['entity' => 'products', 'search' => 'EXP-SKU-']));

        $response
            ->assertOk()
            ->assertDownload('products-'.$account->slug.'-2026-07-27.csv')
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $rows = $this->parseCsv($response);

        $this->assertSame([
            'sku',
            'category',
            'brand',
            'product_name',
            'size',
            'package_type',
            'barcode',
            'reorder_point',
            'vendor_name',
        ], $rows[0]);
        $this->assertCount(3, $rows);
        $this->assertContains([
            $product->sku,
            'Beverage',
            'Acme',
            'Cola',
            '12 oz',
            'Can',
            '1234567890',
            '18',
            $vendor->vendor_name,
        ], $rows);
        $this->assertContains([
            $blankProduct->sku,
            'Snack',
            '',
            'Pretzels',
            '',
            '',
            '',
            '',
            '',
        ], $rows);
        $this->assertNotContains([
            $foreignProduct->sku,
            $foreignProduct->category,
            $foreignProduct->brand,
            $foreignProduct->product_name,
            $foreignProduct->size,
            $foreignProduct->package_type,
            $foreignProduct->barcode,
            (string) $foreignProduct->reorder_point,
            $foreignVendor->vendor_name,
        ], $rows);
    }

    public function test_machines_export_uses_location_names_and_y_m_d_dates(): void
    {
        Carbon::setTestNow('2026-07-27 09:00:00');
        CarbonImmutable::setTestNow('2026-07-27 09:00:00');

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Export Machine Account');
        $otherAccount = $this->createAccount('Export Machine Foreign');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Machine Export Route');
        $location = $this->createCustomerLocation($account, $route, 'Machine Export Stop');
        $inventoryLocation = $account->inventoryLocation()->firstOrFail();
        $otherInventoryLocation = $otherAccount->inventoryLocation()->firstOrFail();

        $inventoryMachine = $this->createMachine($account, $inventoryLocation, [
            'type' => 'snack',
            'serial_number' => 'INV-100',
            'key_number' => 'KEY-INV',
            'telemetry_id' => 'TEL-INV-100',
            'model' => 'Inventory Model',
            'status' => Machine::STATUS_ACTIVE,
            'installed_on' => '2026-07-01',
        ]);
        $deployedMachine = $this->createMachine($account, $location, [
            'type' => 'combo',
            'serial_number' => 'DEP-200',
            'key_number' => null,
            'telemetry_id' => null,
            'model' => 'Deployed Model',
            'status' => Machine::STATUS_REPAIR,
            'installed_on' => null,
        ]);
        $foreignMachine = $this->createMachine($otherAccount, $otherInventoryLocation, [
            'type' => 'soda',
            'serial_number' => 'FOR-300',
            'key_number' => 'KEY-FOR',
            'telemetry_id' => 'TEL-FOR-300',
            'model' => 'Foreign Model',
            'status' => Machine::STATUS_ACTIVE,
            'installed_on' => '2026-07-02',
        ]);

        $rows = $this->parseCsv(
            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('import-export.export', ['entity' => 'machines']))
                ->assertOk()
                ->assertDownload('machines-'.$account->slug.'-2026-07-27.csv')
        );

        $this->assertSame([
            'serial_number',
            'key_number',
            'telemetry_id',
            'type',
            'model',
            'status',
            'installed_on',
            'location_name',
        ], $rows[0]);
        $this->assertCount(3, $rows);
        $this->assertContains([
            'INV-100',
            'KEY-INV',
            'TEL-INV-100',
            'snack',
            'Inventory Model',
            Machine::STATUS_ACTIVE,
            '2026-07-01',
            $inventoryLocation->location_name,
        ], $rows);
        $this->assertContains([
            'DEP-200',
            '',
            '',
            'combo',
            'Deployed Model',
            Machine::STATUS_REPAIR,
            '',
            $location->location_name,
        ], $rows);
        $this->assertFalse(collect($rows)->contains(fn (array $row) => ($row[0] ?? null) === $foreignMachine->serial_number));
        $this->assertContains($inventoryLocation->location_name, collect($rows)->pluck(7)->filter()->all());
    }

    public function test_expenses_export_streams_general_and_location_tied_rows_for_the_current_account_only(): void
    {
        Carbon::setTestNow('2026-08-02 10:00:00');
        CarbonImmutable::setTestNow('2026-08-02 10:00:00');

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Export Expense Account');
        $otherAccount = $this->createAccount('Export Expense Foreign');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Expense Export Route');
        $location = $this->createCustomerLocation($account, $route, 'Expense Export Stop');
        $otherRoute = $this->createRoute($otherAccount, 'Other Expense Export Route');
        $otherLocation = $this->createCustomerLocation($otherAccount, $otherRoute, 'Foreign Expense Stop');

        $generalExpense = $this->createExpense($account, [
            'location_id' => null,
            'category' => Expense::CATEGORY_FUEL,
            'amount' => 44.10,
            'expense_date' => '2026-08-02',
            'vendor' => 'Shell',
            'description' => 'General expense',
        ]);
        $locationExpense = $this->createExpense($account, [
            'location_id' => $location->id,
            'category' => Expense::CATEGORY_MAINTENANCE,
            'amount' => 19.75,
            'expense_date' => '2026-08-01',
            'vendor' => 'FixIt',
            'description' => 'Location expense',
        ]);
        $foreignExpense = $this->createExpense($otherAccount, [
            'location_id' => $otherLocation->id,
            'category' => Expense::CATEGORY_RENT,
            'amount' => 88.00,
            'expense_date' => '2026-08-02',
            'vendor' => 'Foreign Vendor',
            'description' => 'Foreign expense',
        ]);

        $rows = $this->parseCsv(
            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('import-export.export', ['entity' => 'expenses']))
                ->assertOk()
                ->assertDownload('expenses-'.$account->slug.'-2026-08-02.csv')
        );

        $this->assertSame([
            'expense_date',
            'category',
            'amount',
            'location_name',
            'vendor',
            'description',
        ], $rows[0]);
        $this->assertContains([
            '2026-08-02',
            Expense::CATEGORY_FUEL,
            '44.10',
            'General',
            'Shell',
            'General expense',
        ], $rows);
        $this->assertContains([
            '2026-08-01',
            Expense::CATEGORY_MAINTENANCE,
            '19.75',
            $location->location_name,
            'FixIt',
            'Location expense',
        ], $rows);
        $this->assertNotContains([
            '2026-08-02',
            $foreignExpense->category,
            '88.00',
            $otherLocation->location_name,
            'Foreign Vendor',
            'Foreign expense',
        ], $rows);
        $this->assertCount(3, $rows);
        $this->assertTrue($generalExpense->exists);
        $this->assertTrue($locationExpense->exists);
    }

    public function test_locations_export_excludes_inventory_location_and_includes_primary_route_and_contact_summary(): void
    {
        Carbon::setTestNow('2026-07-27 09:00:00');
        CarbonImmutable::setTestNow('2026-07-27 09:00:00');

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Export Location Account');
        $otherAccount = $this->createAccount('Export Location Foreign');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Location Export Route');
        $location = $this->createCustomerLocation($account, $route, 'Location Export Stop');
        $inventoryLocation = $account->inventoryLocation()->firstOrFail();
        $this->createCustomerLocation($otherAccount, $this->createRoute($otherAccount, 'Foreign Route'), 'Foreign Stop');

        $contact = $this->createContact($account, [
            'first_name' => 'Jamie',
            'last_name' => 'Jones',
            'organization' => 'JJ Ops',
            'title' => 'Manager',
            'email' => 'jamie@example.com',
            'phone' => '555-1000',
            'mobile_phone' => '555-2000',
        ]);
        $this->attachContactToLocation($account, $location, $contact, 'Manager', true);

        $rows = $this->parseCsv(
            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('import-export.export', ['entity' => 'locations']))
                ->assertOk()
                ->assertDownload('locations-'.$account->slug.'-2026-07-27.csv')
        );

        $this->assertSame([
            'location_name',
            'address',
            'city',
            'state',
            'zip_code',
            'primary_route_name',
            'primary_contact_name',
            'primary_contact_email',
            'primary_contact_phone',
        ], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame([
            $location->location_name,
            $location->address,
            $location->city,
            $location->state,
            $location->zip_code,
            $route->route_name,
            $contact->display_name,
            $contact->email,
            $contact->phone,
        ], $rows[1]);
        $this->assertFalse(collect($rows)->contains(fn (array $row) => ($row[0] ?? null) === $inventoryLocation->location_name));
    }

    public function test_contacts_export_uses_one_row_per_contact_location_pair_without_data_loss(): void
    {
        Carbon::setTestNow('2026-07-27 09:00:00');
        CarbonImmutable::setTestNow('2026-07-27 09:00:00');

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Export Contact Account');
        $otherAccount = $this->createAccount('Export Contact Foreign');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Contact Export Route');
        $locationA = $this->createCustomerLocation($account, $route, 'Contact Stop A');
        $locationB = $this->createCustomerLocation($account, $route, 'Contact Stop B');
        $foreignLocation = $otherAccount->inventoryLocation()->firstOrFail();

        $contact = $this->createContact($account, [
            'first_name' => 'Alex',
            'last_name' => 'Taylor',
            'organization' => 'AT Services',
            'title' => 'Coordinator',
            'email' => 'alex@example.com',
            'phone' => '555-3000',
            'mobile_phone' => '555-4000',
        ]);
        $standaloneContact = $this->createContact($account, [
            'first_name' => 'Solo',
            'last_name' => 'Contact',
            'organization' => null,
            'title' => null,
            'email' => 'solo@example.com',
            'phone' => null,
            'mobile_phone' => null,
        ]);
        $foreignContact = $this->createContact($otherAccount, [
            'first_name' => 'Foreign',
            'last_name' => 'Person',
            'email' => 'foreign@example.com',
        ]);

        $this->attachContactToLocation($account, $locationA, $contact, 'Manager', true);
        $this->attachContactToLocation($account, $locationB, $contact, 'Backup', false);
        $this->attachContactToLocation($otherAccount, $foreignLocation, $foreignContact, 'Foreign', true);

        $rows = $this->parseCsv(
            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('import-export.export', ['entity' => 'contacts']))
                ->assertOk()
                ->assertDownload('contacts-'.$account->slug.'-2026-07-27.csv')
        );

        $this->assertSame([
            'first_name',
            'last_name',
            'organization',
            'title',
            'email',
            'phone',
            'mobile_phone',
            'location_name',
            'contact_role',
            'is_primary',
        ], $rows[0]);
        $this->assertCount(4, $rows);
        $this->assertContains([
            'Alex',
            'Taylor',
            'AT Services',
            'Coordinator',
            'alex@example.com',
            '555-3000',
            '555-4000',
            $locationA->location_name,
            'Manager',
            '1',
        ], $rows);
        $this->assertContains([
            'Alex',
            'Taylor',
            'AT Services',
            'Coordinator',
            'alex@example.com',
            '555-3000',
            '555-4000',
            $locationB->location_name,
            'Backup',
            '0',
        ], $rows);
        $this->assertContains([
            'Solo',
            'Contact',
            '',
            '',
            'solo@example.com',
            '',
            '',
            '',
            '',
            '',
        ], $rows);
        $this->assertFalse(collect($rows)->contains(fn (array $row) => ($row[4] ?? null) === 'foreign@example.com'));
        $this->assertTrue($standaloneContact->exists);
    }

    protected function parseCsv(TestResponse $response): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $response->streamedContent());
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
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

    protected function createVendor(Account $account, string $name): Vendor
    {
        return Vendor::create([
            'account_id' => $account->id,
            'vendor_name' => $name,
            'location' => $name.' Warehouse',
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

    protected function createCustomerLocation(Account $account, VendingRoute $route, string $name): Location
    {
        $location = Location::create([
            'account_id' => $account->id,
            'location_name' => $name,
            'address' => '123 Main Street',
            'city' => 'New York',
            'state' => 'NY',
            'zip_code' => '10001',
            'is_inventory' => null,
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

    protected function createProduct(Account $account, array $attributes): Product
    {
        return Product::create(array_merge([
            'account_id' => $account->id,
            'vendor_id' => null,
            'category' => 'Beverage',
            'brand' => 'Brand',
            'sku' => uniqid('sku-', true),
            'product_name' => 'Product',
            'size' => null,
            'package_type' => null,
            'barcode' => null,
            'reorder_point' => null,
        ], $attributes));
    }

    protected function createMachine(Account $account, Location $location, array $attributes): Machine
    {
        return Machine::create(array_merge([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => 'snack',
            'serial_number' => uniqid('machine-', true),
            'model' => 'Model',
            'status' => Machine::STATUS_ACTIVE,
            'installed_on' => null,
        ], $attributes));
    }

    protected function createContact(Account $account, array $attributes): Contact
    {
        return Contact::create(array_merge([
            'account_id' => $account->id,
            'first_name' => 'First',
            'last_name' => 'Last',
            'organization' => null,
            'title' => null,
            'email' => null,
            'phone' => null,
            'mobile_phone' => null,
            'notes' => null,
        ], $attributes));
    }

    protected function createExpense(Account $account, array $attributes): Expense
    {
        $expense = new Expense([
            'location_id' => $attributes['location_id'] ?? null,
            'category' => $attributes['category'] ?? Expense::CATEGORY_OTHER,
            'amount' => $attributes['amount'] ?? 10.00,
            'expense_date' => $attributes['expense_date'] ?? '2026-08-02',
            'vendor' => $attributes['vendor'] ?? null,
            'description' => $attributes['description'] ?? null,
        ]);

        $expense->account_id = $account->id;
        $expense->created_by_user_id = $attributes['created_by_user_id'] ?? null;
        $expense->save();

        return $expense;
    }

    protected function attachContactToLocation(
        Account $account,
        Location $location,
        Contact $contact,
        string $role,
        bool $isPrimary
    ): LocationContact {
        return LocationContact::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'contact_role' => $role,
            'is_primary' => $isPrimary,
            'notes' => null,
        ]);
    }
}
