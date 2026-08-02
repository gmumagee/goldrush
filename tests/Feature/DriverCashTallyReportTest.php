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

class DriverCashTallyReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_attributes_cash_to_service_user_id_not_closed_by_user_id(): void
    {
        $manager = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $driverAlpha = User::factory()->create(['status' => User::STATUS_ACTIVE, 'name' => 'Driver Alpha']);
        $driverBeta = User::factory()->create(['status' => User::STATUS_ACTIVE, 'name' => 'Driver Beta']);

        $account = $this->createAccount('Driver Tally Account');
        $this->attachUserToAccount($manager, $account, AccountUser::ROLE_MANAGER);
        $this->attachUserToAccount($driverAlpha, $account, AccountUser::ROLE_TECHNICIAN);
        $this->attachUserToAccount($driverBeta, $account, AccountUser::ROLE_TECHNICIAN);

        $route = $this->createRoute($account, 'Driver Tally Route');
        $alphaLocation = $this->createLocation($account, $route, 'Alpha Stop');
        $betaLocation = $this->createLocation($account, $route, 'Beta Stop');

        $alphaService = $this->createService($account, $alphaLocation, [
            'service_date' => '2026-07-10',
            'amount_collected' => '12.00',
            'user_id' => $driverAlpha->id,
            'closed_by_user_id' => $manager->id,
        ]);
        $betaService = $this->createService($account, $betaLocation, [
            'service_date' => '2026-07-11',
            'amount_collected' => '18.00',
            'user_id' => $driverBeta->id,
            'closed_by_user_id' => $manager->id,
        ]);
        $unassignedService = $this->createService($account, $betaLocation, [
            'service_date' => '2026-07-12',
            'amount_collected' => '5.00',
            'user_id' => null,
            'closed_by_user_id' => $manager->id,
        ]);

        $response = $this->actingAs($manager)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.driver-cash-tally', [
                'date_from' => '2026-07-10',
                'date_to' => '2026-07-12',
            ]));

        $response
            ->assertOk()
            ->assertSeeTextInOrder(['Driver Beta', 'Driver Alpha', 'Unassigned'])
            ->assertSeeText('Driver Subtotal')
            ->assertSeeText('$18.00')
            ->assertSeeText('$12.00')
            ->assertSeeText('$5.00')
            ->assertSeeText('Grand Total')
            ->assertSeeText('$35.00')
            ->assertSeeText('Alpha Stop')
            ->assertSeeText('Beta Stop')
            ->assertSee('href="'.route('services.show', $alphaService).'"', false)
            ->assertSee('href="'.route('services.show', $betaService).'"', false)
            ->assertSee('href="'.route('services.show', $unassignedService).'"', false)
            ->assertDontSeeText('$30.00');
    }

    public function test_optional_driver_filter_narrows_to_selected_driver(): void
    {
        $manager = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $driverAlpha = User::factory()->create(['status' => User::STATUS_ACTIVE, 'name' => 'Driver Alpha']);
        $driverBeta = User::factory()->create(['status' => User::STATUS_ACTIVE, 'name' => 'Driver Beta']);

        $account = $this->createAccount('Driver Filter Account');
        $this->attachUserToAccount($manager, $account, AccountUser::ROLE_MANAGER);
        $this->attachUserToAccount($driverAlpha, $account, AccountUser::ROLE_TECHNICIAN);
        $this->attachUserToAccount($driverBeta, $account, AccountUser::ROLE_TECHNICIAN);

        $route = $this->createRoute($account, 'Driver Filter Route');
        $location = $this->createLocation($account, $route, 'Filter Stop');

        $alphaService = $this->createService($account, $location, [
            'service_date' => '2026-07-10',
            'amount_collected' => '14.00',
            'user_id' => $driverAlpha->id,
        ]);
        $this->createService($account, $location, [
            'service_date' => '2026-07-11',
            'amount_collected' => '9.00',
            'user_id' => $driverBeta->id,
        ]);

        $response = $this->actingAs($manager)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.driver-cash-tally', [
                'driver_filter' => (string) $driverAlpha->id,
                'date_from' => '2026-07-10',
                'date_to' => '2026-07-12',
            ]));

        $response
            ->assertOk()
            ->assertSeeText('Driver Alpha')
            ->assertSeeText('$14.00')
            ->assertSee('href="'.route('services.show', $alphaService).'"', false)
            ->assertDontSeeText('$9.00');
    }

    public function test_empty_report_shows_no_collections_message(): void
    {
        $manager = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Driver Empty Account');
        $this->attachUserToAccount($manager, $account, AccountUser::ROLE_MANAGER);

        $response = $this->actingAs($manager)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.driver-cash-tally', [
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
        $account = $this->createAccount('Driver Permissions');

        foreach ([AccountUser::ROLE_TECHNICIAN, AccountUser::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
            $this->attachUserToAccount($user, $account, $role);

            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('reports.driver-cash-tally'))
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
            'user_id' => $attributes['user_id'] ?? null,
            'created_by_user_id' => null,
            'closed_by_user_id' => $attributes['closed_by_user_id'] ?? null,
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
