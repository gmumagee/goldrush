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

class CashCollectedReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_lists_collection_events_grouped_by_location_with_subtotals_and_grand_total(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Cash Collected Account');
        $otherAccount = $this->createAccount('Foreign Cash Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_MANAGER);

        $route = $this->createRoute($account, 'Cash Route');
        $alpha = $this->createLocation($account, $route, 'Alpha Stop');
        $beta = $this->createLocation($account, $route, 'Beta Stop');

        $otherRoute = $this->createRoute($otherAccount, 'Foreign Route');
        $foreignLocation = $this->createLocation($otherAccount, $otherRoute, 'Foreign Stop');

        $alphaServiceOne = $this->createService($account, $alpha, [
            'service_date' => '2026-07-10',
            'amount_collected' => '10.00',
        ]);
        $alphaServiceTwo = $this->createService($account, $alpha, [
            'service_date' => '2026-07-12',
            'amount_collected' => '20.00',
        ]);
        $betaService = $this->createService($account, $beta, [
            'service_date' => '2026-07-11',
            'amount_collected' => '15.00',
        ]);
        $this->createService($account, $beta, [
            'service_date' => '2026-07-09',
            'amount_collected' => '99.00',
        ]);
        $this->createService($account, $beta, [
            'service_date' => '2026-07-11',
            'amount_collected' => null,
        ]);
        $this->createService($account, $alpha, [
            'service_date' => '2026-07-11',
            'amount_collected' => '50.00',
            'service_type' => Service::TYPE_MAINTENANCE,
        ]);
        $this->createService($otherAccount, $foreignLocation, [
            'service_date' => '2026-07-11',
            'amount_collected' => '500.00',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.cash-collected', [
                'date_from' => '2026-07-10',
                'date_to' => '2026-07-12',
            ]));

        $response
            ->assertOk()
            ->assertSeeTextInOrder(['Alpha Stop', 'Beta Stop'])
            ->assertSeeText('Location Subtotal')
            ->assertSeeText('$30.00')
            ->assertSeeText('$15.00')
            ->assertSeeText('Grand Total')
            ->assertSeeText('$45.00')
            ->assertSeeText('07-10-2026')
            ->assertSeeText('07-11-2026')
            ->assertSeeText('07-12-2026')
            ->assertSee('href="'.route('services.show', $alphaServiceOne).'"', false)
            ->assertSee('href="'.route('services.show', $alphaServiceTwo).'"', false)
            ->assertSee('href="'.route('services.show', $betaService).'"', false)
            ->assertDontSeeText('Foreign Stop')
            ->assertDontSeeText('500.00')
            ->assertDontSeeText('99.00');
    }

    public function test_optional_location_filter_narrows_to_one_location(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Cash Filter Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Cash Filter Route');
        $alpha = $this->createLocation($account, $route, 'Alpha Stop');
        $beta = $this->createLocation($account, $route, 'Beta Stop');

        $alphaService = $this->createService($account, $alpha, [
            'service_date' => '2026-07-10',
            'amount_collected' => '12.00',
        ]);
        $this->createService($account, $beta, [
            'service_date' => '2026-07-11',
            'amount_collected' => '30.00',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.cash-collected', [
                'location_id' => $alpha->id,
                'date_from' => '2026-07-10',
                'date_to' => '2026-07-12',
            ]));

        $response
            ->assertOk()
            ->assertSeeText('Alpha Stop')
            ->assertSeeText('$12.00')
            ->assertSee('href="'.route('services.show', $alphaService).'"', false)
            ->assertDontSeeText('$30.00');
    }

    public function test_empty_report_shows_no_collections_message(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Cash Empty Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_MANAGER);

        $route = $this->createRoute($account, 'Cash Empty Route');
        $this->createLocation($account, $route, 'Quiet Stop');

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.cash-collected', [
                'date_from' => '2026-07-10',
                'date_to' => '2026-07-12',
            ]));

        $response
            ->assertOk()
            ->assertSeeText('No collections in this date range.')
            ->assertSeeText('$0.00');
    }

    public function test_technicians_and_viewers_receive_forbidden_on_the_report_route(): void
    {
        $account = $this->createAccount('Cash Permissions');

        foreach ([AccountUser::ROLE_TECHNICIAN, AccountUser::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
            $this->attachUserToAccount($user, $account, $role);

            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('reports.cash-collected'))
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
