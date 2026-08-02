<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Location;
use App\Models\RouteLocation;
use App\Models\Service;
use App\Models\User;
use App\Models\VendingRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesByLocationReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_locations_groups_and_sums_sales_by_location_for_the_selected_date_range(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Sales Report Account');
        $otherAccount = $this->createAccount('Foreign Sales Report Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_MANAGER);

        $route = $this->createRoute($account, 'Sales Route');
        $alpha = $this->createLocation($account, $route, 'Alpha Stop');
        $beta = $this->createLocation($account, $route, 'Beta Stop');
        $inventory = Location::ensureInventoryLocationForAccount($account->id);

        $otherRoute = $this->createRoute($otherAccount, 'Foreign Route');
        $foreignLocation = $this->createLocation($otherAccount, $otherRoute, 'Foreign Stop');

        $this->createService($account, $alpha, [
            'service_date' => '2026-07-10',
            'amount_collected' => '10.00',
        ]);
        $this->createService($account, $alpha, [
            'service_date' => '2026-07-12',
            'amount_collected' => '20.00',
        ]);
        $this->createService($account, $beta, [
            'service_date' => '2026-07-11',
            'amount_collected' => '15.00',
        ]);
        $this->createService($account, $beta, [
            'service_date' => '2026-07-11',
            'amount_collected' => null,
        ]);
        $this->createService($account, $beta, [
            'service_date' => '2026-07-13',
            'amount_collected' => '99.00',
            'service_type' => Service::TYPE_MAINTENANCE,
        ]);
        $this->createService($account, $alpha, [
            'service_date' => '2026-06-30',
            'amount_collected' => '77.00',
        ]);
        $this->createService($otherAccount, $foreignLocation, [
            'service_date' => '2026-07-11',
            'amount_collected' => '500.00',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.sales-by-location', [
                'date_from' => '2026-07-10',
                'date_to' => '2026-07-12',
            ]));

        $response
            ->assertOk()
            ->assertSeeText('Sales by Location')
            ->assertSeeTextInOrder(['Alpha Stop', 'Beta Stop'])
            ->assertSeeText('$30.00')
            ->assertSeeText('$15.00')
            ->assertSeeText('Grand Total')
            ->assertSeeText('$45.00')
            ->assertDontSeeText('Foreign Stop')
            ->assertDontSee('option value="'.$inventory->id.'"', false);

        $response
            ->assertSee('type="date"', false)
            ->assertSee('value="2026-07-10"', false)
            ->assertSee('value="2026-07-12"', false);
    }

    public function test_specific_location_filter_returns_only_that_locations_service_visits(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Sales Location Filter Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Sales Filter Route');
        $alpha = $this->createLocation($account, $route, 'Alpha Stop');
        $beta = $this->createLocation($account, $route, 'Beta Stop');

        $this->createService($account, $alpha, [
            'service_date' => '2026-07-10',
            'amount_collected' => '10.00',
        ]);
        $this->createService($account, $alpha, [
            'service_date' => '2026-07-12',
            'amount_collected' => '20.00',
        ]);
        $this->createService($account, $beta, [
            'service_date' => '2026-07-11',
            'amount_collected' => '15.00',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.sales-by-location', [
                'location_id' => $alpha->id,
                'date_from' => '2026-07-10',
                'date_to' => '2026-07-12',
            ]));

        $response
            ->assertOk()
            ->assertSeeText('Alpha Stop')
            ->assertSeeText('07-10-2026')
            ->assertSeeText('07-12-2026')
            ->assertSeeText('$10.00')
            ->assertSeeText('$20.00')
            ->assertSeeText('Location Total')
            ->assertSeeText('$30.00')
            ->assertDontSeeText('$15.00');
    }

    public function test_empty_location_result_shows_a_clear_message(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Sales Empty Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_MANAGER);

        $route = $this->createRoute($account, 'Sales Empty Route');
        $location = $this->createLocation($account, $route, 'Quiet Stop');

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.sales-by-location', [
                'location_id' => $location->id,
                'date_from' => '2026-07-10',
                'date_to' => '2026-07-12',
            ]));

        $response
            ->assertOk()
            ->assertSeeText('No sales data for this location and date range.')
            ->assertSeeText('$0.00');
    }

    public function test_technicians_and_viewers_receive_forbidden_on_the_report_route(): void
    {
        $account = $this->createAccount('Sales Report Permissions');

        foreach ([AccountUser::ROLE_TECHNICIAN, AccountUser::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
            $this->attachUserToAccount($user, $account, $role);

            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('reports.sales-by-location'))
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
}
