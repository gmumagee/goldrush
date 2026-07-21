<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Location;
use App\Models\RouteLocation;
use App\Models\User;
use App\Models\VendingRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LocationRouteMembershipNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_primary_route_is_managed_through_route_locations_only(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Route Normalization Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $routeA = $this->createRoute($account, 'North Route');
        $routeB = $this->createRoute($account, 'South Route');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('locations.store'), [
                'route_id' => $routeA->id,
                'location_name' => 'Campus Center',
                'address' => '123 Main Street',
                'city' => 'Toronto',
                'state' => 'ON',
                'zip_code' => 'M1M1M1',
            ])
            ->assertRedirect(route('locations.index'));

        $location = Location::query()
            ->where('account_id', $account->id)
            ->where('location_name', 'Campus Center')
            ->firstOrFail();

        $this->assertFalse(Schema::hasColumn('tbl_locations', 'route_id'));
        $this->assertDatabaseHas('tbl_route_locations', [
            'account_id' => $account->id,
            'route_id' => $routeA->id,
            'location_id' => $location->id,
            'is_primary' => 1,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('routes.locations.store', $routeB), [
                'location_id' => $location->id,
            ])
            ->assertRedirect(route('routes.show', $routeB));

        $this->assertDatabaseHas('tbl_route_locations', [
            'account_id' => $account->id,
            'route_id' => $routeB->id,
            'location_id' => $location->id,
            'is_primary' => 0,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->patch(route('locations.update', $location), [
                'route_id' => $routeB->id,
                'location_name' => 'Campus Center',
                'address' => '123 Main Street',
                'city' => 'Toronto',
                'state' => 'ON',
                'zip_code' => 'M1M1M1',
            ])
            ->assertRedirect(route('locations.show', $location));

        $this->assertDatabaseHas('tbl_route_locations', [
            'account_id' => $account->id,
            'route_id' => $routeA->id,
            'location_id' => $location->id,
            'is_primary' => 0,
        ]);
        $this->assertDatabaseHas('tbl_route_locations', [
            'account_id' => $account->id,
            'route_id' => $routeB->id,
            'location_id' => $location->id,
            'is_primary' => 1,
        ]);

        $routeBMembership = RouteLocation::query()
            ->where('account_id', $account->id)
            ->where('route_id', $routeB->id)
            ->where('location_id', $location->id)
            ->firstOrFail();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('routes.locations.destroy', [$routeB, $routeBMembership]))
            ->assertRedirect(route('routes.show', $routeB));

        $this->assertDatabaseHas('tbl_route_locations', [
            'account_id' => $account->id,
            'route_id' => $routeA->id,
            'location_id' => $location->id,
            'is_primary' => 1,
        ]);
    }

    protected function createAccount(string $name): Account
    {
        return Account::create([
            'account_name' => $name,
            'slug' => str($name)->slug().'-'.uniqid(),
            'status' => 'active',
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
}
