<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Location;
use App\Models\LocationAccessHour;
use App\Models\RouteLocation;
use App\Models\User;
use App\Models\VendingRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LocationOperationalMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_create_saves_full_week_access_hours_service_pattern_and_sales_tax(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Location Metadata Create');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);
        $route = $this->createRoute($account, 'Create Route');

        $hours = [];

        foreach ([1, 2, 3, 4, 5, 6, 0] as $dayOfWeek) {
            $hours[$dayOfWeek] = [
                'is_open' => '1',
                'opens_at' => '09:00',
                'closes_at' => '17:00',
            ];
        }

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('locations.store'), [
                'route_id' => $route->id,
                'location_name' => 'Campus Center',
                'address' => '123 Main Street',
                'city' => 'Toronto',
                'state' => 'ON',
                'zip_code' => 'M1M1M1',
                'service_pattern_type' => 'weekly',
                'sales_tax_rate_percent' => '8.25',
                'commission_rate_percent' => '15',
                'commission_on_net' => '1',
                'access_hours' => $hours,
            ])
            ->assertRedirect(route('locations.index'))
            ->assertSessionHasNoErrors();

        $location = Location::query()
            ->where('account_id', $account->id)
            ->where('location_name', 'Campus Center')
            ->firstOrFail();

        $this->assertTrue(Schema::hasColumns('tbl_locations', ['service_interval_days', 'sales_tax_rate', 'commission_rate', 'commission_on_net']));
        $this->assertFalse(Schema::hasColumn('tbl_locations', 'service_anchor_date'));
        $this->assertTrue(Schema::hasTable('tbl_location_access_hours'));

        $this->assertDatabaseHas('tbl_locations', [
            'id' => $location->id,
            'service_interval_days' => 7,
            'sales_tax_rate' => 0.0825,
            'commission_rate' => 0.1500,
            'commission_on_net' => 1,
        ]);
        $this->assertSame(7, LocationAccessHour::query()->where('account_id', $account->id)->where('location_id', $location->id)->count());

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.show', $location))
            ->assertOk()
            ->assertSeeText('Location Summary')
            ->assertSeeText('Weekly service')
            ->assertSeeText('8.25%')
            ->assertSeeText('15% of net sales')
            ->assertSeeText('Mon')
            ->assertSeeText('9:00 AM - 5:00 PM')
            ->assertSeeText('Sun');
    }

    public function test_location_update_saves_partial_week_and_custom_service_pattern(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Location Metadata Update');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);
        $route = $this->createRoute($account, 'Update Route');
        $location = $this->createLocation($account, 'Office Park');

        RouteLocation::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_id' => $location->id,
            'stop_order' => 1,
            'is_primary' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->patch(route('locations.update', $location), [
                'route_id' => $route->id,
                'location_name' => 'Office Park',
                'address' => '123 Main Street',
                'city' => 'Toronto',
                'state' => 'ON',
                'zip_code' => 'M1M1M1',
                'service_pattern_type' => 'custom',
                'service_interval_days_custom' => '21',
                'sales_tax_rate_percent' => '',
                'commission_rate_percent' => '12.5',
                'access_hours' => [
                    1 => ['is_open' => '1', 'opens_at' => '08:30', 'closes_at' => '16:00'],
                    3 => ['is_open' => '1', 'opens_at' => '10:00', 'closes_at' => '18:30'],
                ],
            ])
            ->assertRedirect(route('locations.show', $location))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_locations', [
            'id' => $location->id,
            'service_interval_days' => 21,
            'sales_tax_rate' => null,
            'commission_rate' => 0.1250,
            'commission_on_net' => 0,
        ]);
        $this->assertSame(2, LocationAccessHour::query()->where('account_id', $account->id)->where('location_id', $location->id)->count());
        $this->assertDatabaseHas('tbl_location_access_hours', [
            'account_id' => $account->id,
            'location_id' => $location->id,
            'day_of_week' => 1,
            'opens_at' => '08:30:00',
            'closes_at' => '16:00:00',
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.show', $location))
            ->assertOk()
            ->assertSeeText('Services every 21 days')
            ->assertSeeText('No tax rate set.')
            ->assertSeeText('12.5% of gross sales')
            ->assertSeeText('8:30 AM - 4:00 PM')
            ->assertSeeText('10:00 AM - 6:30 PM')
            ->assertSeeText('Tue')
            ->assertSeeText('Closed');
    }

    public function test_location_rejects_access_hours_when_closing_is_before_opening(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Location Metadata Hours Error');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);
        $route = $this->createRoute($account, 'Hours Error Route');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('locations.create'))
            ->post(route('locations.store'), [
                'route_id' => $route->id,
                'location_name' => 'Bad Hours Stop',
                'address' => '123 Main Street',
                'city' => 'Toronto',
                'state' => 'ON',
                'zip_code' => 'M1M1M1',
                'access_hours' => [
                    1 => ['is_open' => '1', 'opens_at' => '17:00', 'closes_at' => '09:00'],
                ],
            ])
            ->assertRedirect(route('locations.create'))
            ->assertSessionHasErrors('access_hours.1.closes_at');

        $this->assertDatabaseMissing('tbl_locations', [
            'account_id' => $account->id,
            'location_name' => 'Bad Hours Stop',
        ]);
    }

    public function test_service_pattern_options_map_to_expected_intervals(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Location Pattern Mapping');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);
        $route = $this->createRoute($account, 'Pattern Route');

        foreach ([
            ['name' => 'Weekly Stop', 'type' => 'weekly', 'custom' => '', 'expected' => 7],
            ['name' => 'Biweekly Stop', 'type' => 'biweekly', 'custom' => '', 'expected' => 14],
            ['name' => 'Custom Stop', 'type' => 'custom', 'custom' => '30', 'expected' => 30],
        ] as $definition) {
            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->post(route('locations.store'), [
                    'route_id' => $route->id,
                    'location_name' => $definition['name'],
                    'address' => '123 Main Street',
                    'city' => 'Toronto',
                    'state' => 'ON',
                    'zip_code' => 'M1M1M1',
                    'service_pattern_type' => $definition['type'],
                    'service_interval_days_custom' => $definition['custom'],
                ])
                ->assertRedirect(route('locations.index'))
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('tbl_locations', [
                'account_id' => $account->id,
                'location_name' => $definition['name'],
                'service_interval_days' => $definition['expected'],
            ]);
        }
    }

    public function test_location_can_be_created_without_operational_metadata_and_displays_fallbacks(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Location Metadata Empty');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);
        $route = $this->createRoute($account, 'Empty Route');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('locations.store'), [
                'route_id' => $route->id,
                'location_name' => 'Empty Stop',
                'address' => '123 Main Street',
                'city' => 'Toronto',
                'state' => 'ON',
                'zip_code' => 'M1M1M1',
                'service_pattern_type' => '',
                'sales_tax_rate_percent' => '',
                'commission_rate_percent' => '',
            ])
            ->assertRedirect(route('locations.index'))
            ->assertSessionHasNoErrors();

        $location = Location::query()
            ->where('account_id', $account->id)
            ->where('location_name', 'Empty Stop')
            ->firstOrFail();

        $this->assertDatabaseHas('tbl_locations', [
            'id' => $location->id,
            'service_interval_days' => null,
            'sales_tax_rate' => null,
            'commission_rate' => null,
            'commission_on_net' => 0,
        ]);
        $this->assertSame(0, LocationAccessHour::query()->where('account_id', $account->id)->where('location_id', $location->id)->count());

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.show', $location))
            ->assertOk()
            ->assertSeeText('No pattern set.')
            ->assertSeeText('No tax rate set.')
            ->assertSeeText('No commission set.')
            ->assertSeeText('No hours set.');
    }

    public function test_commission_rate_round_trips_and_checkbox_can_be_unchecked(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Location Metadata Commission Precision');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);
        $route = $this->createRoute($account, 'Commission Route');
        $location = $this->createLocation($account, 'Commission Stop');

        RouteLocation::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_id' => $location->id,
            'stop_order' => 1,
            'is_primary' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->patch(route('locations.update', $location), [
                'route_id' => $route->id,
                'location_name' => 'Commission Stop',
                'address' => '123 Main Street',
                'city' => 'Toronto',
                'state' => 'ON',
                'zip_code' => 'M1M1M1',
                'service_pattern_type' => '',
                'sales_tax_rate_percent' => '',
                'commission_rate_percent' => '15',
            ])
            ->assertRedirect(route('locations.show', $location))
            ->assertSessionHasNoErrors();

        $location->refresh();

        $this->assertSame('0.1500', $location->commission_rate);
        $this->assertFalse($location->commission_on_net);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.show', $location))
            ->assertOk()
            ->assertSeeText('15% of gross sales');
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

    protected function createLocation(Account $account, string $name): Location
    {
        return Location::create([
            'account_id' => $account->id,
            'location_name' => $name,
            'address' => '123 Main Street',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M1M1M1',
        ]);
    }
}
