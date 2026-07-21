<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Contact;
use App\Models\Location;
use App\Models\LocationContact;
use App\Models\RouteLocation;
use App\Models\User;
use App\Models\VendingRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationSummaryCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_summary_card_uses_account_scoped_primary_contact_and_combined_address(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('North Route');
        $otherAccount = $this->createAccount('South Route');

        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $location = $this->createLocation($account, 'Main Office');

        $primaryContact = Contact::create([
            'account_id' => $account->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
            'phone' => '555-111-1000',
            'mobile_phone' => '555-111-2000',
        ]);

        $foreignContact = Contact::create([
            'account_id' => $otherAccount->id,
            'first_name' => 'Wrong',
            'last_name' => 'Account',
            'email' => 'wrong@example.com',
            'phone' => '555-000-0000',
        ]);

        LocationContact::create([
            'account_id' => $otherAccount->id,
            'location_id' => $location->id,
            'contact_id' => $foreignContact->id,
            'is_primary' => true,
        ]);

        LocationContact::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'contact_id' => $primaryContact->id,
            'is_primary' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.show', $location));

        $response->assertOk()
            ->assertSeeText('Location Summary')
            ->assertSeeText('Name')
            ->assertSeeText('Main Office')
            ->assertSeeText('Address')
            ->assertSeeText('123 Main Street, Arlington, VA 22201')
            ->assertSeeText('Primary Contact')
            ->assertSeeText('Jane Smith')
            ->assertSeeText('Primary Contact Phone')
            ->assertSeeText('555-111-1000')
            ->assertSeeText('Primary Contact Email')
            ->assertSeeText('jane.smith@example.com')
            ->assertSee('mailto:jane.smith@example.com', false)
            ->assertDontSeeText('Primary Route')
            ->assertDontSeeText('Assigned Routes')
            ->assertDontSeeText('Wrong Account')
            ->assertDontSeeText('wrong@example.com');
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

    protected function createLocation(Account $account, string $name): Location
    {
        $route = VendingRoute::create([
            'account_id' => $account->id,
            'route_name' => $name.' Route',
            'description' => $name.' route description',
        ]);

        $location = Location::create([
            'account_id' => $account->id,
            'location_name' => $name,
            'address' => '123 Main Street',
            'city' => 'Arlington',
            'state' => 'VA',
            'zip_code' => '22201',
            'contact_name' => 'Legacy Person',
            'contact_phone' => '555-999-1000',
            'contact_email' => 'legacy@example.com',
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
}
