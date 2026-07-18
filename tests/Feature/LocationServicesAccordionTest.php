<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Contact;
use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\LocationContact;
use App\Models\LocationDocument;
use App\Models\Service;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Warehouse;
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
            ->assertSeeTextInOrder(['July 20, 2026', 'July 18, 2026', 'July 17, 2026'])
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
